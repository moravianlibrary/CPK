<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;

use VuFind\Exception\ILS as ILSException, DOMDocument;

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind2
 * @package ILS_Drivers
 * @author Matus Sabik <sabik@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *          License
 * @link http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class XCNCIP2 extends \VuFind\ILS\Driver\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface, \VuFind\I18n\Translator\TranslatorAwareInterface, CPKDriverInterface
{

    const AGENCY_ID_DELIMITER = ':';
    const CANCEL_REQUEST_PREFIX = 'req';

    protected $maximumItemsCount = null;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * @var NCIPRequests
     */
    protected $requests = null;

    protected $cannotUseLUIS = false;

    protected $hideHoldLinks = false;

    protected $hasUntrustedSSL = false;

    protected $cacert = null;

    protected $timeout = null;

    protected $logo = null;

    protected $agency = '';
    protected $source = '';

    protected $translator = false;

    protected $libsLikeTabor = null;
    protected $libsLikeLiberec = null;

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service
     *            HTTP service
     *
     * @return void
     */
    public function setHttpService(\VuFindHttp\HttpServiceInterface $service)
    {
        $this->httpService = $service;
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
        if (empty($this->config)) {
            $message = 'Configuration needs to be set.';

            $this->addEnviromentalException($message);
            throw new ILSException($message);
        }

        $maximumItemsCount = $this->config['Catalog']['maximumItemsCount'];

        if (! empty($maximumItemsCount))
            $this->maximumItemsCount = intval($maximumItemsCount);
        else
            $this->maximumItemsCount = 20;

        if (isset($this->config['Catalog']['cannotUseLUIS']) &&
             $this->config['Catalog']['cannotUseLUIS'])
            $this->cannotUseLUIS = true;

        if (! empty($this->config['Catalog']['hideHoldLinks']))
            $this->hideHoldLinks = true;

        if (isset($this->config['Catalog']['hasUntrustedSSL']) &&
             $this->config['Catalog']['hasUntrustedSSL'])
            $this->hasUntrustedSSL = true;

        if (isset($this->config['Catalog']['cacert']))
            $this->cacert = $this->config['Catalog']['cacert'];

        if (isset($this->config['Catalog']['timeout']))
            $this->timeout = intval($this->config['Catalog']['timeout']);

        if (isset($this->config['Catalog']['logo']))
            $this->logo = $this->config['Catalog']['logo'];

        if (isset($this->config['Catalog']['agency']))
            $this->agency = $this->config['Catalog']['agency'];

        if (isset($this->config['Availability']['source']))
            $this->source = $this->config['Availability']['source'];

        $this->requests = new NCIPRequests($this->config);
        $this->libsLikeTabor = $this->requests->getLibsLikeTabor();
        $this->libsLikeLiberec = $this->requests->getLibsLikeLiberec();
    }

    public function setTranslator(\Zend\I18n\Translator\TranslatorInterface $translator)
    {
        $this->translator = $translator->getTranslator();
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml
     *            XML request document
     *
     * @return \SimpleXMLElement parsed from response
     */
    protected function sendRequest($xml)
    {
        // Make the NCIP request:
        try {
            $client = $this->httpService->createClient(
                $this->config['Catalog']['url']);
            $client->setRawBody($xml);
            $client->setEncType('application/xml; "charset=utf-8"');
            $client->setMethod('POST');
            $client->setHeaders(array(
                'Content-Type' => 'application/xml'
            ));

            if (isset($this->timeout))
                $client->setOptions(
                    array(
                        'timeout' => $this->timeout
                    ));

            if ((! empty($this->config['Catalog']['username'])) &&
                    (! empty($this->config['Catalog']['password']))) {

                $user = $this->config['Catalog']['username'];
                $password = $this->config['Catalog']['password'];
                $client->setAuth($user, $password);
            }

            if ($this->hasUntrustedSSL) {
                // Do not verify SSL certificate
                $client->setOptions(
                    array(
                        'adapter' => 'Zend\Http\Client\Adapter\Curl',
                        'curloptions' => array(
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_SSL_VERIFYPEER => false
                        )
                    ));
            } elseif (isset($this->cacert)) {
                $client->setOptions(
                    array(
                        'adapter' => 'Zend\Http\Client\Adapter\Curl',
                        'curloptions' => array(
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_CAINFO => $this->cacert
                        )
                    ));
            }

            $result = $client->send();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $this->addEnviromentalException($message);
            throw new ILSException($message);
        }

        if (! $result->isSuccess()) {
            $message = 'HTTP error: ' . $result->getStatusCode();

            $this->addEnviromentalException($message);
            throw new ILSException($message);
        }

        // Process the NCIP response:
        $body = $result->getBody();
        $response = @simplexml_load_string($body);

        if (! is_a($response, 'SimpleXMLElement')) {
            $message = "Problem parsing XML";

            $this->addEnviromentalException($message);
            throw new ILSException($message);
        }
        $response->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');

        if ($problem = $this->getProblem($response)) {
            // TODO chcek problem type

            $message = 'Problem recieved in XCNCIP2 Driver. Content:' .
                 str_replace('\n', '<br/>', $problem);

            //$this->addEnviromentalException($message); TODO must be moved to particular method

            throw new ILSException($message);
        }
        return $response;
    }

    /**
     * Cancel Holds
     *
     * Cancels a list of holds for a specific patron.
     *
     * @param array $cancelDetails
     *            - array with two keys: patron (array from patronLogin method) and
     *            details (an array of strings returned by the driver's getCancelHoldDetails method)
     *
     * @throws ILSException
     * @return array Status of canceled holds.
     */
    public function cancelHolds($cancelDetails)
    {
        $patron = $cancelDetails['patron'];
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $holds = $this->getMyHolds($patron);
        $items = array();

        $problemValue = null;

        $desiredRequestFound = $problemOccurred = false;

        foreach ($cancelDetails['details'] as $recent) {
            foreach ($holds as $hold) {
                if ($hold['item_id'] == $recent) { // Item-leveled cancel request

                    $desiredRequestFound = true;

                    $request = $this->requests->cancelRequestItemUsingItemId($patron,
                        $recent);
                    try {
                        $response = $this->sendRequest($request);
                    } catch (ILSException $e) {
                        $problemOccurred = true;
                        $problemValue = $e->getMessage();
                    }

                    break;

                } else
                    if ($hold['reqnum'] == $recent) { // Biblio-leveled cancel request

                        $request_id = substr($hold['reqnum'], strlen(static::CANCEL_REQUEST_PREFIX));

                        if (! $request_id > 0) {
                            $message = 'XCNCIP2 Driver cannot cancel biblio-leveled request without request id!';

                            $this->addEnviromentalException($message);
                            throw new ILSException($message);
                        }

                        $desiredRequestFound = true;

                        $request = $this->requests->cancelRequestItemUsingRequestId(
                            $patron, $request_id);
                        try {
                            $response = $this->sendRequest($request);
                        } catch (ILSException $e) {
                            $problemOccurred = true;
                            $problemValue = $e->getMessage();
                        }

                        break;
                    }
            }

            $didWeTheJob = $desiredRequestFound && ! $problemOccurred;
            if (! $didWeTheJob) $this->addEnviromentalException("Item has not been canceled.");

            $items[$recent] = array(
                'success' => $didWeTheJob,
                'status' => '',
                'sysMessage' => isset($problemValue) ? $problemValue : ''
            );
        }

        $retVal = array(
            'count' => count($items),
            'items' => $items
        );

        return $retVal;
    }

    /**
     * Renew My Items
     *
     * his method renews a list of items for a specific patron.
     *
     * @param array $renewDetails
     *            Two keys patron and details.
     *
     * @throws ILSException
     * @return array An associative array with two keys: blocks and details.
     */
    public function renewMyItems($renewDetails)
    {
        $patron = $renewDetails['patron'];
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $result = array();
        foreach ($renewDetails['details'] as $item) {
            try {
                $request = $this->requests->renewItem($patron, $item);
                $response = $this->sendRequest($request);
                $rawDate = $this->useXPath($response, 'RenewItemResponse/DateForReturn');
                $date = $this->parseDate($rawDate);
                $result[$item] = array(
                    'success' => true,
                    'new_date' => $date,
                    'new_time' => $date, // used the same like previous
                    'item_id' => $item,
                    'sysMessage' => ''
                );
            } catch (ILSException $e) {
                $result[$item] = array(
                    'success' => false,
                    'sysMessage' => 'renew_fail'
                );
            }
        }
        return array(
            'blocks' => false,
            'details' => $result
        );
    }

    public function getPaymentURL()
    {
        if (isset($this->config['paymentUrl']))
            return $this->config['paymentUrl'];

        return null;
    }

    /**
     * Get Renew Details
     *
     * This method returns a string to use as the input form value for renewing each hold item.
     *
     * @param array $checkOutDetails
     *            - One of the individual item arrays returned by the getMyTransactions method.
     *
     * @throws ILSException
     * @return string Used as the input form value for renewing each item;
     *         you can pass any data that is needed by your ILS to identify the transaction to renew –
     *         the output of this method will be used as part of the input to the renewMyItems method.
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['id'];
    }

    /**
     * Get Cancel Hold Details
     *
     * This method returns a string to use as the input form value for cancelling each hold item.
     *
     * @param array $holdDetails
     *            - One of the individual item arrays returned by the getMyHolds method.
     *
     * @throws ILSException
     * @return string Used as the input value for cancelling each hold item;
     *         any data needed to identify the hold –
     *         the output will be used as part of the input to the cancelHolds method.
     */
    public function getCancelHoldDetails($holdDetails)
    {
        if (strpos($holdDetails['item_id'], '.') != null) {
            $holdDetails['item_id'] = substr($holdDetails['item_id'], strpos($holdDetails['item_id'], '.') + 1); // strip prefix
        }
        if ($holdDetails['item_id'] == 'N/A') $holdDetails['item_id'] = '';
        return empty($holdDetails['reqnum']) ? $holdDetails['item_id'] : $holdDetails['reqnum'];
    }

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param array $current
     *            Current XCItemAvailability chunk.
     *            array $bibinfo Information about record.
     *
     * @return array
     */
    protected function getHoldingsForChunk($current, $bibinfo)
    {
        // Extract details from the XML:
        $status = (string) $this->useXPath($current,
            'ItemOptionalFields/CirculationStatus')[0];

        $id = (string) $this->useXPath($bibinfo,
            'BibliographicId/BibliographicItemId/BibliographicItemIdentifier')[0];
        $itemIdentifierCode = (string) $this->useXPath($current,
            'ItemId/ItemIdentifierType')[0];

        $parsingLoans = $this->useXPath($current, 'LoanedItem') != null;

        if ($itemIdentifierCode == 'Accession Number') {

            $item_id = (string) $this->useXPath($current,
                'ItemId/ItemIdentifierValue')[0];
        }

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):

        $locationNameInstance = $this->useXPath($current,
            'ItemOptionalFields/Location/LocationName/LocationNameInstance');

        foreach ($locationNameInstance as $recent) {
            // FIXME: Create config to map location abbreviations of each institute into human readable values

            $locationLevel = (string) $this->useXPath($recent, 'LocationNameLevel')[0];

            if ($locationLevel == 4) {
                $department = (string) $this->useXPath($recent, 'LocationNameValue')[0];
            } else
                if ($locationLevel == 3) {
                    $sublibrary = (string) $this->useXPath($recent,
                        'LocationNameValue')[0];
                } else {
                    $locationInBuilding = (string) $this->useXPath($recent,
                        'LocationNameValue')[0];
                }
        }

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        // $holdCallNo = $this->useXPath($current, 'HoldingsSet/CallNumber');
        $itemCallNo = (string) $this->useXPath($current,
            'ItemOptionalFields/ItemDescription/CallNumber')[0];
        // $itemCallNo = (string)$itemCallNo[0];

        $bibliographicItemIdentifierCode = (string) $this->useXPath($current,
            'ItemOptionalFields/BibliographicDescription/BibliographicRecordId/BibliographicRecordIdentifierCode')[0];

        if ($bibliographicItemIdentifierCode == 'Legal Deposit Number') {
            $barcode = (string) $this->useXPath($current,
                'ItemOptionalFields/BibliographicDescription/BibliographicRecordId/BibliographicRecordIdentifier')[0];
        }

        $numberOfPieces = (string) $this->useXPath($current,
            'ItemOptionalFields/ItemDescription/NumberOfPieces')[0];

        $holdQueue = (string) $this->useXPath($current,
            'ItemOptionalFields/HoldQueueLength')[0];

        $itemRestriction = (string) $this->useXPath($current,
            'ItemOptionalFields/ItemUseRestrictionType')[0];

        $available = $status === 'Available On Shelf';

        // TODO Exists any clean way to get the due date without additional request?

        if (! empty($locationInBuilding))
            $onStock = substr($locationInBuilding, 0, 5) == 'Stock';
        else
            $onStock = false;

        $onStock = true;

        $restrictedToLibrary = ($itemRestriction == 'In Library Use Only');

        $monthLoanPeriod = ($itemRestriction ==
             'Limited Circulation, Normal Loan Period') || empty($itemRestriction);

        return array(
            'id' => empty($id) ? "" : $id,
            'availability' => empty($available) ? false : $available ? true : false,
            'status' => empty($status) ? "" : $status,
            'location' => empty($locationInBuilding) ? "" : $locationInBuilding,
            'sub_lib_desc' => empty($sublibrary) ? '' : $sublibrary,
            'department' => empty($department) ? '' : $department,
            'reserve' => "",
            'callnumber' => "",
            'collection_desc' => "",
            'duedate' => empty($dueDate) ? "" : $dueDate,
            'returnDate' => false,
            'number' => empty($numberOfPieces) ? "" : $numberOfPieces,
            'requests_placed' => empty($holdQueue) ? "" : $holdQueue,
            'barcode' => empty($barcode) ? "" : $barcode,
            'notes' => "",
            'summary' => "",
            'supplements' => "",
            'indexes' => "",
            'is_holdable' => "",
            'holdtype' => "",
            'addLink' => "",
            'link' => $link,
            'item_id' => empty($item_id) ? "" : $item_id,
            'holdOverride' => "",
            'addStorageRetrievalRequestLink' => "",
            'addILLRequestLink' => ""
        );
    }

    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $status = true;
        $message = '';
        $request = $this->requests->requestItem($patron, $holdDetails);
        try {
            $response = $this->sendRequest($request);
        } catch (ILSException $ex) {
            $status = false;
            $message = 'hold_error_fail';
        }
        return array(
            'success' => $status,
            'sysMessage' => $message,
            'source' => $patron['agency'],
        );
    }

    public function getConfig($func)
    {
        if ($func == "Holds") {
            if (isset($this->config['Holds'])) {
                return $this->config['Holds'];
            }
            return array(
                "HMACKeys" => "id:item_id",
                "extraHoldFields" => "comments:requiredByDate",
                "defaultRequiredDate" => "0:1:0"
            );
        }
        if ($func == "ILLRequests") {
            return array(
                "HMACKeys" => "id:item_id"
            );
        } else {
            return array();
        }
    }

    public function getPickUpLocations($patron = null, $holdInformation = null)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->lookupAgency($patron);
        $response = $this->sendRequest($request);

        $retVal = array();
        if ($this->agency === 'ABG001') { // mkp
            $retVal = $this->parseLocationsMkp($response);
        }
        if ($this->agency === 'UOG505') { // tre
            $retVal = $this->parseLocationsKoha($response);
        }
        return $retVal;
    }

    private function parseLocationsMkp($response)
    {
        $retVal = array();
        $locations = $this->useXPath($response, 'LookupAgencyResponse/Ext/LocationName/LocationNameInstance');
        foreach ($locations as $location) {
            $id = $this->useXPath($location, 'LocationNameLevel');
            $name = $this->useXPath($location, 'LocationNameValue');
            $address = $this->useXPath($location,
                    'Ext/PhysicalAddress/UnstructuredAddress/UnstructuredAddressData');
            if (empty($id)) continue;

            $retVal[] = array(
                'locationID' => empty($id) ? '' : (string) $id[0],
                'locationDisplay' => empty($name) ? '' : (string) $name[0],
                'locationAddress' => empty($address) ? '' : (string) $address[0]
            );
        }
        return $retVal;
    }

    private function parseLocationsKoha($response)
    {
        $retVal = array();
        $locations = $this->useXPath($response, 'LookupAgencyResponse/AgencyAddressInformation');
        foreach ($locations as $location) {
            $id = $this->useXPath($location, 'AgencyAddressRoleType');
            $name = $this->useXPath($location, 'PhysicalAddress/UnstructuredAddress/UnstructuredAddressType');
            $address = $this->useXPath($location,
                    'PhysicalAddress/UnstructuredAddress/UnstructuredAddressData');
            if (empty($id)) continue;
            if (! empty($name) && (string) $name[0] == 'Newline-Delimited Text') continue;

            $retVal[] = array(
                'locationID' => empty($id) ? '' : (string) $id[0],
                'locationDisplay' => empty($name) ? '' : (string) $name[0],
                'locationAddress' => empty($address) ? '' : (string) $address[0]
            );
        }
        return $retVal;
    }

    public function getDefaultPickUpLocation($patron = null, $holdInformation = null)
    {
        return false;
    }

    public function getMyHistory($patron, $currentLimit = 0)
    {
        return [];
    }

    public function getMyHistoryPage($patron, $page, $perPage)
    {
        if ($this->agency != 'UOG505') {
            throw new ILSException('driver_no_history');
        }

        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronHistory($patron, $page, $perPage);
        $response = $this->sendRequest($request);

        $totalPages = $this->useXPath($response, 'LookupUserResponse/Ext/LoanedItemsHistory/LastPage');
        $items = $this->useXPath($response, 'LookupUserResponse/Ext/LoanedItemsHistory/LoanedItem');
        $inc = 0;

        foreach ($items as $item) {

            $inc++;
            $title = $this->useXPath($item, 'Title');
            $itemId = $this->useXPath($item, 'ItemId/ItemIdentifierValue');
            $dueDate = $this->useXPath($item, 'DateDue');
            $dueDate = $this->parseDate($dueDate);

            $additRequest = $this->requests->lookupItem((string) $itemId[0], $patron);
            try {
                $additResponse = $this->sendRequest($additRequest);
                $bib_id = $this->getFirstXPathMatchAsString($additResponse,
                        'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
                if (empty($title)) {
                    $title = $this->useXPath($additResponse, 'LookupItemResponse/ItemOptionalFields/BibliographicDescription/Title');
                }
            } catch (ILSException $e) {
            }

            $historyPage[] = array(
                'id' => empty($bib_id) ? '' : $bib_id,
                'item_id' => empty($itemId) ? '' : (string) $itemId[0],
                'barcode' => '',
                'title' => empty($title) ? $this->translator->translate('unknown_title') : (string) $title[0],
                'author' => '',
                'reqnum' => '',
                'loandate' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'returned' => '',
                'publicationYear' => '',
                'rowNo' => ($page - 1) * $perPage + $inc,
            );
        }

        return [
            'historyPage' => empty($historyPage) ? [] : $historyPage,
            'totalPages' => empty($totalPages) ? 0 : (int) $totalPages[0],
        ];
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
     *         id, availability (boolean), status, location, reserve,
     *         callnumber.
     */
    public function getStatus($id, $patron = [])
    {
        // id may have the form of "patronId:agencyId"
        list ($id, $agencyId) = $this->splitAgencyId($id);

        $request = $this->requests->lookupItem($id, $patron);
        try {
            $response = $this->sendRequest($request);
        }
        catch (ILSException $e) {
            return array(
                'item_id' => $id,
                'usedGetStatus' => true
            );
        }
        if ($response == null)
            return null;

            // Merge it back if the agency was specified before ..
        $id = $this->joinAgencyId($id, $agencyId);

        // Extract details from the XML:
        $status = $this->useXPath($response, 'LookupItemResponse/ItemOptionalFields/CirculationStatus');
        $status = $this->convertStatus($status);

        $locations = $this->useXPath($response, 'LookupItemResponse/ItemOptionalFields/Location');
        foreach ($locations as $locElement) {
            $level = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameLevel');
            $locationName = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameValue');
            if (! empty($level)) {
                if ((string) $level[0] == '1') if (! empty($locationName)) $department = (string) $locationName[0];
                if ((string) $level[0] == '2') if (! empty($locationName)) $collection = (string) $locationName[0];
            }
        }

        if ($this->agency === 'ABA008') { // NLK
            $parts = explode("@", $department);
            $translate = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
            $parts = explode(" ", $translate, 2);
            $department = isset($parts[0]) ? $parts[0] : '';
            $collection = isset($parts[1]) ? $parts[1] : '';
        }

        $numberOfPieces = $this->useXPath($response,
            'LookupItemResponse/ItemOptionalFields/ItemDescription/NumberOfPieces');

        $holdQueue = (string) $this->useXPath($response,
            'LookupItemResponse/ItemOptionalFields/HoldQueueLength')[0];

        $itemRestriction = $this->useXPath($response,
            'LookupItemResponse/ItemOptionalFields/ItemUseRestrictionType');
        if (! empty($status) && (string) $status[0] == 'On Loan') {
            $dueDate = $this->useXPath($response, 'LookupItemResponse/ItemOptionalFields/DateDue');
            $dueDate = $this->parseDate($dueDate);
        } else {
            $dueDate = false;
        }

        $label = $this->determineLabel($status);
        $addLink = $this->isLinkAllowed($status, $itemRestriction);

        return array(
            'id' => empty($id) ? "" : $id,
            'availability' => empty($itemRestriction) ? '' : (string) $itemRestriction[0],
            'status' => empty($status) ? '' : (string) $status[0],
            'location' => '',
            'sub_lib_desc' => '',
            'collection' => isset($collection) ? $collection : '',
            'department' => isset($department) ? $department : '',
            'number' => empty($numberOfPieces) ? '' : (string) $numberOfPieces[0],
            'requests_placed' => empty($holdQueue) ? "" : $holdQueue,
            'queue' => isset($holdQueue) ? $holdQueue : '',
            'item_id' => empty($id) ? "" : $id,
            'label' => $label,
            'holdOverride' => "",
            'addStorageRetrievalRequestLink' => "",
            'addILLRequestLink' => "",
            'addLink' => $addLink,
            'duedate' => empty($dueDate) ? '' : $dueDate,
            'usedGetStatus' => true
        );
    }

    public function getItemStatus($id, $bibId, $patron) {
        return $this->getStatus($id, $patron);
    }

    /**
     * Get Statuses
     *
     * This method calls getStatus for an array of records.
     *
     * @param
     *            array Array of bibliographic record IDs.
     *
     * @throws ILSException
     * @return array Array of return values from getStatus.
     */
    public function getStatuses($ids, $patron = [], $filter = [], $bibId = [], $nextItemToken = null)
    {
        if (empty($bibId)) $this->cannotUseLUIS = true;
        if (! empty($filter) && in_array($this->agency, $this->libsLikeLiberec)) $this->cannotUseLUIS = true;
        if ($this->cannotUseLUIS) {
            // If we cannot use LUIS we will parse only the first one
            $retVal = [];
            $retVal[] = $this->getStatus(reset($ids), $patron);
            return $retVal;
        }
        else {
            if (in_array($this->agency, $this->libsLikeTabor)) {
                if ($this->agency === 'SOG504') {
                $bibId = '00124' . sprintf('%010d', $bibId);
                } elseif  ($this->agency === 'KHG001' ) {
                $bibId = '00160' . sprintf('%010d', $bibId);
                }
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutuses($response);
            }

            if ($this->agency === 'ZLG001') {
                $bibId = str_replace('oai:', '', $bibId);
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutusesZlg($response);
            }

            if (in_array($this->agency, $this->libsLikeLiberec)) {
                $bibId = str_replace('LiUsCat_', 'li_us_cat*', $bibId);
                $bibId = str_replace('CbvkUsCat_', 'cbvk_us_cat*', $bibId);
                $bibId = str_replace('KlUsCat_', 'kl_us_cat*', $bibId);
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutusesZlg($response);
            }

            if ($this->agency === 'ABA008') { // NLK
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutusesNlk($response);
            }

            if ($this->agency === 'ABG001') { // MKP
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutusesZlg($response);
            }

            if ($this->agency === 'UOG505') { // tre
                $request = $this->requests->LUISBibItem($bibId, $nextItemToken, $this, $patron);
                $response = $this->sendRequest($request);
                return $this->handleStutusesZlg($response);
            }
        }
        return [];
    }

    private function handleStutuses($response)
    {
        $retVal = array();

        $bib_id = $this->useXPath($response,
                'LookupItemSetResponse/BibInformation/BibliographicId/BibliographicItemId/BibliographicItemIdentifier');

        $nextItemToken = $this->useXPath($response, 'LookupItemSetResponse/NextItemToken');

        $holdingSets = $this->useXPath($response, 'LookupItemSetResponse/BibInformation/HoldingsSet');
        foreach ($holdingSets as $holdingSet) {
            $department = '';

            $item_id = $this->useXPath($holdingSet,
                    'ItemInformation/ItemId/ItemIdentifierValue');

            $status = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/CirculationStatus');

            if (! empty($status) && (string) $status[0] == 'On Loan') {
                $dueDate = $this->useXPath($holdingSet,
                        'ItemInformation/DateDue');
                $dueDate = $this->parseDate($dueDate);
            } else {
                /* 'On Order' means that item is ordered from stock and will be loaned, but we don't know dueDate yet.*/
                $dueDate = false;
            }

            $holdQueue = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/HoldQueueLength');

            $itemRestriction = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/ItemUseRestrictionType');

            $label = $this->determineLabel($status);
            $addLink = $this->isLinkAllowed($status, $itemRestriction);
            if (in_array($this->agency, $this->libsLikeTabor)) {
                if ((string) $itemRestriction[0] == 'Orderable') {
                    $addLink = true;
                    $itemRestriction = array_pop($itemRestriction);
                }
                else {
                    $addLink = false;
                }
            }

            $locations = $this->useXPath($holdingSet, 'ItemInformation/ItemOptionalFields/Location');
            foreach ($locations as $locElement) {
                $level = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameLevel');
                $locationName = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameValue');
                if (! empty($level)) {
                    if ((string) $level[0] == '1') if (! empty($locationName)) $department = (string) $locationName[0];
                }
            }

            $retVal[] = array(
                'id' => empty($bib_id) ? "" : (string) $bib_id[0],
                'availability' => empty($itemRestriction) ? '' : (string) $itemRestriction[0],
                'status' => empty($status) ? "" : (string) $status[0],
                'location' => '',
                'collection' => '',
                'sub_lib_desc' => '',
                'department' => $department,
                'requests_placed' => ! isset($holdQueue) ? "" : (string) $holdQueue[0],
                'item_id' => empty($item_id) ? "" : (string) $item_id[0],
                'label' => $label,
                'hold_type' => isset($holdQueue) && intval($holdQueue) > 0 ? 'Recall This' : 'Place a Hold',
                'restrictions' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'next_item_token' => empty($nextItemToken) ? '' : (string) $nextItemToken[0],
                'addLink' => $addLink,
            );
        }
        return $retVal;
    }

    private function handleStutusesZlg($response)
    {
        $retVal = array();

        $bib_id = $this->useXPath($response,
                'LookupItemSetResponse/BibInformation/BibliographicId/BibliographicItemId/BibliographicItemIdentifier');

        $nextItemToken = $this->useXPath($response, 'LookupItemSetResponse/NextItemToken');

        $items = $this->useXPath($response, 'LookupItemSetResponse/BibInformation/HoldingsSet/ItemInformation');
        foreach ($items as $itemInformation) {
            $collection = '';
            $department = '';

            $item_id = $this->useXPath($itemInformation,
                    'ItemId/ItemIdentifierValue');
            if (in_array($this->agency, $this->libsLikeLiberec)) {
                if (empty($item_id)) {
                    $item_id = $this->useXPath($itemInformation,
                            'ItemOptionalFields/BibliographicDescription/ComponentId/ComponentIdentifier');
                }
                if (empty($item_id) || empty($item_id[0])) { // this is for LIA's periodicals (without item_id)
                    $item_id = $this->useXPath($itemInformation,
                            'ItemOptionalFields/ItemDescription/CopyNumber');
                }
            }

            $status = $this->useXPath($itemInformation,
                    'ItemOptionalFields/CirculationStatus');
            $status = $this->convertStatus($status);

            if (! empty($status) && (string) $status[0] == 'On Loan') {
                $dueDate = $this->useXPath($itemInformation,
                        'ItemOptionalFields/DateDue');
                $dueDate = $this->parseDate($dueDate);
            } else {
                $dueDate = false;
            }

            $holdQueue = $this->useXPath($itemInformation,
                    'ItemOptionalFields/HoldQueueLength');

            $itemRestriction = $this->useXPath($itemInformation,
                    'ItemOptionalFields/ItemUseRestrictionType');

            $locations = $this->useXPath($itemInformation, 'ItemOptionalFields/Location');
            foreach ($locations as $locElement) {
                $level = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameLevel');
                $locationName = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameValue');
                if (! empty($level)) {
                    if ((string) $level[0] == '1') if (! empty($locationName)) $department = (string) $locationName[0];
                    if ((string) $level[0] == '2') if (! empty($locationName)) $collection = (string) $locationName[0];
                    if (empty($department) && (string) $level[0] == '4') {
                        if (! empty($locationName)) $department = (string) $locationName[0];
                    }
                }
            }

            $label = $this->determineLabel($status);
            $addLink = $this->isLinkAllowed($status, $itemRestriction, $department);

            $retVal[] = array(
                'id' => empty($bib_id) ? "" : (string) $bib_id[0],
                'availability' => empty($itemRestriction) ? '' : (string) $itemRestriction[0],
                'status' => empty($status) ? "" : (string) $status[0],
                'location' => '',
                'collection' => $collection,
                'sub_lib_desc' => '',
                'department' => $department,
                'requests_placed' => ! isset($holdQueue) ? "" : (string) $holdQueue[0],
                'item_id' => empty($item_id) ? "" : (string) $item_id[0],
                'label' => $label,
                'hold_type' => isset($holdQueue) && intval($holdQueue) > 0 ? 'Recall This' : 'Place a Hold',
                'restrictions' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'next_item_token' => empty($nextItemToken) ? '' : (string) $nextItemToken[0],
                'addLink' => $addLink,
            );
        }
        return $retVal;
    }

    private function handleStutusesNlk($response) {
        $retVal = array();

        $bib_id = $this->useXPath($response,
                'LookupItemSetResponse/BibInformation/BibliographicId/BibliographicItemId/BibliographicItemIdentifier');

        $nextItemToken = $this->useXPath($response, 'LookupItemSetResponse/NextItemToken');

        $holdingSets = $this->useXPath($response, 'LookupItemSetResponse/BibInformation/HoldingsSet');
        foreach ($holdingSets as $holdingSet) {
            $department = '';

            $item_id = $this->useXPath($holdingSet,
                    'ItemInformation/ItemId/ItemIdentifierValue');

            $status = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/CirculationStatus');

            if (! empty($status) && (string) $status[0] == 'On Loan') {
                $dueDate = $this->useXPath($holdingSet,
                        'ItemInformation/DateDue');
                $dueDate = $this->parseDate($dueDate);
            } else {
                /* 'On Order' means that item is ordered from stock and will be loaned, but we don't know dueDate yet.*/
                $dueDate = false;
            }

            $holdQueue = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/HoldQueueLength');

            $itemRestriction = $this->useXPath($holdingSet,
                    'ItemInformation/ItemOptionalFields/ItemUseRestrictionType');

            $label = $this->determineLabel($status);
            $addLink = $this->isLinkAllowed($status, $itemRestriction);

            $locations = $this->useXPath($holdingSet, 'Location');
            foreach ($locations as $locElement) {
                $level = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameLevel');
                $locationName = $this->useXPath($locElement, 'LocationName/LocationNameInstance/LocationNameValue');
                if (! empty($level)) {
                    if ((string) $level[0] == '1') if (! empty($locationName)) $department = (string) $locationName[0];
                }
            }
            $parts = explode("@", $department);
            $translate = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
            $parts = explode(" ", $translate, 2);
            $department = isset($parts[0]) ? $parts[0] : '';
            $collection = isset($parts[1]) ? $parts[1] : '';

            $retVal[] = array(
                'id' => empty($bib_id) ? "" : (string) $bib_id[0],
                'availability' => empty($itemRestriction) ? '' : (string) $itemRestriction[0],
                'status' => empty($status) ? "" : (string) $status[0],
                'location' => '',
                'collection' => $collection,
                'sub_lib_desc' => '',
                'department' => $department,
                'requests_placed' => ! isset($holdQueue) ? "" : (string) $holdQueue[0],
                'item_id' => empty($item_id) ? "" : (string) $item_id[0],
                'label' => $label,
                'hold_type' => isset($holdQueue) && intval($holdQueue) > 0 ? 'Recall This' : 'Place a Hold',
                'restrictions' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'next_item_token' => empty($nextItemToken) ? '' : (string) $nextItemToken[0],
                'addLink' => $addLink,
            );
        }
        return $retVal;
    }

    /**
     * Determines the color of item status' frame.
     *
     * @param SimpleXMLElement $status
     * @return string $label
     */
    protected function determineLabel($status) {
        $status = empty($status) ? '' : (string) $status[0];
        $label = 'label-danger';
        if (($status === 'Available On Shelf') || ($status === 'Available For Pickup'))
            $label = 'label-success';
        elseif (($status === 'On Loan') || ($status === 'On Order') || ($status === 'In Process') ||
                    ($status === 'In Transit Between Library Locations'))
                $label = 'label-warning';
        elseif (($status === 'Circulation Status Undefined'))
                $label = 'label-unknown';
        return $label;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id
     *            The record id to retrieve the holdings.
     * @param array $patron
     *            Patron data.
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array On success, an associative array with the following
     *         keys: id, availability (boolean), status, location, reserve,
     *         callnumber,
     *         duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        // id may have the form of "patronId:agencyId"
        list ($id, $agencyId) = $this->splitAgencyId($id);

        // FIXME: Is this not async iteration useful??
        do {
            if (isset($nextItemToken[0]))
                $request = $this->requests->LUISBibItem(
                    array(
                        $id
                    ), (string) $nextItemToken[0], $this, $patron);
            else {
                $request = $this->requests->LUISBibItem(
                    array(
                        $id
                    ), null, $this, $patron);
                $all_iteminfo = [];
            }

            $response = $this->sendRequest($request);
            if ($response == null)
                return null;

            $new_iteminfo = $this->useXPath($response,
                'LookupItemSetResponse/BibInformation/HoldingsSet/ItemInformation');
            $all_iteminfo = array_merge($all_iteminfo, $new_iteminfo);

            $nextItemToken = $this->useXPath($response,
                'LookupItemSetResponse/NextItemToken');
        } while ($this->isValidToken($nextItemToken));
        $bibinfo = $this->useXPath($response, 'LookupItemSetResponse/BibInformation');
        $bibinfo = $bibinfo[0];

        // Build the array of holdings:
        $holdings = array();
        foreach ($all_iteminfo as $current) {
            $holdings[] = $this->getHoldingsForChunk($current, $bibinfo);
        }
        return $holdings;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id
     *            The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array An array with the acquisitions data on success.
     *         @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username
     *            The patron username
     * @param string $password
     *            The patron's password
     *
     * @throws ILSException
     * @return mixed Associative array of patron info on successful login,
     *         null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        // If password is null, than is user already logged in ..
        if ($password == null) {
            $temp = array(
                'id' => $username
            );
            return $this->getMyProfile($temp);
        }

        $request = $this->requests->patronLogin($username, $password);
        $response = $this->sendRequest($request);
        $id = $this->useXPath($response,
            'LookupUserResponse/UserId/UserIdentifierValue');
        $firstname = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/NameInformation/PersonalNameInformation/StructuredPersonalUserName/GivenName');

        $lastname = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/NameInformation/PersonalNameInformation/StructuredPersonalUserName/Surname');
        $email = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/ElectronicAddress/ElectronicAddressData');
        if (! empty($id)) {
            $patron = array(
                'id' => empty($id) ? '' : (string) $id[0],
                'firstname' => empty($firstname) ? '' : (string) $firstname[0],
                'lastname' => empty($lastname) ? '' : (string) $lastname[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => empty($email) ? '' : (string) $email[0],
                'major' => null,
                'college' => null
            );
            return $patron;
        }
        return null;
    }

    /**
     * Get Patron Transactions
     *
     * This method queries the ILS for a patron's current checked out items.
     *
     * @param array $patron
     *            The patron array
     *
     * @throws ILSException
     * @return array Array of arrays, one for each item checked out by the
     *         specified account.
     */
    public function getMyTransactions($patron)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronLoanedItems($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);
        return $this->handleTransactions($response, $patron);
    }

    private function handleTransactions($response, $patron)
    {
        $retVal = array();
        $list = $this->useXPath($response, 'LookupUserResponse/LoanedItem');

        foreach ($list as $current) {
            $isbn = $bib_id = $author = $mediumType = null;
            $item_id = $this->getFirstXPathMatchAsString($current,
                'ItemId/ItemIdentifierValue');

            if (! $item_id) {
                // MKP, in house loans (present loans) has no item_id
                continue;
            }

            $dateDue = $this->useXPath($current, 'DateDue');
            $title = $this->useXPath($current, 'Title');
            $publicationYear = $this->useXPath($current, 'Ext/BibliographicDescription/PublicationDate');
            $dueStatus =$this->hasOverdue($dateDue);
            $dateDue = $this->parseDate($dateDue);
            $renewalNotPermitted = $this->useXPath($current, 'Ext/RenewalNotPermitted');
            $renewable = empty($renewalNotPermitted)? true : false;
            $additRequest = $this->requests->lookupItem($item_id, $patron);
            try {
                $additResponse = $this->sendRequest($additRequest);
                $isbn = $this->useXPath($additResponse,
                        'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicRecordId/BibliographicRecordIdentifier');
                $bib_id = $this->getFirstXPathMatchAsString($additResponse,
                        'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
                $author = $this->useXPath($additResponse,
                        'LookupItemResponse/ItemOptionalFields/BibliographicDescription/Author');
                if (empty($title) || $title[0] == '')
                    $title = $this->useXPath($additResponse, 'LookupItemResponse/ItemOptionalFields/BibliographicDescription/Title');
                $mediumType = $this->useXPath($additResponse,
                        'LookupItemResponse/ItemOptionalFields/BibliographicDescription/MediumType');

            } catch (ILSException $e) {
            }

          if ($this->agency === 'KHG001' || $this->agency === 'SOG504') {
                $dateDue = $this->useXPath($current, 'dateDue');
                $dueStatus =$this->hasOverdue($dateDue);
                $dateDue = $this->parseDate($dateDue);
            } 

	  $retVal[] = array(
                'cat_username' => $patron['cat_username'],
                'duedate' => empty($dateDue) ? '' : $dateDue,
                'id' => $bib_id,
                'barcode' => $item_id,
                                 // 'renew' => '', // TODO
                                 // 'renewLimit' => '', // TODO
                'request' => empty($request) ? '' : (string) $request[0],
                'volume' => '',
                'author' => empty($author) ? '' : (string) $author[0],
                'publication_year' => empty($publicationYear) ? '' : (string) $publicationYear[0],
                'renewable' => $renewable,
                'message' => '',
                'title' => empty($title) ? '' : (string) $title[0],
                'item_id' => $item_id,
                'institution_name' => '',
                'isbn' => empty($isbn) ? '' : (string) $isbn[0],
                'issn' => '',
                'oclc' => '',
                'upc' => '',
                'borrowingLocation' => '',
                'loan_id' => $item_id,
                'dueStatus' => $dueStatus
            );
        }
        return $retVal;
    }

    /**
     * Get Patron Fines
     *
     * This method queries the ILS for a patron's current fines.
     *
     * @param array $patron
     *            The patron array
     *
     * @return array Array of arrays containing fines information.
     */
    public function getMyFines($patron)
    {
        if ($this->agency === 'ABA008') { // NLK
            throw new ILSException('driver_no_fines');
        }

        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronFiscalAccount($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $list = $this->useXPath($response,
            'LookupUserResponse/UserFiscalAccount/AccountDetails');
        $monetaryValue = $this->useXPath($response,
            'LookupUserResponse/UserFiscalAccount/AccountBalance/MonetaryValue');

        $fines = array();
        $sum = 0;
        $leastOne = false;
        foreach ($list as $current) {
            $excluded = false;
            $amount = $this->useXPath($current,
                'FiscalTransactionInformation/Amount/MonetaryValue');
            $action = $this->useXPath($current,
                    'FiscalTransactionInformation/FiscalActionType');
            $type = $this->useXPath($current,
                'FiscalTransactionInformation/FiscalTransactionType');
            $date = $this->useXPath($current, 'AccrualDate');
            $desc = $this->useXPath($current,
                'FiscalTransactionInformation/FiscalTransactionDescription');
            $item_id = $this->useXPath($current, 'FiscalTransactionInformation/ItemDetails/ItemId/ItemIdentifierValue');
            if ($this->isAncientFee($date)) $excluded = true; // exclude old fees
            $date = $this->parseDate($date);
            $amount_int = (int) $amount[0];
            if ($amount_int == 0) continue; // remove zero fees
            if (! $excluded) $leastOne = true;
            $sum += $amount_int;

            if ($this->agency == 'ZLG001') $desc = $action;
            if (empty($desc)) $desc = $type;

            $fines[] = array(
                'amount' => (string) $amount_int,
                'checkout' => $date,
                'fine' => $this->translator->translate((string) $type[0]),
                'balance' => (string) $sum,
                'createdate' => '',
                'duedate' => '',
                'id' => (string) $desc[0],
                'excluded' => $excluded
            );
        }
        if (empty($fines) && ! empty($monetaryValue) && (int) $monetaryValue[0] != 0) $fines[] = array(
                'amount' => (string) $monetaryValue[0],
                'balance' => (string) $monetaryValue[0]
            );
        if (! empty($fines) && ! $leastOne) $fines[count($fines) - 1]['excluded'] = false;
        return $fines;
    }

    /**
     * Get Patron's current holds - books which are reserved.
     *
     * @param array $patron
     *            The patron array
     *
     * @return array Array of arrays, one for each hold.
     */
    public function getMyHolds($patron)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronRequestedItems($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $retVal = array();
        $list = $this->useXPath($response, 'LookupUserResponse/RequestedItem');

        foreach ($list as $current) {
            $cannotCancel = false;
            $type = $this->useXPath($current, 'RequestType');
            $requestStatusType = $this->useXPath($current, 'RequestStatusType');
            $id = $this->useXPath($current,
                'BibliographicId/BibliographicItemId/BibliographicItemIdentifier');
            $location = $this->useXPath($current, 'PickupLocation');
            $reqnum = $this->useXPath($current, 'RequestId/RequestIdentifierValue');
            $expire = $this->useXPath($current, 'PickupExpiryDate');
            $create = $this->useXPath($current, 'DatePlaced');
            $position = $this->useXPath($current, 'HoldQueuePosition');
            $item_id = $this->useXPath($current, 'ItemId/ItemIdentifierValue');
            $title = $this->useXPath($current, 'Title');
            $extTitle = $this->useXPath($current, 'Ext/BibliographicDescription/Title');

            // Deal with Zlin.
            if (empty($id)) $id = $this->useXPath($current,
                    'Ext/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
            if (empty($title)) $title = $extTitle;
            if ($this->agency === 'ZLG001') {
                // $type == 'Hold' => rezervace; $type == 'Loan' => objednavka
            }

            // Deal with Liberec.
            if (empty($position)) $position = $this->useXPath($current,
                    'Ext/HoldQueueLength');
            if (in_array($this->agency, $this->libsLikeLiberec)) {
                $title = $extTitle; // TODO temporary solution for periodics
                if ((! empty($type)) && ((string) $type[0] == 'w')) $cannotCancel = true;
            }

            // Deal with Tabor.
            if (in_array($this->agency, $this->libsLikeTabor)) {
                // $type == 'Hold' => rezervace; $type == 'Stack Retrieval' => objednavka
                if (empty($expire)) $expire = $this->useXPath($current,
                        'Ext/NeedBeforeDate');
                if ((! empty($type)) && ((string) $type[0] == 'Stack Retrieval')) {
                    $cannotCancel = true;
                    $position = null; // hide queue position
                }
            }

            if ($this->agency === 'ABA008') { // NLK
                $parts = explode("@", (string) $location[0]);
                $location[0] = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
                $additId = empty($item_id) ? '' : (string) $item_id[0];
                $additRequest = $this->requests->lookupItem($additId, $patron);
                try {
                    $additResponse = $this->sendRequest($additRequest);
                    $id = $this->useXPath($additResponse,
                            'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
                } catch (ILSException $e) {
                }
            }

            if (! empty($position)) if ((string) $position[0] === '0') $position = null; // hide queue position
            $bib_id = empty($id) ? null : explode('-', (string) $id[0])[0];
            if (in_array($this->agency, $this->libsLikeLiberec)) {
                $bib_id = str_replace('li_us_cat*', 'LiUsCat_', $bib_id);
                $bib_id = str_replace('cbvk_us_cat*', 'CbvkUsCat_', $bib_id);
                $bib_id = str_replace('kl_us_cat*', 'KlUsCat_', $bib_id);
            }
            $create = $this->parseDate($create);
            $expire = $this->parseDate($expire);

            $available = false;
            if ((! empty($requestStatusType)) &&
                        ((string) $requestStatusType[0] == 'Available For Pickup')) {
                $available = true;
                $cannotCancel = true;
            }

            $retVal[] = array(
                'type' => empty($type) ? '' : (string) $type[0],
                'id' => empty($bib_id) ? '' : $bib_id,
                'location' => empty($location) ? '' : (string) $location[0],
                'reqnum' => empty($reqnum) ? '' : static::CANCEL_REQUEST_PREFIX . (string) $reqnum[0],
                'expire' => empty($expire) ? '' : $expire,
                'create' => empty($create) ? '' : $create,
                'position' => empty($position) ? null : (string) $position[0],
                'available' => $available, // true means item is ready for check out
                'item_id' => empty($item_id) ? '' : (string) $item_id[0],
                'barcode' => empty($item_id) ? '' : (string) $item_id[0],
                'volume' => '',
                'publication_year' => '',
                'title' => empty($title) ? '' : (string) $title[0],
                'isbn' => '',
                'issn' => '',
                'oclc' => '',
                'upc' => '',
                'cannotcancel' => $cannotCancel,
                'fake_id' => $this->source . '.N/A',
            );
        }
        return $retVal;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron
     *            The patron array
     *
     * @throws ILSException
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        // id may have the form of "patronId:agencyId"
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);

        $request = $this->requests->patronFullInformation($patron);
        $response = $this->sendRequest($request);

        // Merge it back if the agency was specified before ..
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $name = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/NameInformation/PersonalNameInformation/StructuredPersonalUserName/GivenName');
        $surname = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/NameInformation/PersonalNameInformation/StructuredPersonalUserName/Surname');
        $address1 = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/Street');
        $address2 = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/HouseName');
        $city = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/Locality');
        $country = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/Country');
        $zip = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/PostalCode');
        if (empty($zip)) {
            $zip = $this->useXPath($response,
                    'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/PostOfficeBox');
        }
        $electronicAddress = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserAddressInformation/ElectronicAddress');
        foreach ($electronicAddress as $recent) {
            if ($this->useXPath($recent, 'ElectronicAddressType')[0] == 'tel') {
                $phone = $this->useXPath($recent, 'ElectronicAddressData');
            }
            if ($this->useXPath($recent, 'ElectronicAddressType')[0] == 'mailto') {
                $email = $this->useXPath($recent, 'ElectronicAddressData');
            }
        }
        if ($this->agency === 'KHG001' || $this->agency === 'SOG504') {
            $email = $this->useXPath($response,
                    'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/ElectronicAddressData');
        }
        $group = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserPrivilege/UserPrivilegeDescription');
        if (empty($group)) {
            $group = $this->useXPath($response,
                    'LookupUserResponse/UserOptionalFields/UserPrivilege/AgencyUserPrivilegeType');
        }
        $group = $this->extractData($group);
        $translatedGroup = $this->translator->translate($this->source . '_group_' . $group);
        $group = ($translatedGroup == $this->source . '_group_' . $group) ? $group : $translatedGroup;

        $rawExpire = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserPrivilege/ValidToDate');

        $expireDate = $this->parseDate($rawExpire);

        $blocksParsed = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/BlockOrTrap/BlockOrTrapType');

        $logo = $this->logo;

        foreach ($blocksParsed as $block) {
            $block = $this->translator->translate((string) $block);
            if (! empty($logo)) {
                if (! empty($blocks[$logo]))
                    $blocks[$logo] .= ", " . $block;
                else
                    $blocks[$logo] = $block;
            } else
                $blocks[] = $block;
        }

        if (empty($name) && empty($surname)) {
            $name = $this->useXPath($response,
                'LookupUserResponse/UserOptionalFields/NameInformation/PersonalNameInformation/UnstructuredPersonalUserName');
        }
        if (empty($address1)) {
            $address1 = $this->useXPath($response,
                'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/Line1');
        }
        if (empty($city)) {
            $city = $this->useXPath($response,
                'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/Line2');
        }
        if (empty($address1)) {
            $address1 = $this->useXPath($response,
                    'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/StructuredAddress/District');
        }
        if (empty($address1)) {
            $address1 = $this->useXPath($response,
                'LookupUserResponse/UserOptionalFields/UserAddressInformation/PhysicalAddress/' .
                'UnstructuredAddress/UnstructuredAddressData');
        }

        $patron = array(
            'cat_username' => $patron['id'],
            'id' => $patron['id'],
            'firstname' => empty($name) ? '' : (string) $name[0],
            'lastname' => empty($surname) ? '' : (string) $surname[0],
            'address1' => empty($address1) ? '' : (string) $address1[0],
            'address2' => empty($address2) ? '' : (string) $address2[0],
            'city' => empty($city) ? '' : (string) $city[0],
            'country' => empty($country) ? '' : (string) $country[0],
            'zip' => empty($zip) ? '' : (string) $zip[0],
            'phone' => empty($phone) ? '' : (string) $phone[0],
            'group' => $group,
            'blocks' => empty($blocks) ? array() : $blocks,
            'email' => empty($email) ? '' : (string) $email[0],
            'expire' => $expireDate
        );
        return $patron;
    }

    /**
     * Get Patron BlocksOrTraps
     *
     * This is responsible for retrieving blocks of a specific patron.
     *
     * @param array $cat_username
     *            String of userId
     *
     * @throws ILSException
     * @return array Array of the patron's blocks in string.
     */
    public function getBlocks($cat_username)
    {
        $patron = array(
            'id' => $cat_username
            );
        $request = $this->requests->patronBlocks($patron);
        $response = $this->sendRequest($request);

        $blocksParsed = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/BlockOrTrap/BlockOrTrapType');

        $i = - 1;
        foreach ($blocksParsed as $block) {
            $blocks[++ $i] = (string) $block;
        }

        return $blocks;
    }

    public function getAccruedOverdue($user) {
        // TODO
        return array();
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page
     *            Page number of results to retrieve (counting starts at 1)
     * @param int $limit
     *            The size of each page of results to retrieve
     * @param int $daysOld
     *            The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId
     *            optional fund ID to use for limiting results (use a value
     *            returned by getFunds, or exclude for no limit); note that
     *            "fund" may be a
     *            misnomer - if funds are not an appropriate way to limit your
     *            new item
     *            results, you can return a different set of values from
     *            getFunds. The
     *            important thing is that this parameter supports an ID returned
     *            by getFunds,
     *            whatever that may mean.
     *
     * @throws ILSException
     * @return array Associative array with 'count' and 'results' keys
     *         @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        return array();
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws ILSException
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        // TODO
        return array();
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = dept. ID, value = dept.
     *         name.
     */
    public function getDepartments()
    {
        // TODO
        return array();
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        // TODO
        return array();
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        // TODO
        return array();
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course
     *            ID from getCourses (empty string to match all)
     * @param string $inst
     *            ID from getInstructors (empty string to match all)
     * @param string $dept
     *            ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     *         @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return array();
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        // TODO
        return array();
    }

    /**
     * Explodes string using const AGENCY_ID_DELIMITER to fetch agencyId.
     *
     * If no AGENCY_ID_DELIMITER found in string, it returns false in $agencyId.
     *
     * Return value = [ $id, $agencyId ]
     *
     * @param string $id
     * @return array
     */
    public function splitAgencyId($id)
    {

        // $id may have the form of "agencyId:itemId"
        $agencyId = false;
        $idSplitted = explode(static::AGENCY_ID_DELIMITER, $id);

        if (count($idSplitted) > 1) {
            $agencyId = $idSplitted[0];

            // Merge the rest of the array
            $idSplitted = array_slice($idSplitted, 1);
            $id = implode(static::AGENCY_ID_DELIMITER, $idSplitted);
        }

        return [
            $id,
            $agencyId
        ];
    }

    public function getMaximumItemsCount()
    {
        return $this->maximumItemsCount;
    }

    /**
     * Gets the contact person for this driver instance.
     *
     * @return string
     */
    public function getAdministratorEmail()
    {
        if (isset($this->config->Catalog->contactPerson))
            return $this->config->Catalog->contactPerson;
        else
            return null;
    }

    /**
     * Validate XML against XSD schema.
     *
     * @param string $XML
     *            or SimpleXMLElement $XML
     * @param
     *            $path_to_XSD
     *
     * @throws ILSException
     * @return boolean Returns true, if XML is valid.
     */
    protected function isValidXMLAgainstXSD($XML,
        $path_to_XSD = './module/VuFind/tests/fixtures/ils/xcncip2/schemas/v2.02.xsd')
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true); // Begin - Disable xml error messages.
        if (is_string($XML))
            $doc->loadXML($XML);
        else
            if (get_class($XML) == 'SimpleXMLElement')
                $doc->loadXML($XML->asXML());
            else {
                $message = 'Expected SimpleXMLElement or string containing XML.';
                $this->addEnviromentalException($message);
                throw new ILSException($message);
            }

        libxml_clear_errors(); // End - Disable xml error messages.
        return $doc->schemaValidate($path_to_XSD);
    }

    /**
     * Check response from NCIP responder.
     * Check if $response is not containing problem tag.
     *
     * @param $response SimpleXMLElement
     *            Object
     *
     * @return mixed string Problem | boolean Returns false, if response is without problem.
     */
    protected function getProblem($response)
    {
        $problem = $this->useXPath($response, '//Problem');

        if ($problem == null)
            return false;

        return $problem[0]->AsXML();
    }

    protected function addEnviromentalException($message)
    {
        $_ENV['exceptions']['ncip'] = $message;
    }

    protected function useXPath(\SimpleXMLElement $xmlObject, $xPath)
    {
        $arrayXPath = explode('/', $xPath);
        $newXPath = "";
        foreach ($arrayXPath as $key => $part) {
            if ($part == null) {
                $newXPath .= "/";
                continue;
            }
            $newXPath .= "*[local-name()='" . $part . "']";
            if ($key != (sizeof($arrayXPath) - 1))
                $newXPath .= '/';
        }
        // var_dump($newXPath);
        return $xmlObject->xpath($newXPath);
    }

    /**
     *
     * @param \SimpleXMLElement $xmlObject
     * @param string $xPath
     * @return boolean false || string
     */
    protected function getFirstXPathMatchAsString(\SimpleXMLElement $xmlObject,
        $xPath)
    {
        $xPathMatch = $this->useXPath($xmlObject, $xPath);

        if ($xPathMatch !== false && is_array($xPathMatch)) {
            if (count($xPathMatch) > 0) {
                return (string) $xPathMatch[0];
            }
        }
        return false;
    }

    /**
     * Validate next item token.
     * Check if $nextItemToken was set and contains data.
     *
     * @param
     *            array, at index [0] SimpleXMLElement Object
     *
     * @return boolean Returns true, if token is valid.
     */
    protected function isValidToken($nextItemToken)
    {
        if (isset($nextItemToken[0])) {
            return ! empty((string) $nextItemToken[0]);
        }
        return false;
    }

    /**
     * If agencyId isn't false, it will return $agencyId joined with $id
     * using AGENCY_ID_DELIMITER.
     *
     * Else it returns only $id.
     *
     * @param string $id
     * @param string $agencyId
     * @return string
     */
    protected function joinAgencyId($id, $agencyId)
    {
        if ($agencyId)
            return $agencyId . static::AGENCY_ID_DELIMITER . $id;
        else
            return $id;
    }

    protected function parseDate($date)
    {
        $parsedDate = empty($date) ? null : strtotime($date[0]);
        return ($parsedDate == null) ? '' : date('j. n. Y', $parsedDate);
    }

    protected function hasOverdue($dateDue)
    {
        $parsedDate = empty($dateDue) ? '' : strtotime($dateDue[0]);
        $today_time = strtotime(date("Y-m-d"));
        $expire_time = strtotime(date('Y-m-d', $parsedDate));
        return ($expire_time < $today_time) ? 'overdue' : false;
    }

    protected function isAncientFee($date)
    {
        $parsedDate = empty($date) ? '' : strtotime($date[0]);
        $fee_time = strtotime(date('Y-m-d', $parsedDate));
        $filter_time = strtotime(date("Y-m-d") . ' -1 year');
        return ($fee_time < $filter_time) ? true : false;
    }

    /**
     * Determines if item's hold link is available.
     *
     * @param SimpleXMLElement $status
     * @param SimpleXMLElement $itemRestriction
     * @return boolean $addLink
     */
    protected function isLinkAllowed($status, $itemRestriction, $department = null) {
        // Always show MKP's hold link, because it is hold for record, not item.
        if ($this->agency === 'ABG001') {
            return true;
        }
        if (! empty($this->hideHoldLinks)) {
            return false;
        }
        if ($department == 'Podlesí') {
            return false;
        }
        $status = empty($status) ? '' : (string) $status[0];
        $itemRestriction = empty($itemRestriction) ? '' : (string) $itemRestriction[0];
        $addLink = true;
        if ($itemRestriction === 'Not For Loan') $addLink = false;
        if (($status === 'Circulation Status Undefined') || ($status === 'Not Available') ||
                ($status === 'Lost')) $addLink = false;
        return $addLink;
    }

    /**
     * Converts status to expected value.
     *
     * @param SimpleXMLElement $status
     * @return SimpleXMLElement $status
     */
    protected function convertStatus($status) {
        if (! empty($status) && (string) $status[0] === 'Available on Shelf') $status[0] = 'Available On Shelf';
        if (! empty($status) && (string) $status[0] === 'Not available') $status[0] = 'Not Available';
        if (! empty($status) && (string) $status[0] === 'Available for Pickup') $status[0] = 'On Order';
        if (! empty($status) && (string) $status[0] === 'Available For Pickup') $status[0] = 'On Order';
        if (! empty($status) && (string) $status[0] === 'Waiting To Be Reshelved') $status[0] = 'In Process';
        return $status;
    }

    /**
     * Extract the first data from array of SimpleXMLElements.
     *
     * @param array of SimpleXMLElements $elements
     * @return string $data
     */
    protected function extractData($elements) {
        $data = '';
        if (! empty($elements)) {
            foreach ($elements as $element) {
                if (! empty($element)) {
                    $data = (string) $element;
                    break;
                }
            }
        }
        return $data;
    }
}
