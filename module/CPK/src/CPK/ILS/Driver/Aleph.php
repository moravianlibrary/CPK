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
use VuFind\ILS\Driver\AlephRestfulException;

class Aleph extends AlephBase
{

    const CONFIG_ARRAY_DELIMITER = ',';

    protected $available_statuses = [];

    protected $logo= null;

    protected $maxItemsParsed;

    protected $dontShowLink;

    public function init()
    {
        parent::init();

        if (isset($this->config['Catalog']['available_statuses'])) {
            $this->available_statuses = explode(self::CONFIG_ARRAY_DELIMITER,
                $this->config['Catalog']['available_statuses']);
        }

        if (isset($this->config['Catalog']['logo'])) {
            $this->logo = $this->config['Catalog']['logo'];
        }

        if (isset($this->config['Availability']['maxItemsParsed'])) {
            $this->maxItemsParsed = intval(
                $this->config['Availability']['maxItemsParsed']);
        }

        if (! isset($this->maxItemsParsed) || $this->maxItemsParsed < 2) {
            $this->maxItemsParsed = 10;
        }

        if ($this->idResolver instanceof \VuFind\ILS\Driver\SolrIdResolver) {
            $this->idResolver = new SolrIdResolver($this->searchService,
                $this->config);
        }

        if (isset($this->config['Catalog']['dont_show_link'])) {
            $this->dontShowLink = explode(self::CONFIG_ARRAY_DELIMITER,
                $this->config['Catalog']['dont_show_link']);
        } else {
            $this->dontShowLink = [];
        }
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $details
     *            An array of item and patron data
     *
     * @return array An array of data on each request including
     *         whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($details)
    {
        $patron = $details['patron'];
        $patronId = $patron['id'];
        $count = 0;
        $statuses = array();

        $statuses['fails'] = 0;

        foreach ($details['details'] as $id) {

            try {
                $result = $this->alephWebService->doRestDLFRequest(
                    array(
                        'patron',
                        $patronId,
                        'circulationActions',
                        'requests',
                        'holds',
                        $id
                    ), null, "DELETE");
            } catch (\Exception $ex) {
                $statuses[$id] = array(
                    'success' => false,
                    'status' => 'cancel_hold_failed',
                    'sysMessage' => $ex->getMessage()
                );
                continue;
            }

            $reply_code = $result->{'reply-code'};
            if ($reply_code != "0000") {
                $message = $result->{'del-pat-hold'}->{'note'};
                if ($message == null) {
                    $message = $result->{'reply-text'};
                }
                $statuses[$id] = array(
                    'success' => false,
                    'status' => 'cancel_hold_failed',
                    'sysMessage' => (string) $message
                );
            } else {
                $count ++;
                $statuses[$id] = array(
                    'success' => true,
                    'status' => 'cancel_hold_ok'
                );
            }
        }
        $statuses['count'] = $count;
        return $statuses;
    }

    public function getMyProfile($user)
    {
        $profile = parent::getMyProfile($user);

        $blocks = [];
        $translatedBlock = '';

        if (isset($profile['blocks']))
            foreach ($profile['blocks'] as $block) {
                if (isset($this->availabilitySource)) {
                    $translatedBlock = $this->translator->getTranslator()->translate(
                        $this->availabilitySource . " " . "Block" . " " . (string) $block);

                    /* Skip blocks which are not translated. */
                    if ($translatedBlock === $this->availabilitySource . " " . "Block" . " " .
                        (string) $block) continue;
                }
                else {
                    $translatedBlock = $this->translator->getTranslator()->translate(
                        "Block " . (string) $block);
                    if ($translatedBlock === "Block " . (string) $block) continue;
                }

                if (! empty($this->logo)) {
                    if (! empty($blocks[$this->logo])) $blocks[$this->logo] .= ", " . $translatedBlock;
                    else $blocks[$this->logo] = $translatedBlock;
                } else
                    $blocks[] = $translatedBlock;
            }

        $profile['blocks'] = $blocks;

        return $profile;
    }

    public function getMyTransactions($user, $history=false, $limit = 0) {
        $transactions = parent::getMyTransactions($user, $history, $limit);

        foreach($transactions as &$transaction) {
            $transaction['loan_id'] = $transaction['item_id'];
        }

        return $transactions;
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

        $additionalAttributes = [
            'view' => 'full',
        ];
        if ($this->maxItemsParsed === - 1 || $idsCount <= $this->maxItemsParsed) {
            // Query all items at once ..

            // Get bibId from this e.g. [ MZK01-000910444:MZK50000910444000270, ... ]
            $explodedBibId = explode(':', reset($ids));
            $bibId = reset($explodedBibId);

            $path_elements = array(
                'record',
                str_replace('-', '', $bibId),
                'items'
            );

            $xml = $this->alephWebService->doRestDLFRequest($path_elements,
                $additionalAttributes);

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

                $alephItem = $this->parseItemFromRawItem($id, $item);

                $statuses[] = $alephItem->toAssocArray();
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

                $xml = $this->alephWebService->doRestDLFRequest($path_elements,
                    $additionalAttributes);

                if (! isset($xml->{'item'})) {
                    continue;
                }

                $item = $xml->{'item'};

                $alephItem = $this->parseItemFromRawItem($id, $item);

                $statuses[] = $alephItem->toAssocArray();

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
     * @return AlephItem $alephItem
     */
    protected function parseItemFromRawItem($id, \SimpleXMLElement $item)
    {
        $status = (string) $item->{'status'};

        $availability = (string) $item->{'z30'}->{'z30-item-status'};

        $isDueDate = preg_match('/^[0-9]+\/.+\/[0-9]+/', $status);

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

            if (in_array($availability, $this->dontShowLink)) {
                $holdType = 'false';
            }
        }

        return (new AlephItem($id))->setLabel($label)
            ->setAvailability($availability)
            ->setDueDate($dueDate)
            ->setHoldType($holdType)
            ->setStatus($status);
    }

    /**
     * Get profile information using X-server.
     *
     * @param array $user
     *            The patron array
     *
     * @throws ILSException
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfileX($user)
    {
        $recordList = array();
        if (! isset($user['college'])) {
            $user['college'] = $this->useradm;
        }
        $xml = $this->alephWebService->doXRequest("bor-info",
            array(
                'loans' => 'N',
                'cash' => 'N',
                'hold' => 'N',
                'library' => $user['college'],
                'bor_id' => $user['id']
            ), true);
        $id = (string) $xml->z303->{'z303-id'};
        $address1 = (string) $xml->z304->{'z304-address-2'};
        $address2 = (string) $xml->z304->{'z304-address-3'};
        $zip = (string) $xml->z304->{'z304-zip'};
        $phone = (string) $xml->z304->{'z304-telephone'};
        $barcode = (string) $xml->z304->{'z304-address-0'};
        $group = (string) $xml->z305->{'z305-bor-status'};
        $expiry = (string) $xml->z305->{'z305-expiry-date'};
        $credit_sum = (string) $xml->z305->{'z305-sum'};
        $credit_sign = (string) $xml->z305->{'z305-credit-debit'};
        $name = (string) $xml->z303->{'z303-name'};
        if (strstr($name, ",")) {
            list ($lastname, $firstname) = explode(",", $name);
        } else {
            $lastname = $name;
            $firstname = "";
        }
        if ($credit_sign == null) {
            $credit_sign = "C";
        }
        $recordList['firstname'] = $firstname;
        $recordList['lastname'] = $lastname;
        $recordList['cat_username'] = $user['id'];
        if (isset($user['email'])) {
            $recordList['email'] = $user['email'];
        } else {
            $recordList['email'] = (string) $xml->z304->{'z304-email-address'};
        }
        $recordList['address1'] = $address1;
        $recordList['address2'] = $address2;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['group'] = $group;
        $recordList['barcode'] = $barcode;
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['credit'] = $expiry;
        $recordList['credit_sum'] = $credit_sum;
        $recordList['credit_sign'] = $credit_sign;
        $recordList['id'] = $id;
        // deliquencies
        $blocks = array();
        foreach (array(
            'z303-delinq-1',
            'z303-delinq-2',
            'z303-delinq-3'
        ) as $elementName) {
            $block = (string) $xml->z303->{$elementName};
            if (! empty($block) && $block != '00') {
                $blocks[] = $block;
            }
        }
        foreach (array(
            'z305-delinq-1',
            'z305-delinq-2',
            'z305-delinq-3'
        ) as $elementName) {
            $block = (string) $xml->z305->{$elementName};
            if (! empty($block) && $block != '00') {
                $blocks[] = $block;
            }
        }
        $recordList['blocks'] = array_unique($blocks);
        return $recordList;
    }
}

class AlephItem
{

    protected $data = [];

    public function __construct($id = null)
    {
        if ($id !== null)
            return $this->setId($id);
        else
            return $this;
    }

    public function setId($id)
    {
        return $this->setProperty('id', $id);
    }

    public function setStatus($status)
    {
        return $this->setProperty('status', $status);
    }

    public function setDueDate($dueDate)
    {
        return $this->setProperty('due_date', $dueDate);
    }

    public function setHoldType($holdType)
    {
        return $this->setProperty('hold_type', $holdType);
    }

    public function setLabel($label)
    {
        return $this->setProperty('label', $label);
    }

    public function setAvailability($availability)
    {
        return $this->setProperty('availability', $availability);
    }

    protected function setProperty($name, $val)
    {
        if ($val !== null)
            $this->data[$name] = $val;

        return $this;
    }

    public function toAssocArray()
    {
        return $this->data;
    }
}

/**
 * SolrIdResolver - resolve bibliographic base against solr.
 */
class SolrIdResolver extends SolrIdResolverBase
{

    public function resolveIds(&$recordsToResolve)
    {
        $idsToResolve = array();
        foreach ($recordsToResolve as $record) {
            $identifier = $record[$this->itemIdentifier];
            if (isset($identifier) && ! empty($identifier)) {
                $idsToResolve[] = $record[$this->itemIdentifier];
            }
        }
        $resolved = $this->convertToIDUsingSolr($idsToResolve);
        foreach ($recordsToResolve as &$record) {
            if (isset($record[$this->itemIdentifier])) {
                $id = $record[$this->itemIdentifier];
                if (isset($resolved[$id])) {
                    $record['id'] = explode(".", $resolved[$id])[1];
                }
            }
        }
    }
}
