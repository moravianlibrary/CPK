<?php

/**
 * Aleph ILS driver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 22
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

    const CONFIG_ARRAY_DELIMITER = ',';

    protected $available_statuses = [];

    protected $logo = null;

    protected $maxItemsParsed;

    /**
     *
     * @var \CPK\Db\Table\AlephMappings
     */
    protected $alephMappingsTable;

    /**
     *
     * @var object
     */
    protected $addressMappings;

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
        
        if (! isset($this->maxItemsParsed) || $this->maxItemsParsed < 2) {
            $this->maxItemsParsed = 10;
        }
        
        if ($this->idResolver instanceof \VuFind\ILS\Driver\SolrIdResolver) {
            $this->idResolver = new SolrIdResolver($this->searchService, $this->config);
        }
        
        $this->addressMappings = $this->getDefaultMappings();
        
        if (isset($this->config['AddressMappings'])) {
            foreach ($this->config['AddressMappings'] as $key => $val) {
                $this->addressMappings[$key] = $val;
            }
        }
        
    }

    protected function getDefaultMappings()
    {
        return [
            'barcode' => null,
            'fullname' => 'z304-address-1',
            'street' => 'z304-address-2',
            'city' => 'z304-address-3',
            'zip' => 'z304-zip',
            'email' => 'z304-email-address',
            'phone' => 'z304-telephone-1',
            'group' => 'z305-bor-status',
            'expiration' => 'z305-expiry-date'
        ];
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelHoldDetails().
     *
     * @param array $details
     *            An array of item and patron data
     *            
     * @return array An array of data on each request including
     *         whether or not it was successful and a system message (if
     *         available)
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
                $result = $this->alephWebService->doRestDLFRequest(array(
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
        try {
            if (!$this->alephWebService->isXServerEnabled()) {
                $profile = $this->getMyProfileX($user);
            } else {
                $profile = $this->getMyProfileDLF($user);
            }
        } catch (\Exception $e) {
            
            $msg = $e->getMessage();
            
            /* TODO: Probably expired account ?
             * message: XServer error: Error retrieving Patron System Key.
             * or 2nd : ID čtenáře není platné
             * or 3rd : The patron ID is invalid
             */
            throw $e;
        }
        
        $blocks = [];
        $translatedBlock = '';
        
        if (isset($profile['blocks']))
            foreach ($profile['blocks'] as $block) {
                if (isset($this->availabilitySource)) {
                    $translatedBlock = $this->translator->getTranslator()->translate($this->availabilitySource . " " . "Block" . " " . (string) $block);
                    
                    /* Skip blocks which are not translated. */
                    if ($translatedBlock === $this->availabilitySource . " " . "Block" . " " . (string) $block)
                        continue;
                } else {
                    $translatedBlock = $this->translator->getTranslator()->translate("Block " . (string) $block);
                    if ($translatedBlock === "Block " . (string) $block)
                        continue;
                }
                
                if (! empty($this->logo)) {
                    if (! empty($blocks[$this->logo]))
                        $blocks[$this->logo] .= ", " . $translatedBlock;
                    else
                        $blocks[$this->logo] = $translatedBlock;
                } else
                    $blocks[] = $translatedBlock;
            }
        
        $profile['blocks'] = $blocks;
        
        return $profile;
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
        $xml = $this->alephWebService->doXRequest("bor-info", array(
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

    /**
     * Get profile information using DLF service.
     *
     * @param array $user
     *            The patron array
     *            
     * @throws ILSException
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfileDLF($user)
    {
        $xml = $this->alephWebService->doRestDLFRequest(array(
            'patron',
            $user['id'],
            'patronInformation',
            'address'
        ));
        
        $addressInfo = $xml->{'address-information'};
        
        $fullname = (string) $addressInfo->{$this->addressMappings['fullname']};
        $street = (string) $addressInfo->{$this->addressMappings['street']};
        
        $dateFrom = (string) $addressInfo->{'z304-date-from'};
        $dateTo = (string) $addressInfo->{'z304-date-to'};
        
        if (! empty($this->addressMappings['barcode']))
            $barcode = (string) $addressInfo->{$this->addressMappings['barcode']};
        else
            $barcode = (string) $addressInfo->{'z304-address-5'};
        $city = (string) $addressInfo->{$this->addressMappings['city']};
        
        if (! empty($this->addressMappings['zip']))
            $zip = (string) $addressInfo->{$this->addressMappings['zip']};
        else
            $zip = (string) $addressInfo->{'z304-zip'};
        
        if (! empty($this->addressMappings['email']))
            $email = (string) $addressInfo->{$this->addressMappings['email']};
        else
            $email = (string) $addressInfo->{'z304-email-address'};
        
        if (! empty($this->addressMappings['phone']))
            $phone = (string) $addressInfo->{$this->addressMappings['phone']};
        else
            $phone = (string) $addressInfo->{'z304-telephone-1'};
        
        if (strpos($fullname, ",") === false) {
            $recordList['lastname'] = $fullname;
            $recordList['firstname'] = "";
        } else {
            list ($recordList['lastname'], $recordList['firstname']) = explode(",", $fullname);
        }
        $recordList['address1'] = $street;
        $recordList['address2'] = $city;
        $recordList['barcode'] = $barcode;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['email'] = $email;
        $recordList['addressValidFrom'] = $this->parseDate($dateFrom);
        $recordList['addressValidTo'] = $this->parseDate($dateTo);
        $recordList['id'] = $user['id'];
        $recordList['cat_username'] = $user['id'];
        $xml = $this->alephWebService->doRestDLFRequest(array(
            'patron',
            $user['id'],
            'patronStatus',
            'registration'
        ));
        $institution = $xml->{'registration'}->{'institution'};
        
        if (! empty($this->addressMappings['group']))
            $status = (string) $institution->{$this->addressMappings['group']};
        else
            $status = (string) $institution->{'z305-bor-status'};
        
        if (! empty($this->addressMappings['expiration']))
            $expiry = (string) $institution->{$this->addressMappings['expiration']};
        else
            $expiry = (string) $institution->{'z305-expiry-date'};
        
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['group'] = $status;
        return $recordList;
    }

    public function getMyTransactions($user, $history = false, $limit = 0)
    {
        $transactions = parent::getMyTransactions($user, $history, $limit);
        
        foreach ($transactions as &$transaction) {
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
     * @param string $item_id
     *            The record id to retrieve the holdings for
     *            
     * @throws ILSException
     * @return mixed On success, an associative array with the following keys:
     *         id, availability (boolean), status, location, reserve,
     *         callnumber.
     */
    public function getStatuses($ids, $patron = [], $filter = [], $bibId = null)
    {
        $statuses = array();
        
        $idsCount = count($ids);
        
        $additionalAttributes = [
            'view' => 'full'
        ];
        
        if ($filter !== null)
            foreach ($filter as $name => $value) {
                $additionalAttributes[$name] = $value;
            }
        
        if (! empty($patron['id'])) {
            $additionalAttributes['patron'] = $patron['id'];
        }
        
        if ($this->maxItemsParsed === - 1 || $idsCount <= $this->maxItemsParsed) {
            // Query all items at once ..
            
            $path_elements = array(
                'record',
                str_replace('-', '', $bibId),
                'items'
            );
            
            $xml = $this->alephWebService->doRestDLFRequest($path_elements, $additionalAttributes);
            
            if (! isset($xml->{'items'})) {
                return $statuses;
            }
            
            foreach ($xml->{'items'}->{'item'} as $item) {
                
                $item_id = $item->attributes()->href;
                $item_id = substr($item_id, strrpos($item_id, '/') + 1);
                
                // do not process ids which are not in desired $ids array
                if (array_search($item_id, $ids) === false)
                    continue;
                
                $statuses[] = $this->parseItem($bibId, $item_id, $item, $patron);
            }
        } else // Query one by one item
            foreach ($ids as $item_id) {
                
                if (isSeT($additionalAttributes['patron']))
                    // We can search for patron specific bib info
                    // Example URL:
                    // patron/700/record/MZK01000244261/items/MZK50000244261006690
                    $path_elements = array(
                        'patron',
                        $additionalAttributes['patron'],
                        'record',
                        str_replace('-', '', $bibId),
                        'items',
                        $item_id
                    );
                else
                    $path_elements = array(
                        'record',
                        str_replace('-', '', $bibId),
                        'items',
                        $item_id
                    );
                
                $xml = $this->alephWebService->doRestDLFRequest($path_elements, $additionalAttributes);
                
                if (! isset($xml->{'item'})) {
                    continue;
                }
                
                $item = $xml->{'item'};
                
                $statuses[] = $this->parseItem($bibId, $item_id, $item, $patron);
                
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
    protected function parseItem($bibId, $item_id, \SimpleXMLElement $item, $patron)
    {
        $item_status = $this->alephTranslator->tab15Translate($item);
        if ($item_status['opac'] != 'Y') {
            continue;
        }
        
        $available = false;
        $reserve = ($item_status['request'] == 'C') ? 'N' : 'Y';
        $z30 = $item->z30;
        $collection = (string) $z30->{'z30-collection'};
        $collection_desc = array(
            'desc' => $collection
        );
        $collection_desc = $this->alephTranslator->tab40Translate($item);
        $sub_library_code = (string) $item->{'z30-sub-library-code'};
        $requested = false;
        $duedate = null;
        $addLink = false;
        $status = (string) $item->{'status'};
        if (in_array($status, $this->available_statuses)) {
            $available = true;
        }
        if ($item_status['request'] == 'Y' && $available == false) {
            $addLink = true;
        }
        // Customized from here
        if (! empty($patron)) {
            $hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
            
            if (! empty($hold_request))
                $addLink = ($hold_request[0] == 'Y');
            // To here
        }
        $matches = [];
        if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
            $duedate = $this->parseDate($matches[1]);
            $requested = (trim($matches[2]) == "Requested");
        } else 
            if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches)) {
                $duedate = $this->parseDate($matches[1]);
            }
        // process duedate_status
        $duedate_status = $item_status['desc'];
        if ($available && $this->duedates) {
            foreach ($this->duedates as $key => $value) {
                if (preg_match($value, $item_status['desc'])) {
                    $duedate_status = $key;
                    break;
                }
            }
        } else 
            if (! $available && ($status == "On Hold" || $status == "Requested" || $status == "Požadováno")) {
                $duedate_status = "requested";
            }
        
        $note = (string) $z30->{'z30-note-opac'};
        
        $availability = (string) $z30->{'z30-item-status'};
        
        // Customized from here
        $isDueDate = preg_match('/^[0-9]+\/.+\/[0-9]+/', $status);
        
        $holdType = 'Recall This';
        
        if ($isDueDate) {
            
            $duedate = (string) $duedate;
            
            if (empty($duedate)) {
                $duedate = $status;
            }
            
            $label = 'label-warning';
            $status = 'On Loan';
        } else {
            
            if ($available) {
                
                $status = 'available';
                $holdType = 'Place a Hold';
                $label = 'label-success';
            } elseif ($duedate_status = "requested") {
                $label = 'label-warning';
                $status = 'On Order';
            } else {
                $label = 'label-danger';
                $status = 'unavailable';
            }
        }
        
        return [
            'id' => $bibId,
            'item_id' => $item_id,
            'availability' => $availability,
            'status' => $status,
            'location' => $sub_library_code,
            'reserve' => 'N',
            'callnumber' => (string) $z30->{'z30-call-no'},
            'duedate' => $duedate,
            'number' => (string) $z30->{'z30-inventory-number'},
            'barcode' => (string) $z30->{'z30-barcode'},
            'description' => (string) $z30->{'z30-description'},
            'notes' => ($note == null) ? null : array(
                $note
            ),
            'is_holdable' => true,
            'addLink' => $addLink,
            'holdtype' => $holdType,
                /* below are optional attributes*/
                'duedate_status' => $status,
            'collection' => (string) $collection,
            'collection_desc' => (string) $collection_desc['desc'],
            'callnumber_second' => (string) $z30->{'z30-call-no-2'},
            'sub_lib_desc' => (string) $item_status['sub_lib_desc'],
            'no_of_loans' => (string) $z30->{'$no_of_loans'},
            'requested' => (string) $requested,
            // Customized from here
            'label' => $label
        ];
    }

    /**
     * Support method for placeHold -- get holding info for an item.
     *
     * @param string $id
     *            Item ID
     * @param string $bibId
     *            Bib ID
     * @param string $patronId
     *            Patron ID
     *            
     * @return array
     */
    public function getItemStatus($id, $bibId, $patronId)
    {
        $holding = array();
        $bibId = str_replace("-", "", $bibId);
        $params = array();
        $params['view'] = 'full';
        $params['patron'] = $patronId;
        $xml = $this->alephWebService->doRestDLFRequest(array(
            'patron',
            $patronId,
            'record',
            $bibId,
            'items',
            $id
        ), $params);
        if (! isset($xml->{'item'})) {
            return $holding;
        }
        $item = $xml->{'item'};
        $duedate = null;
        $status = (string) $item->{'status'};
        $matches = [];
        if (preg_match("/([0-9]*\\/[a-zA-Z0-9]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
            $duedate = $this->parseDate($matches[1]);
        } else 
            if (preg_match("/([0-9]*\\/[a-zA-Z0-9]*\\/[0-9]*)/", $status, $matches)) {
                $duedate = $this->parseDate($matches[1]);
            }
        $requests = 0;
        $str = $xml->xpath('//item/queue/text()');
        $matches = [];
        if ($str != null && preg_match("/(\d) .+ (\d) [\w]+/", $str[0], $matches)) {
            $requests = $matches[1];
        }
        $retStatus = null;
        if (preg_match("/(Requested|Požadováno)/", $status, $matches)) {
            $retStatus = 'On Order';
        }
        $holding = [
            'id' => $bibId,
            'item_id' => $id,
            'duedate' => (string) $duedate,
            'requests_placed' => $requests,
            'status' => $retStatus
        ];
        return $holding;
    }

    /**
     * Parse a date.
     *
     * @param string $date
     *            Date to parse
     *            
     * @return string
     */
    public function parseDate($date)
    {
        if ($date == null || $date == "") {
            return "";
        } elseif (preg_match("/^[0-9]{8}$/", $date) === 1) {
            // 20120725
            return $this->dateConverter->convertToDisplayDate('Ynd', $date);
        } elseif (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
            // 13/jan/2012
            return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
        } elseif (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
            // 13/7/2012
            return $this->dateConverter->convertToDisplayDate('d/m/Y', $date);
        } elseif (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{2}$/", $date) === 1) {
            // 13/7/12 - ntk uses this format
            $date = substr_replace($date, '20', - 2, 0);
            return $this->dateConverter->convertToDisplayDate('d/m/Y', $date);
        } else {
            throw new \Exception("Invalid date: $date");
        }
    }
}