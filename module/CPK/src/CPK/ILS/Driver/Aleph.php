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

class Aleph extends AlephBase
{

    const AVAILABLE_STATUSES_DELIMITER = ',';

    protected $available_statuses = [];

    protected $eppnScope = null;

    protected $maxItemsParsed;

    public function init()
    {
        parent::init();

        if (isset($this->config['Catalog']['available_statuses']))
            $this->available_statuses = explode(self::AVAILABLE_STATUSES_DELIMITER, $this->config['Catalog']['available_statuses']);

        if (isset($this->config['Catalog']['eppnScope']))
            $this->eppnScope = $this->config['Catalog']['eppnScope'];

        if (isset($this->config['Availability']['maxItemsParsed'])) {
            $this->maxItemsParsed = intval($this->config['Availability']['maxItemsParsed']);
        } else
            $this->maxItemsParsed = 10;
    }

    public function getMyProfile($user)
    {
        $profile = parent::getMyProfile($user);

        $blocks = null;

        foreach ($profile['blocks'] as $block) {
            if (! empty($this->eppnScope)) {
                $blocks[$this->eppnScope] = (string) $block;
            } else
                $blocks[] = (string) $block;
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

                list ($status, $dueDate) = $this->parseStatusFromItem($item);

                $statuses[] = array(
                    'id' => $id,
                    'status' => $status,
                    'due_date' => $dueDate
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

                list ($status, $dueDate) = $this->parseStatusFromItem($item);

                $statuses[] = array(
                    'id' => $id,
                    'status' => $status,
                    'due_date' => $dueDate
                );

                // Returns parsed items to show it to user
                if (count($statuses) === $this->maxItemsParsed)
                    break;
            }

        return $statuses;
    }

    /**
     * Parses the status from <status> tag .
     *
     * . Sometimes there is due date, thus
     * it will always return an array of both status & dueDate (which will often be null)
     *
     * @param \SimpleXMLElement $item
     * @return array [ $status, $dueDate ]
     */
    protected function parseStatusFromItem(\SimpleXMLElement $item)
    {
        $status = (string) $item->{'status'};

        $isDueDate = preg_match('/[0-9]{2}\/.+\/[0-9]{4}/', $status);

        if ($isDueDate) {
            $dueDate = $status;

            $status = 'On Loan';
        } else {
            $dueDate = null;

            if (in_array($status, $this->available_statuses))
                $status = 'available';
            else
                $status = 'unavailable';
        }

        return [
            $status,
            $dueDate
        ];
    }
}