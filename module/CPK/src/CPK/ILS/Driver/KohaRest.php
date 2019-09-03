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
use \VuFind\Date\Converter as DateConverter;

/**
 * VuFind Driver for Koha, using REST API
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends AbstractBase implements \Zend\Log\LoggerAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface, CPKDriverInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    /**
     * Library prefix
     *
     * @var string
     */
    protected $source = '';

    protected $dateConverter;
    protected $defaultPickUpLocation;
    protected $kohaRestService;

    /**
     * Normalizer
     *
     * @var KohaRestNormalizer
     */
    protected $normalizer;

    /**
     * Item statuses mapping
     *
     * @var array
     */
    protected $statuses = [
        'checked_out' => 'On Loan',
        'in_transit' => 'In Transit Between Library Locations',
        'waiting_hold' => 'On Order',
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

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter
     * @param \CPK\Auth\KohaRestService $kohaRestService Koha API authentication service
     */
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

        if (isset($this->config['Availability']['source']))
            $this->source = $this->config['Availability']['source'];

        $this->kohaRestService->setConfig($this->config);
        $this->kohaRestService->setSource($this->source);

        $this->normalizer = new KohaRestNormalizer($this->dateConverter);
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
            'cat_username' => $patron['userid'],
            'id' => $patron['patron_id'],
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'city' => $result['city'],
            'country' => $result['country'],
            'zip' => $result['postal_code'],
            'phone' => $result['phone'],
            'group' => $result['category_id'],
            'blocks' => $result['restricted'],
            'email' => $result['email'],
            'expire' => $result['expiry_date'],
            'expiration_date' => $result['expiry_date'], // For future compatibility with VuFind 6+
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
            $renewability = $this->getCheckoutRenewability($entry['checkout_id']);
            $item = null;
            //$biblio = null;
            if (isset($entry['item_id'])) {
                $item = $this->getItem($entry['item_id']);
                /* FIXME need biblio administrative data endpoint if (isset($item['biblio_id'])) {
                    $biblio = $this->getBiblioRecord($item['biblio_id']);
                }*/
            }
            $transactions[] = [
                'id' => $item['biblio_id'] ?? null,
                'loan_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'duedate' => $entry['due_date'],
                //'dueStatus' => $entry['due_status'],
                'renew' => $entry['renewals'],
                'barcode' => $item['barcode'] ?? null,
                'renewable' => $renewability['allows_renewal'] ?? false,
                'renewLimit' => $renewability['max_renewals'] ?? null,
                'message' => $renewability['error'] ?? null,
                'borrowingLocation' => $entry['library_id'],
                // publication_year => $biblio[''],
                // title => $biblio[''],
            ];
        }

        return $transactions;
    }

    /**
     * Checks if item is renewable
     *
     * @param $checkoutId
     * @return bool
     */
    public function getCheckoutRenewability($checkoutId) {
        $result = $this->makeRequest(
            ['v1', 'checkouts', $checkoutId, 'allows_renewal'],
            __FUNCTION__,
            [],
            'GET'
        );
        return $result;
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
            $sortKey = 'checkout_date';
        } elseif ($sort[0] == 'return') {
            $sortKey = 'returndate';
        } else {
            $sortKey = 'checkin_date';
        }
        $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'desc' : 'asc';

        $pageSize = isset($params['limit']) ? $params['limit'] : 50;
        $queryParams = [
            'sort' => $sortKey,
            'order' => $direction,
            'offset' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'limit' => $pageSize
        ];

        $transactions = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'checkouts'],
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
                $item = $this->getItem($entry['item_id']);
            } catch (\Exception $e) {
                $item = [];
            }
            $volume = isset($item['serial_enum_chron'])
                ? $item['serial_enum_chron'] : '';
            $title = '';
            if (!empty($item['biblio_id'])) {
                $bib = $this->getBiblioRecord($item['biblio_id']);
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
                'id' => $item['biblio_id'] ?? '',
                'checkout_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'title' => $title,
                'volume' => $volume,
                'checkoutdate' => $this->normalizer->normalizeDate($entry['checkout_date'], true),
                'duedate' => $this->normalizer->normalizeDate($entry['date_due'], true),
                'dueStatus' => $dueStatus,
                'returndate' => $this->normalizer->normalizeDate($entry['checkin_date'], true),
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
            ['patron_id' => $patron['id']],
            'GET',
            $patron
        );

        $holds = [];
        if (!isset($result)) {
            return $holds;
        }
        foreach ($result as $entry) {
            $holds[] = [
                'id' => $entry['biblio_id'],
                'item_id' => $entry['item_id'] ?? null,
                'location' => $entry['pickup_library_id'] ?? null,
                'create' => $entry['hold_date'],
                'expire' => $entry['expiration_date'],
                'position' => $entry['priority'],
                'available' => !empty($entry['waiting_date']),
                'requestId' => $entry['hold_id'],
                'in_transit' => !empty($entry['status']) && $entry['status'] == 'T',
                'barcode' => $entry['item_id'] ?? ''
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
                || in_array($location['library_id'], $excluded)
            ) {
                continue;
            }
            $locations[] = [
                'locationID' => $location['library_id'],
                'locationDisplay' => $location['name']
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
                ['v1', 'contrib', 'knihovny_cz', 'biblios', $id, 'alows_hold'],
                __FUNCTION__,
                ['patron_id' => $patron['id'], 'library_id' => $this->getDefaultPickUpLocation($patron)],
                'GET',
                $patron
            );
            if (!empty($result['allows_hold']) && $result['allows_hold'] == true) {
                return [
                    'valid' => true,
                    'status' => 'title_hold_place'
                ];
            }
            return [
                'valid' => false,
                'status' => $this->getHoldBlockReason($result) //FIXME
            ];
        }
        $result = $this->makeRequest(
            ['v1', 'contrib', 'knihovny_cz', 'items', $data['item_id'], 'allows_hold'],
            __FUNCTION__,
            ['patron_id' => $patron['id'], 'library_id' => $this->getDefaultPickUpLocation($patron)],
            'GET',
            $patron
        );
        if (!empty($result['allows_hold']) && $result['allows_hold'] == true) {
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
            'biblio_id' => (int)$bibId,
            'patron_id' => (int)$patron['id'],
            'pickup_library_id' => $pickUpLocation,
            'expiration_date' => $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            )
        ];
        if ($level == 'copy') {
            $request['item_id'] = (int)$itemId;
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
                'fine' =>  $this->translate("KohaFine_" . $entry['account_type']),
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
                $client->setParameterPost($params);
            }
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError(
                "$method request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            $this->kohaRestService->invalidateToken();
            $client = $this->kohaRestService->createOAUTH2Client($apiUrl);

            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->logError(
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
        $this->debug(
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
            $this->logError(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        $decodedResult = $this->normalizer->normalize($decodedResult, $action);

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
        $availabilities = $this->makeRequest(
            ['v1', 'contrib', 'knihovny_cz', 'biblios', $id, 'allows_checkout'],
            __FUNCTION__,
            [],
            'GET',
            $patron,
            false,
            false
        );

        $holdable = 'Y';
        if ($patron) {
            $holdability = $this->makeRequest(
                ['v1', 'contrib', 'knihovny_cz', 'biblios', $id, 'allows_hold'],
                __FUNCTION__,
                ['patron_id' => $patron['id'], 'library_id' => $this->getDefaultPickUpLocation($patron) ],
                'GET',
                $patron,
                false,
                false
            );
            if ($holdability && $holdability['allows_hold'] == false) {
                $holdable = 'N';
            }
        }

        $items = $this->makeRequest(
            ['v1', 'contrib', 'bibliocommons', 'biblios', $id, 'items'],
            __FUNCTION__,
            [],
            'GET',
            $patron,
            false,
            true
        );

        $holds = $this->makeRequest(
            ['v1', 'holds'],
            __FUNCTION__,
            ['biblio_id' => $id],
            'GET',
            $patron,
            false,
            true
        );

        foreach ($items as $item) {
            $available = $availabilities[$item['item_id']]['allows_checkout'] ??  false;
            $status = $this->statuses[$availabilities[$item['item_id']]['allows_checkout_status']] ?? 'available';
            $duedate = $availabilities[$item['item_id']]['date_due'] ?? null;
            $entry = [
                'id' => $id,
                'item_id' => $item['item_id'],
                'department' => $this->getItemLocationName($item),
                'location' => $item['location'],
                'availability' => $item['notforloan'] ? 'P' : 'A',
                'status' => $status,
                'reserve' => count($holds) >= 1 ? 'Y' : 'N',
                'callnumber' => $item['callnumber'],
                'duedate' => $duedate,
                'number' => $item['serial_enum_chron'],
                'barcode' => $item['barcode'],
            ];
            if (!empty($item['public_notes'])) {
                $entry['item_notes'] = [$item['public_notes']];
            }

            if ($holdable) {
                $entry['is_holdable'] = true;
                $entry['level'] = 'copy';
                $entry['addLink'] = 'check';
            } else {
                $entry['is_holdable'] = false;
            }
            $statuses[] = $entry;
        }
        return $statuses;
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
            ['v1', 'patrons', $patron['id'], 'status'], //FIXME need endpoint
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
    protected function getBiblioRecord($id)
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
        $library_id = $item['holding_library'] ?? $item['home_library'];
        $name = $this->translate("location_$library_id");
        if ($name === "location_$library_id") {
            $result = $this->makeRequest(
                ['v1', 'libraries'], __FUNCTION__,false, 'GET'
            );
            $libraries = [];
            foreach ($result as $library) {
                $libraries[$library['library_id']] = $library['name'];
            }
            $name = $libraries[$library_id] ?? $library_id;
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
     * Gets the contact person for this driver instance.
     *
     * @return string
     */
    public function getAdministratorEmail()
    {
        return $this->config['Catalog']['contactPerson'] ?? null;
    }
}
