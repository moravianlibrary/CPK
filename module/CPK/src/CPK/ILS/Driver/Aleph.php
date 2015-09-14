<?php
/**
 * Aleph ILS driver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 18.1.
 *
 * TODO: login, course information, getNewItems, duedate in holdings,
 * https connection to x-server, ...
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;

use MZKCommon\ILS\Driver\Aleph as AlephBase;
use VuFind\ILS\Driver\SolrIdResolver as SolrIdResolverBase;

class Aleph extends AlephBase
{

    const CONFIG_ARRAY_DELIMITER = ',';

    protected $available_statuses = [];

    protected $logo = null;

    protected $maxItemsParsed;

    public function init()
    {
        parent::init();

        if (isset($this->config['Catalog']['available_statuses'])) {
            $this->available_statuses = explode(self::CONFIG_ARRAY_DELIMITER, $this->config['Catalog']['available_statuses']);
        }

        if (isset($this->config['Catalog']['logo'])) {
            $this->logo = $this->config['Catalog']['logo'];
        }

        if (isset($this->config['Availability']['maxItemsParsed'])) {
            $this->maxItemsParsed = intval($this->config['Availability']['maxItemsParsed']);
        }

        if (!isset($this->maxItemsParsed) || $this->maxItemsParsed < 2) {
            $this->maxItemsParsed = 10;
        }

        if (isset($this->config['IdResolver']['type'])) {
            $idResolverType = $this->config['IdResolver']['type'];
        }

        if ($idResolverType == 'solr') {
            $this->idResolver = new SolrIdResolver($this->searchService, $this->config);
        }

        if (isset($this->config['Catalog']['dont_show_link'])) {
            $this->dontShowLink = explode(self::CONFIG_ARRAY_DELIMITER, $this->config['Catalog']['dont_show_link']);
        }
    }

    public function getMyProfile($user)
    {
        $profile = parent::getMyProfile($user);

        $blocks = [];
        $translatedBlock = '';

        if (isset($profile['blocks']))
            foreach ($profile['blocks'] as $block) {
                if (! empty($this->config['Catalog']['agency']))
                    $translatedBlock = $this->translator->getTranslator()->translate($this->config['Catalog']['agency'] .
                            " " . "Block" . " " . (string) $block);
                else $translatedBlock = $this->translator->getTranslator()->translate("Block " . (string) $block);

                if (! empty($this->logo)) {
                    $blocks[$this->logo] = $translatedBlock;
                } else
                    $blocks[] = $translatedBlock;
            }

        $profile['blocks'] = $blocks;

        return $profile;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id
     *            The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed On success, an associative array with the following keys:
     *         id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatuses($ids)
    {
        $statuses = array();

        $idsCount = count($ids);
        if ($this->maxItemsParsed === - 1 || $idsCount <= $this->maxItemsParsed) {
            // Query all items at once ..

            // Get bibId from this e.g. [ MZK01-000910444:MZK50000910444000270, ... ]
            $bibId = reset(explode(':', reset($ids)));

            $path_elements = array(
                'record',
                str_replace('-', '', $bibId),
                'items'
            );

            $xml = $this->alephWebService->doRestDLFRequest($path_elements, [
                'view' => 'full'
            ]);

            if (! isset($xml->{'items'})) {
                return $statuses;
            }

            foreach ($xml->{'items'}->{'item'} as $item) {

                $item_id = $item->attributes()->href;
                $item_id = substr($item_id, strrpos($item_id, '/') + 1);

                // Build the id into initial state so that jQuery knows which row has to be updated
                $id = $bibId . ':' . $item_id;

                // do not process ids which are not in desired $ids array
                if (array_search($id, $ids) === false)
                    continue;

                list ($status, $dueDate, $holdType, $label) = $this->parseStatusFromItem($item);

                $statuses[] = array(
                    'id' => $id,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'hold_type' => $holdType,
                    'label' => $label
                );
            }
        } else // Query one by one item
            foreach ($ids as $id) {
                list ($resource, $itemId) = explode(':', $id);

                $path_elements = array(
                    'record',
                    str_replace('-', '', $resource),
                    'items',
                    $itemId
                );

                $xml = $this->alephWebService->doRestDLFRequest($path_elements);

                if (! isset($xml->{'item'})) {
                    continue;
                }

                $item = $xml->{'item'};

                list ($status, $dueDate, $holdType, $label) = $this->parseStatusFromItem($item);

                $statuses[] = array(
                    'id' => $id,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'hold_type' => $holdType,
                    'label' => $label
                );

                // Returns parsed items to show it to user
                if (count($statuses) === $this->maxItemsParsed)
                    break;
            }

        return $statuses;
    }

    /**
     * Parses the status from <status> tag
     *
     * Returns an array of status, dueDate (which will often be null) & holdType
     *
     * @param \SimpleXMLElement $item
     * @return array [ $status, $dueDate, $holdType ]
     */
    protected function parseStatusFromItem(\SimpleXMLElement $item)
    {
        $status = (string) $item->{'status'};

        $itemStatus = (string) $item->{'z30'}->{'z30-item-status'};

        $isDueDate = preg_match('/[0-9]{2}\/.+\/[0-9]{4}/', $status);

        $holdType = 'Recall This';
        $label = 'label-danger';

        if ($isDueDate) {
            $dueDate = $status;

            $status = 'On Loan';
        } else {
            $dueDate = null;

            if (in_array($status, $this->available_statuses)) {

                $status = 'available';
                $holdType = 'Place a Hold';
                $label = 'label-success';

            } else {
                $status = 'unavailable';
            }

            if (in_array($itemStatus, $this->dontShowLink)) {
                $holdType = 'false';
            }
        }

        return [
            $status,
            $dueDate,
            $holdType,
            $label
        ];
    }


}


/**
 * SolrIdResolver - resolve bibliographic base against solr.
 *
 */
class SolrIdResolver extends SolrIdResolverBase
{
    public function resolveIds(&$recordsToResolve)
    {
        $idsToResolve = array();
        foreach ($recordsToResolve as $record) {
            $identifier = $record[$this->itemIdentifier];
            if (isset($identifier) && !empty($identifier)) {
                $idsToResolve[] = $record[$this->itemIdentifier];
            }
        }
        $resolved = $this->convertToIDUsingSolr($idsToResolve);
        foreach ($recordsToResolve as &$record) {
            if (isset($record[$this->itemIdentifier])) {
                $id = $record[$this->itemIdentifier];
                if (isset($resolved[$id])) {
                    $record['id'] = explode(".",$resolved[$id])[1];
                }
            }
        }
    }
}