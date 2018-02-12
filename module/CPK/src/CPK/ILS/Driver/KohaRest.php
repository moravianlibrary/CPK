<?php
/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
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
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;

use CPK\Auth\KohaRestService;
use VuFind\ILS\Driver\AbstractBase;
use CPK\ILS\Logic\KohaRestNormalizer;
use VuFind\Exception\Date as DateException;
use VuFind\Exception\ILS as ILSException;
use Zend\I18n\Translator\TranslatorInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use \VuFind\Date\Converter as DateConverter;
use \Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

/**
 * VuFind Driver for Koha, using REST API
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends AbstractBase implements LoggerAwareInterface, TranslatorAwareInterface
{
    /**
     * Library prefix
     *
     * @var string
     */
    protected $source = '';

    protected $dateConverter;
    protected $defaultPickUpLocation;
    protected $kohaRestService;
    protected $translator;
    protected $logger;

    /**
     * Item status rankings. The lower the value, the more important the status.
     *
     * @var array
     */
    protected $statusRankings = [
        'Charged' => 1,
        'On Hold' => 2
    ];

    /**
     * Mappings from renewal block reasons
     *
     * @var array
     */
    protected $renewalBlockMappings = [
        'too_soon' => 'Cannot renew yet',
        'onsite_checkout' => 'Copy has special circulation',
        'on_reserve' => 'renew_item_requested',
        'too_many' => 'renew_item_limit',
        'restriction' => 'Borrowing Block Message',
        'overdue' => 'renew_item_overdue',
        'cardlost' => 'renew_card_lost',
        'gonenoaddress' => 'Borrowing Block Koha Reason Patron_GoneNoAddress',
        'debarred' => 'Borrowing Block Koha Reason Patron_DebarredOverdue',
        'debt' => 'renew_debt'
    ];

    public function __construct(DateConverter $dateConverter, KohaRestService $kohaRestService) {
        $this->dateConverter = $dateConverter;
        $this->kohaRestService = $kohaRestService;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        // Validate config
        $required = ['host'];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }

        if (!empty($this->config['StatusRankings'])) {
            $this->statusRankings = array_merge(
                $this->statusRankings, $this->config['StatusRankings']
            );
        }

        if (isset($this->config['Availability']['source']))
            $this->source = $this->config['Availability']['source'];

        $this->kohaRestService->setConfig($this->config);
        $this->kohaRestService->setSource($this->source);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setTranslator(TranslatorInterface $translator)
    {
        if (method_exists($translator, "getTranslator")) {
            $this->translator = $translator->getTranslator();
        } else {
            $this->logger->err("Error getting translator.");
        }
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getItemStatusesForBiblio($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getItemStatusesForBiblio($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     * @param array $patron Patron data
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getItemStatusesForBiblio($id, $patron);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws \VuFind\Exception\ILS
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron) //TODO deal if it is needed
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Get Renew Details
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['checkout_id'] . '|' . $checkOutDetails['item_id'];
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id']], __FUNCTION__,false, 'GET', $patron
        );

        return [
            'cat_username' => $patron['id'],
            'id' => $patron['id'],
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'city' => $result['city'],
            'country' => $result['country'],
            'zip' => $result['postal_code'],
            'phone' => $result['phone'],
            'group' => '',
            'blocks' => '',
            'email' => $result['email'],
            'expire' => $result['expiry_date']
        ];
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'checkouts'],
            __FUNCTION__,
            ['patron_id' => $patron['id']],
            'GET',
            $patron
        );

        $transactions = [];
        if (empty($result)) {
            return $transactions;
        }
        foreach ($result as $entry) {
            $transactions[] = [
                'id' => $entry['item_id'],
                'loan_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'duedate' => $entry['due_date'],
                'dueStatus' => $entry['due_status'],
                'renew' => $entry['renewals'],
                'barcode' => '',
                'renewable' => $this->isItemRenewable($entry['checkout_id']),
            ];
        }

        return $transactions;
    }

    /**
     * Checks if item is renewable
     *
     * @param $checkoutId
     * @return bool
     * @internal param $checkout_id
     */
    public function isItemRenewable($checkoutId) {
        $result = $this->makeRequest(
            ['v1', 'checkouts', $checkoutId, 'renewability'],
            __FUNCTION__,
            [],
            'GET'
        );

        return $result['renewable'] && !$result['error'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $patron = $renewDetails['patron'];
        $finalResult = ['details' => []];

        foreach ($renewDetails['details'] as $details) {
            list($checkoutId, $itemId) = explode('|', $details);
            list($code, $result) = $this->makeRequest(
                ['v1', 'checkouts', $checkoutId, 'renewal'],
                __FUNCTION__,
                false,
                'POST',
                $patron,
                true
            );
            if ($code == 403) {
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => false
                ];
            } else {
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => true,
                    'new_date' => $result['date_due']
                ];
            }
        }
        return $finalResult;
    }

    /**
     * Get Patron Transaction History
     *
     * This is responsible for retrieving all historical transactions
     * (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactionHistory($patron, $params)
    {
        $sort = explode(
            ' ', !empty($params['sort']) ? $params['sort'] : 'checkout desc', 2
        );
        if ($sort[0] == 'checkout') {
            $sortKey = 'issuedate';
        } elseif ($sort[0] == 'return') {
            $sortKey = 'returndate';
        } else {
            $sortKey = 'date_due';
        }
        $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'desc' : 'asc';

        $pageSize = isset($params['limit']) ? $params['limit'] : 50;
        $queryParams = [
            'borrowernumber' => $patron['id'],
            'sort' => $sortKey,
            'order' => $direction,
            'offset' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'limit' => $pageSize
        ];

        $transactions = $this->makeRequest(
            ['v1', 'checkouts', 'history'],
            __FUNCTION__,
            $queryParams,
            'GET',
            $patron
        );

        $result = [
            'count' => $transactions['total'],
            'transactions' => []
        ];

        foreach ($transactions['records'] as $entry) {
            try {
                $item = $this->getItem($entry['itemnumber']);
            } catch (\Exception $e) {
                $item = [];
            }
            $volume = isset($item['enumchron'])
                ? $item['enumchron'] : '';
            $title = '';
            if (!empty($item['biblionumber'])) {
                $bib = $this->getBibRecord($item['biblionumber']);
                if (!empty($bib['title'])) {
                    $title = $bib['title'];
                }
                if (!empty($bib['title_remainder'])) {
                    $title .= ' ' . $bib['title_remainder'];
                    $title = trim($title);
                }
            }

            $dueStatus = false;
            $now = time();
            $dueTimeStamp = strtotime($entry['date_due']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }

            $transaction = [
                'id' => isset($item['biblionumber']) ? $item['biblionumber'] : '',
                'checkout_id' => $entry['issue_id'],
                'item_id' => $entry['itemnumber'],
                'title' => $title,
                'volume' => $volume,
                'checkoutdate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['issuedate']
                ),
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['date_due']
                ),
                'dueStatus' => $dueStatus,
                'returndate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['returndate']
                ),
                'renew' => $entry['renewals']
            ];

            $result['transactions'][] = $transaction;
        }

        return $result;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'holds'],
            __FUNCTION__,
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );

        $holds = [];
        if (!isset($result)) {
            return $holds;
        }
        foreach ($result as $entry) {
            $holds[] = [
                'id' => $entry['biblionumber'],
                'item_id' => $entry['biblionumber'] ? $entry['biblionumber'] : $entry['reserve_id'],
                'location' => $entry['branchcode'],
                'create' => $entry['reservedate'],
                'expire' => $entry['expirationdate'],
                'position' => $entry['priority'],
                'available' => !empty($entry['waitingdate']),
                'requestId' => $entry['reserve_id'],
                'in_transit' => '',
                'barcode' => ''
            ];
        }
        return $holds;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold. The data in $cancelDetails['details'] is determined
     * by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $details = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $detail) {
            list($holdId, $itemId) = explode('|', $detail, 2);
            list($resultCode) = $this->makeRequest(
                ['v1', 'holds', $holdId], __FUNCTION__, [], 'DELETE', $patron, true
            );

            if ($resultCode != 200) {
                $response[$itemId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false
                ];
            } else {
                $response[$itemId] = [
                    'success' => true,
                    'status' => 'hold_cancel_success'
                ];
                ++$count;
            }
        }
        return ['count' => $count, 'items' => $response];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $result = $this->makeRequest(
            ['v1', 'libraries'],
            __FUNCTION__,
            false,
            'GET',
            $patron
        );
        if (empty($result)) {
            return [];
        }
        $locations = [];
        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];
        foreach ($result as $location) {
            if (!$location['pickup_location']
                || in_array($location['branchcode'], $excluded)
            ) {
                continue;
            }
            $locations[] = [
                'locationID' => $location['branchcode'],
                'locationDisplay' => $location['branchname']
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = isset($this->config['Holds']['pickUpLocationOrder'])
            ? $this->config['Holds']['pickUpLocationOrder'] : 'default';
        if (count($locations) > 1 && !empty($orderSetting)) {
            $locationOrder = $orderSetting === 'default'
                ? [] : array_flip(explode(':', $orderSetting));
            $sortFunction = function ($a, $b) use ($locationOrder) {
                $aLoc = $a['locationID'];
                $bLoc = $b['locationID'];
                if (isset($locationOrder[$aLoc])) {
                    if (isset($locationOrder[$bLoc])) {
                        return $locationOrder[$aLoc] - $locationOrder[$bLoc];
                    }
                    return -1;
                }
                if (isset($locationOrder[$bLoc])) {
                    return 1;
                }
                return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
            };
            usort($locations, $sortFunction);
        }

        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Cancel Hold Details
     *
     * Get required data for canceling a hold. This value is used by relayed to the
     * cancelHolds function when the user attempts to cancel a hold.
     *
     * @param array $holdDetails An array of hold data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['available'] || $holdDetails['in_transit'] ? ''
            : $holdDetails['requestId'] . '|' . $holdDetails['item_id'];
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if ($this->getPatronBlocks($patron)) {
            return false;
        }
        $level = isset($data['level']) ? $data['level'] : 'copy';
        if ('title' == $data['level']) {
            $result = $this->makeRequest(
                ['v1', 'availability', 'biblio', 'hold'],
                __FUNCTION__,
                ['biblionumber' => $id, 'borrowernumber' => $patron['id']],
                'GET',
                $patron
            );
            if (!empty($result[0]['availability']['available'])) {
                return [
                    'valid' => true,
                    'status' => 'title_hold_place'
                ];
            }
            return [
                'valid' => false,
                'status' => $this->getHoldBlockReason($result)
            ];
        }
        $result = $this->makeRequest(
            ['v1', 'availability', 'item', 'hold'],
            __FUNCTION__,
            ['itemnumber' => $data['item_id'], 'borrowernumber' => $patron['id']],
            'GET',
            $patron
        );
        if (!empty($result[0]['availability']['available'])) {
            return [
                'valid' => true,
                'status' => 'hold_place'
            ];
        }
        return [
            'valid' => false,
            'status' => $this->getHoldBlockReason($result)
        ];
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        $level = isset($holdDetails['level']) && !empty($holdDetails['level'])
            ? $holdDetails['level'] : 'copy';
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $itemId = isset($holdDetails['item_id']) ? $holdDetails['item_id'] : false;
        $comment = isset($holdDetails['comment']) ? $holdDetails['comment'] : '';
        $bibId = $holdDetails['id'];

        // Convert last interest date from Display Format to Koha's required format
        try {
            $lastInterestDate = $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            );
        } catch (DateException $e) {
            // Hold Date is invalid
            return $this->holdError('hold_date_invalid');
        }

        if ($level == 'copy' && empty($itemId)) {
            throw new ILSException("Hold level is 'copy', but item ID is empty");
        }

        try {
            $checkTime = $this->dateConverter->convertFromDisplayDate(
                'U', $holdDetails['requiredBy']
            );
            if (!is_numeric($checkTime)) {
                throw new DateException('Result should be numeric');
            }
        } catch (DateException $e) {
            throw new ILSException('Problem parsing required by date.');
        }

        if (time() > $checkTime) {
            // Hold Date is in the past
            return $this->holdError('hold_date_past');
        }

        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'biblionumber' => (int)$bibId,
            'borrowernumber' => (int)$patron['id'],
            'branchcode' => $pickUpLocation,
            'expirationdate' => $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            )
        ];
        if ($level == 'copy') {
            $request['itemnumber'] = (int)$itemId;
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'holds'],
            __FUNCTION__,
            json_encode($request),
            'POST',
            $patron,
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'account'],
            __FUNCTION__,
            false,
            'GET',
            $patron
        );

        $fines = [];
        if (empty($result['outstanding_debits']['lines'])) {
            return $fines;
        }

        foreach ($result['outstanding_debits']['lines'] as $entry) {
            $fines[] = [
                'amount' => $entry['amount'],
                'checkout' => $entry['date'],
                'fine' => $entry['account_type'],
                'balance' => $entry['amount_outstanding'],
                'createdate' => '',
                'duedate' => '',
                'item_id' => $entry['item_id']
            ];
        }
        return $fines;
    }

    /**
     * Make Request
     *
     * Makes a request to the Koha REST API
     *
     * @param array $hierarchy Array of values to embed in the URL path of
     * the request
     * @param $action string An action driver is doing now
     * @param array|bool $params A keyed array of query data
     * @param string $method The http request method to use (Default is GET)
     * @param array $patron Patron information when using patron APIs
     * @param bool $returnCode If true, returns HTTP status code in addition to
     * the result
     * @param bool $oauth2Needed
     * @return mixed
     * @throws ILSException
     * @internal param bool $authNeeded
     */
    protected function makeRequest($hierarchy, $action, $params = false, $method = 'GET',
        $patron = null, $returnCode = false, $oauth2Needed = true
    ) {
        // Set up the request
        $apiUrl = $this->config['Catalog']['host'];

        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        $client = $oauth2Needed
            ? $this->kohaRestService->createOAUTH2Client($apiUrl)
            : $this->kohaRestService->createHttpClient($apiUrl, ['username' => 'cpk', 'password' => 'cpk']);

        // Add params
        if (false !== $params) {
            if ('GET' === $method || 'DELETE' === $method) {
                $client->setParameterGet($params);
            } else {
                $body = '';
                if (is_string($params)) {
                    $body = $params;
                } else {
                    if (isset($params['##body##'])) {
                        $body = $params['##body##'];
                        unset($params['##body##']);
                        $client->setParameterGet($params);
                    } else {
                        $client->setParameterPost($params);
                    }
                }
                if ('' !== $body) {
                    $client->getRequest()->setContent($body);
                    $client->getRequest()->getHeaders()
                        ->addHeaderLine('Content-Type', 'application/json');
                }
            }
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logger->err(
                "$method request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            $this->kohaRestService->renewToken();

            $client = $this->kohaRestService->createOAUTH2Client($apiUrl);

            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->logger->err(
                    "$method request for '$apiUrl' failed: " . $e->getMessage()
                );
                throw new ILSException('Problem with Koha REST API.');
            }
        }

        $result = $response->getBody();

        $fullUrl = $apiUrl;
        if ($method == 'GET') {
            $fullUrl .= '?' . $client->getRequest()->getQuery()->toString();
        }
        $this->logger->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $method request $fullUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        // Handle errors as complete failures only if the API call didn't return
        // valid JSON that the caller can handle
        $decodedResult = json_decode($result, true);
        if (!$response->isSuccess()
            && (null === $decodedResult || !empty($decodedResult['error']))
            && !$returnCode
        ) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->logger->err(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        $decodedResult = $this->normalizeResponse($action)->normalize($decodedResult);

        return $returnCode ? [$response->getStatusCode(), $decodedResult]
            : $decodedResult;
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null)
    {
        $result = $this->makeRequest(
            ['v1', 'contrib', 'knihovny_cz', 'biblios', $id, 'allows_checkout'],
            __FUNCTION__,
            [],
            'GET',
            $patron,
            false,
            false
        );
        if (empty($result[0]['item_availabilities'])) {
            return [];
        }

        $statuses = [];
        foreach ($result[0]['item_availabilities'] as $i => $item) {
            $avail = $item['availability'];
            $available = $avail['available'];
            $statusCodes = $this->getItemStatusCodes($item);
            $status = $this->pickStatus($statusCodes);
            if (isset($avail['unavailabilities']['Item::CheckedOut']['date_due'])) {
                $duedate = $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP',
                    $avail['unavailabilities']['Item::CheckedOut']['date_due']
                );
            } else {
                $duedate = null;
            }

            $entry = [
                'id' => $id,
                'item_id' => $item['itemnumber'],
                'location' => $this->getItemLocationName($item),
                'availability' => $available,
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $item['itemcallnumber'],
                'duedate' => $duedate,
                'number' => $item['enumchron'],
                'barcode' => $item['barcode'],
                'sort' => $i,
                'requests_placed' => max(
                    [$item['hold_queue_length'], $result[0]['hold_queue_length']]
                )
            ];
            if (!empty($item['itemnotes'])) {
                $entry['item_notes'] = [$item['itemnotes']];
            }

            if ($patron && $this->itemHoldAllowed($item)) {
                $entry['is_holdable'] = true;
                $entry['level'] = 'copy';
                $entry['addLink'] = 'check';
            } else {
                $entry['is_holdable'] = false;
            }

            $statuses[] = $entry;
        }

        usort($statuses, [$this, 'statusSortFunction']);
        return $statuses;
    }

    /**
     * Get statuses for an item
     *
     * @param array $item Item from Koha
     *
     * @return array Status array and possible due date
     */
    protected function getItemStatusCodes($item)
    {
        $statuses = [];
        if ($item['availability']['available']) {
            $statuses[] = 'On Shelf';
        } elseif (isset($item['availability']['unavailabilities'])) {
            foreach ($item['availability']['unavailabilities'] as $key => $reason) {
                if (isset($this->config['ItemStatusMappings'][$key])) {
                    $statuses[] = $this->config['ItemStatusMappings'][$key];
                } elseif (strncmp($key, 'Item::', 6) == 0) {
                    $status = substr($key, 6);
                    switch ($status) {
                    case 'CheckedOut':
                        $overdue = false;
                        if (!empty($reason['date_due'])) {
                            $duedate = $this->dateConverter->convert(
                                'Y-m-d',
                                'U',
                                $reason['date_due']
                            );
                            $overdue = $duedate < time();
                        }
                        $statuses[] = $overdue ? 'Overdue' : 'Charged';
                        break;
                    case 'Lost':
                        $statuses[] = 'Lost--Library Applied';
                        break;
                    case 'NotForLoan':
                    case 'NotForLoanForcing':
                        if (isset($reason['code'])) {
                            switch ($reason['code']) {
                            case 'Not For Loan':
                                $statuses[] = 'On Reference Desk';
                                break;
                            default:
                                $statuses[] = $reason['code'];
                                break;
                            }
                        } else {
                            $statuses[] = 'On Reference Desk';
                        }
                        break;
                    case 'Transfer':
                        $onHold = false;
                        if (!empty($item['availability']['notes'])) {
                            foreach ($item['availability']['notes'] as $noteKey
                                => $note
                            ) {
                                if ('Item::Held' === $noteKey) {
                                    $onHold = true;
                                    break;
                                }
                            }
                        }
                        $statuses[] = $onHold ? 'In Transit On Hold' : 'In Transit';
                        break;
                    case 'Held':
                        $statuses[] = 'On Hold';
                        break;
                    case 'Waiting':
                        $statuses[] = 'On Holdshelf';
                        break;
                    default:
                        $statuses[] = !empty($reason['code'])
                            ? $reason['code'] : $status;
                    }
                }
            }
            if (empty($statuses)) {
                $statuses[] = 'Not Available';
            }
        } else {
            $this->logger->err(
                "Unable to determine status for item: " . print_r($item, true)
            );
        }

        if (empty($statuses)) {
            $statuses[] = 'No information available';
        }
        return array_unique($statuses);
    }

    /**
     * Status item sort function
     *
     * @param array $a First status record to compare
     * @param array $b Second status record to compare
     *
     * @return int
     */
    protected function statusSortFunction($a, $b)
    {
        $result = strcmp($a['location'], $b['location']);
        if ($result == 0) {
            $result = $a['sort'] - $b['sort'];
        }
        return $result;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item from Koha
     *
     * @return bool
     */
    protected function itemHoldAllowed($item)
    {
        $unavail = isset($item['availability']['unavailabilities'])
            ? $item['availability']['unavailabilities'] : [];
        if (!isset($unavail['Hold::NotHoldable'])) {
            return true;
        }
        return false;
    }

    /**
     * Protected support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @throws ILSException
     * @return string            The best status message to display.
     */
    protected function pickStatus($statusArray)
    {
        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->getStatusRanking($status);
        for ($x = 1; $x < count($statusArray); $x++) {
            if ($this->getStatusRanking($statusArray[$x]) < $rank) {
                $status = $statusArray[$x];
            }
        }

        return $status;
    }

    /**
     * Support method for pickStatus() -- get the ranking value of the specified
     * status message.
     *
     * @param string $status Status message to look up
     *
     * @return int
     */
    protected function getStatusRanking($status)
    {
        return isset($this->statusRankings[$status])
            ? $this->statusRankings[$status] : 32000;
    }

    /**
     * Get patron's blocks, if any
     *
     * @param array $patron Patron
     *
     * @return mixed        A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function getPatronBlocks($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'status'],
            __FUNCTION__,
            [],
            'GET',
            $patron
        );
        $blockReason = [];
        if (!empty($result['blocks'])) {
            $blockReason[] = $this->translate('Borrowing Block Message');
            foreach ($result['blocks'] as $reason => $details) {
                $params = [];
                if (($reason == 'Patron::Debt'
                        || $reason == 'Patron::DebtGuarantees')
                    && !empty($details['current_outstanding'])
                    && !empty($details['max_outstanding'])
                ) {
                    $params = [
                        '%%blockCount%%' => $details['current_outstanding'],
                        '%%blockLimit%%' => $details['max_outstanding']
                    ];
                }
                $reason = 'Borrowing Block Koha Reason '
                    . str_replace('::', '_', $reason);
                $translated = $this->translate($reason, $params);
                if ($reason !== $translated) {
                    $reason = $translated;
                    $blockReason[] = $reason;
                }
            }
        }
        return empty($blockReason) ? false : $blockReason;
    }

    /**
     * Fetch an item record from Koha
     *
     * @param int $id Item id
     *
     * @return array|null
     */
    protected function getItem($id) //TODO do it or not
    {
        static $cachedRecord = [];
        if (!isset($cachedRecords[$id])) {
            $cachedRecords[$id] = $this->makeRequest(['v1', 'items', $id]);
        }
        return $cachedRecords[$id];
    }

    /**
     * Fetch a bib record from Koha
     *
     * @param int $id Bib record id
     *
     * @return array|null
     */
    protected function getBibRecord($id)
    {
        static $cachedRecords = [];
        if (!isset($cachedRecords[$id])) {
            $cachedRecords[$id] = $this->makeRequest(['v1', 'biblios', $id]);
        }
        return $cachedRecords[$id];
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a hold error message
     *
     * @param int   $code   HTTP Result Code
     * @param array $result API Response
     *
     * @return array
     */
    protected function holdError($code, $result)
    {
        $message = isset($result['error']) ? $result['error'] : 'hold_error_fail';
        return [
            'success' => false,
            'sysMessage' => $message
        ];
    }

    /**
     * Map a Koha renewal block reason code to a VuFind translation string
     *
     * @param string $reason Koha block code
     *
     * @return string
     */
    protected function mapRenewalBlockReason($reason)
    {
        return isset($this->renewalBlockMappings[$reason])
            ? $this->renewalBlockMappings[$reason] : 'renew_denied';
    }

    /**
     * Return a location for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemLocationName($item)
    {
        $branchId = null !== $item['holdingbranch'] ? $item['holdingbranch']
            : $item['homebranch'];
        $name = $this->translate("location_$branchId");
        if ($name === "location_$branchId") {
            $result = $this->makeRequest(
                ['v1', 'libraries'], __FUNCTION__,false, 'GET'
            );
            $branches = [];
            foreach ($result as $branch) {
                $branches[$branch['branchcode']] = $branch['branchname'];
            }
            $name = isset($branches[$branchId]) ? $branches[$branchId] : $branchId;
        }
        return $name;
    }

    /**
     * Get a reason for why a hold cannot be placed
     *
     * @param array $result Hold check result
     *
     * @return string
     */
    protected function getHoldBlockReason($result)
    {
        if (!empty($result[0]['availability']['unavailabilities'])) {
            foreach ($result[0]['availability']['unavailabilities']
                as $key => $reason
            ) {
                switch ($key) {
                case 'Biblio::NoAvailableItems':
                    return 'hold_error_not_holdable';
                case 'Item::NotForLoan':
                case 'Hold::NotAllowedInOPAC':
                case 'Hold::ZeroHoldsAllowed':
                case 'Hold::NotAllowedByLibrary':
                case 'Hold::NotAllowedFromOtherLibraries':
                case 'Item::Restricted':
                case 'Hold::ItemLevelHoldNotAllowed':
                    return 'hold_error_item_not_holdable';
                case 'Hold::MaximumHoldsForRecordReached':
                case 'Hold::MaximumHoldsReached':
                    return 'hold_error_too_many_holds';
                case 'Item::AlreadyHeldForThisPatron':
                    return 'hold_error_already_held';
                case 'Hold::OnShelfNotAllowed':
                    return 'hold_error_on_shelf_blocked';
                }
            }
        }
        return 'hold_error_blocked';
    }

    /**
     * Normalizes response from API to needed format
     *
     * @param $method
     * @return KohaRestNormalizer
     */
    public function normalizeResponse($method) {
        return new KohaRestNormalizer($method, $this->dateConverter, $this->translator, $this->logger);
    }
}