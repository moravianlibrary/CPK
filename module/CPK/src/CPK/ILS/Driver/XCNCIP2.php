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

use VuFind\Exception\ILS as ILSException, DOMDocument, Zend\XmlRpc\Value\String;

/*
 * TODO List
 *
 * Check all functionalities of these services:
 * LookupItem
 * LookupItemSet
 * LookupUser
 * LookupAgency
 * LookupRequest
 * RequestItem
 * - placeHold()
 * CancelRequestItem
 * - cancelHolds()
 * RenewItem
 * - renewMyItems()
 *
 */
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
    \VuFindHttp\HttpServiceAwareInterface
{

    const AGENCY_ID_DELIMITER = ':';

    protected $maximumItemsCount = null;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    protected $requests = null;

    protected $cannotUseLUIS = false;

    protected $hasUntrustedSSL = false;

    protected $cacert = null;

    protected $timeout = null;

    protected $logo = null;

    protected $agency = '';

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

        $this->requests = new NCIPRequests($this->agency);
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

            if (isset($this->config['Catalog']['username']) &&
                 isset($this->config['Catalog']['password'])) {

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
                            CURLOPT_FOLLOWLOCATION => true,
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

            $this->addEnviromentalException($message);

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
                    $response = $this->sendRequest($request);

                    $problem = $this->useXPath($response,
                        'NCIPMessage/CancelRequestItemResponse/Problem');

                    if ($problem !== false && is_array($problem) &&
                         count($problem) > 0) {
                        $problemValue = $this->getFirstXPathMatchAsString(
                            $problem[0], 'ProblemValue');
                        $problemDetail = $this->getFirstXPathMatchAsString(
                            $problem[0], 'ProblemDetail');

                        $problemOccurred = true;
                    }

                    // $rawResponse = $response->asXML();

                    break;
                } else
                    if ($hold['id'] == $recent) { // Biblio-leveled cancel request

                        $request_id = $hold['reqnum'];

                        if (! $request_id > 0) {
                            $message = 'XCNCIP2 Driver cannot cancel biblio-leveled request without request id!';

                            $this->addEnviromentalException($message);
                            throw new ILSException($message);
                        }

                        $desiredRequestFound = true;

                        $request = $this->requests->cancelRequestItemUsingRequestId(
                            $patron, $request_id);
                        $response = $this->sendRequest($request);

                        $problem = $this->useXPath($response,
                            'NCIPMessage/CancelRequestItemResponse/Problem');

                        if ($problem !== false && is_array($problem) &&
                             count($problem) > 0) {
                            $problemValue = $this->getFirstXPathMatchAsString(
                                $problem[0], 'ProblemValue');
                            $problemDetail = $this->getFirstXPathMatchAsString(
                                $problem[0], 'ProblemDetail');

                            $problemOccurred = true;
                        }

                        // $rawResponse = $response->asXML();
                        break;
                    }
            }

            $didWeTheJob = $desiredRequestFound && ! $problemOccurred;

            $items[$recent] = array(
                'success' => $didWeTheJob,
                'status' => '',
                'sysMessage' => isset($problemValue) ?  : ''
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
            $request = $this->requests->renewItem($patron, $item);
            $response = $this->sendRequest($request);
            $date = $this->useXPath($response, 'RenewItemResponse/DateForReturn');
            $result[$item] = array(
                'success' => true,
                'new_date' => (string) $date[0],
                'new_time' => (string) $date[0], // used the same like previous
                'item_id' => $item,
                'sysMessage' => ''
            );
        }
        return array(
            'blocks' => false,
            'details' => $result
        );
    }

    public function getAccruedOverdue($user)
    {
        // TODO testing purposes
        return 12340;
        $sum = 0;
        $xml = $this->alephWebService->doRestDLFRequest(
            array(
                'patron',
                $user['id'],
                'circulationActions'
            ), null);
        foreach ($xml->circulationActions->institution as $institution) {
            $cashNote = (string) $institution->note;
            $matches = array();
            if (preg_match(
                "/Please note that there is an additional accrued overdue items fine of: (\d+\.?\d*)\./",
                $cashNote, $matches) === 1) {
                $sum = $matches[1];
            }
        }
        return $sum;
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
        return $holdDetails['item_id'];
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

    /*
     * public function getHoldLink ($item_id)
     * {
     * // TODO testing purposes
     * $itemIdParts = explode("-", $item_id);
     *
     * $id = substr($itemIdParts[0], 0, 5) . "-" . substr($itemIdParts[0], 5);
     * $link .= $id . '/Hold?id=' . $id . '&item_id=';
     * $link .= $itemIdParts[1];
     * $link .= '#tabnav';
     * return 'odlisenie/Hold?id=MZK01-001422752&item_id=MZK50001457754000010#tabnav';
     * return $link;
     * }
     */
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
        // FIXME
        return array();
    }

    public function getDefaultPickUpLocation($patron = null, $holdInformation = null)
    {
        return false;
    }

    public function getMyHistory($patron, $currentLimit = 0)
    {
        return [];
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
    public function getStatus($id)
    {
        if (! $this->cannotUseLUIS) {
            // TODO
            // For now, we'll just use getHolding, since getStatus should return a
            // subset of the same fields, and the extra values will be ignored.
            $holding = $this->getHolding($id);

            foreach ($holding as $recent) {
                $tmp[] = array_slice($recent, 0, 6);
            }
            return $tmp;
        } else {
            // id may have the form of "patronId:agencyId"
            list ($id, $agencyId) = $this->splitAgencyId($id);

            $request = $this->requests->lookupItem($id, $agencyId);
            $response = $this->sendRequest($request);
            if ($response == null)
                return null;

                // Merge it back if the agency was specified before ..
            $id = $this->joinAgencyId($id, $agencyId);

            // Extract details from the XML:
            $status = (string) $this->useXPath($response,
                'LookupItemResponse/ItemOptionalFields/CirculationStatus')[0];

            // Pick out the permanent location (TODO: better smarts for dealing with
            // temporary locations and multi-level location names):

            $locationNameInstance = $this->useXPath($response,
                'LookupItemResponse/ItemOptionalFields/Location/LocationName/LocationNameInstance');

            foreach ($locationNameInstance as $recent) {
                // FIXME: Create config to map location abbreviations of each institute into human readable values

                $locationLevel = (string) $this->useXPath($recent,
                    'LocationNameLevel')[0];

                if ($locationLevel == 4) {
                    $department = (string) $this->useXPath($recent,
                        'LocationNameValue')[0];
                } else
                    if ($locationLevel == 3) {
                        $sublibrary = (string) $this->useXPath($recent,
                            'LocationNameValue')[0];
                    } else {
                        $locationInBuilding = (string) $this->useXPath($recent,
                            'LocationNameValue')[0];
                    }
            }

            $numberOfPieces = (string) $this->useXPath($response,
                'LookupItemResponse/ItemOptionalFields/ItemDescription/NumberOfPieces')[0];

            $holdQueue = (string) $this->useXPath($response,
                'LookupItemResponse/ItemOptionalFields/HoldQueueLength')[0];

            $itemRestriction = (string) $this->useXPath($response,
                'LookupItemResponse/ItemOptionalFields/ItemUseRestrictionType')[0];

            // TODO Exists any clean way to get the due date without additional request?

            if (! empty($locationInBuilding))
                $onStock = substr($locationInBuilding, 0, 5) == 'Stock';
            else
                $onStock = false;

            $onStock = true;

            $restrictedToLibrary = ($itemRestriction == 'In Library Use Only');

            $monthLoanPeriod = ($itemRestriction ==
                 'Limited Circulation, Normal Loan Period') || empty(
                    $itemRestriction);

            // FIXME: Add link logic
            $link = false;

            $label = $this->determineLabel($status);

            return array(
                'id' => empty($id) ? "" : $id,
                'availability' => empty($itemRestriction) ? "" : $itemRestriction,
                'status' => empty($status) ? "" : $status,
                'location' => empty($locationInBuilding) ? "" : $locationInBuilding,
                'sub_lib_desc' => empty($sublibrary) ? '' : $sublibrary,
                'department' => empty($department) ? '' : $department,
                'number' => empty($numberOfPieces) ? "" : $numberOfPieces,
                'requests_placed' => empty($holdQueue) ? "" : $holdQueue,
                'item_id' => empty($id) ? "" : $id,
                'label' => $label,
                'holdOverride' => "",
                'addStorageRetrievalRequestLink' => "",
                'addILLRequestLink' => ""
            );
        }
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
    public function getStatuses($ids, $patron = [], $filter = [])
    {
        $retVal = [];

        if ($this->cannotUseLUIS)
            // If we cannot use LUIS we will parse only the first one
            $retVal[] = $this->getStatus(reset($ids));
        else {
            $request = $this->requests->LUISItemId($ids, null, $this, $patron);
            $response = $this->sendRequest($request);

            if ($response === null)
                return [];

            $bibInfos = $this->useXPath($response,
                'LookupItemSetResponse/BibInformation');

            foreach ($bibInfos as $bibInfo) {

                $id = $this->getFirstXPathMatchAsString($bibInfo,
                    'BibliographicId/BibliographicRecordId/BibliographicRecordIdentifier');

                $status = $this->getFirstXPathMatchAsString($bibInfo,
                    'HoldingsSet/ItemInformation/ItemOptionalFields/CirculationStatus');

                if ($status == 'On Loan') {
                    $dueDate = $this->getFirstXPathMatchAsString($bibInfo,
                        'HoldingsSet/ItemInformation/ItemOptionalFields/DateDue');
                } else {
                    $dueDate = false;
                }

                $itemCallNo = $this->getFirstXPathMatchAsString($bibInfo,
                    'HoldingsSet/ItemInformation/ItemOptionalFields/ItemDescription/CallNumber');

                $holdQueue = $this->getFirstXPathMatchAsString($bibInfo,
                    'HoldingsSet/ItemInformation/ItemOptionalFields/HoldQueueLength');

                $itemRestrictions = $this->useXPath($bibInfo,
                    'HoldingsSet/ItemInformation/ItemOptionalFields/ItemUseRestrictionType');

                $restrictedToLibrary = false;
                $monthLoanPeriod = false;

                $restrictions = [];
                foreach ($itemRestrictions as $itemRestriction) {
                    $restrictions[] = (string) $itemRestriction;

                    $restrictedToLibrary = ($itemRestriction == 'In Library Use Only');

                    $monthLoanPeriod = ($itemRestriction ==
                         'Limited Circulation, Normal Loan Period') ||
                         empty($itemRestriction);
                }

                $locationNameInstances = $this->useXPath($bibInfo,
                    'HoldingsSet/ItemInformation/ItemOptionalFields/Location/LocationName/LocationNameInstance');

                foreach ($locationNameInstances as $locationNameInstance) {
                    // FIXME: Create config to map location abbreviations of each institute into human readable values

                    $locationLevel = $this->getFirstXPathMatchAsString(
                        $locationNameInstance, '//LocationNameLevel');

                    if ($locationLevel == 4) {
                        $department = $this->getFirstXPathMatchAsString(
                            $locationNameInstance, '//LocationNameValue');
                    } else
                        if ($locationLevel == 3) {
                            $sublibrary = $this->getFirstXPathMatchAsString(
                                $locationNameInstance, '//LocationNameValue');
                        } else {
                            $locationInBuilding = $this->useXPath(
                                $locationNameInstance, '//LocationNameValue');
                        }
                }

                $label = $this->determineLabel($status);

                $retVal[] = array(
                    'id' => empty($id) ? "" : $id,
                    'status' => empty($status) ? "" : $status,
                    'location' => empty($locationInBuilding) ? "" : $locationInBuilding,
                    'sub_lib_desc' => empty($sublibrary) ? '' : $sublibrary,
                    'department' => empty($department) ? '' : $department,
                    'requests_placed' => ! isset($holdQueue) ? "" : $holdQueue,
                    'item_id' => empty($id) ? "" : $id,
                    'label' => $label,
                    'hold_type' => isset($holdQueue) && intval($holdQueue) > 0 ? 'Recall This' : 'Place a Hold',
                    'restrictions' => $restrictions,
                    'due_date' => $dueDate
                );
            }
        }
        return $retVal;
    }

    /**
     * Determines the color of item status' frame.
     *  */
    protected function determineLabel($status) {
        $label = 'label-danger';
        if (($status === 'Available On Shelf') || ($status === 'Available For Pickup'))
            $label = 'label-success';
        else
            if ($status === 'On Loan')
                $label = 'label-warning';
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
            $item_id = $this->getFirstXPathMatchAsString($current,
                'ItemId/ItemIdentifierValue');

            if (! $item_id)
                throw new ILSException(
                    "ItemIdentifierValue within ItemId is empty - cannot continue");

            $dateDue = $this->useXPath($current, 'DateDue');
            $parsedDate = strtotime((string) $dateDue[0]);
            $renewalNotPermitted = $this->useXPath($current, 'Ext/RenewalNotPermitted');
            $renewable = empty($renewalNotPermitted)? true : false;
            $additRequest = $this->requests->lookupItem($item_id, $patron['agency']);
            $additResponse = $this->sendRequest($additRequest);
            $isbn = $this->useXPath($additResponse,
                'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicRecordId/BibliographicRecordIdentifier');
            $bib_id = $this->getFirstXPathMatchAsString($additResponse,
                    'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
            $author = $this->useXPath($additResponse,
                'LookupItemResponse/ItemOptionalFields/BibliographicDescription/Author');
            $title = $this->useXPath($additResponse,
                'LookupItemResponse/ItemOptionalFields/BibliographicDescription/Title');
            if (empty($title) || $title[0] == '')
                $title = $this->useXPath($current, 'Title');
            $mediumType = $this->useXPath($additResponse,
                'LookupItemResponse/ItemOptionalFields/BibliographicDescription/MediumType');

            $dateDue = date('j. n. Y', $parsedDate);

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
                'publication_year' => '', // TODO
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
                'loan_id' => $item_id
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
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronFiscalAccount($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $list = $this->useXPath($response,
            'LookupUserResponse/UserFiscalAccount/AccountDetails');
        $monetaryValue = $this->useXPath($response,
            'LookupUserResponse/UserFiscalAccount/AccountBalance/MonetaryValue');

        $fines = array();
        foreach ($list as $current) {
            $amount = $this->useXPath($current,
                'FiscalTransactionInformation/Amount/MonetaryValue');
            $type = $this->useXPath($current,
                'FiscalTransactionInformation/FiscalTransactionType');
            $date = $this->useXPath($current, 'AccrualDate');
            $desc = $this->useXPath($current,
                'FiscalTransactionInformation/FiscalTransactionDescription');
            $parsedDate = strtotime((string) $date[0]);
            $date = date('j. n. Y', $parsedDate);
            $amount_int = (int) $amount[0] * (- 1);
            $sum += $amount_int;

            $fines[] = array(
                'amount' => (string) $amount_int,
                'checkout' => $date,
                'fine' => (string) $desc[0],
                'balance' => (string) $sum,
                'createdate' => '',
                'duedate' => '',
                'id' => (string) $type[0]
            );
        }
        if (empty($fines) && ! empty($monetaryValue)) $fines[] = array(
                'amount' => (string) $monetaryValue[0],
                'balance' => (string) $monetaryValue[0]
            );
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
            $type = $this->useXPath($current, 'RequestType');
            $id = $this->useXPath($current,
                'BibliographicId/BibliographicItemId/BibliographicItemIdentifier');
            $location = $this->useXPath($current, 'PickupLocation');
            $reqnum = $this->useXPath($current, 'RequestId/RequestIdentifierValue');
            $expire = $this->useXPath($current, 'PickupExpiryDate');
            $create = $this->useXPath($current, 'DatePlaced');
            $position = $this->useXPath($current, 'HoldQueuePosition');
            $item_id = $this->useXPath($current, 'ItemId/ItemIdentifierValue');
            $title = $this->useXPath($current, 'Title');

            // Deal with Zlin.
            if (empty($id)) $id = $this->useXPath($current,
                    'Ext/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
            if (empty($title)) $title = $this->useXPath($current, 'Ext/BibliographicDescription/Title');

            $bib_id = empty($id) ? null : explode('-', (string) $id[0])[0];
            $parsedDate = empty($create) ? '' : strtotime($create[0]);
            $create = date('j. n. Y', $parsedDate);
            $parsedDate = empty($expire) ? '' : strtotime($expire[0]);
            $expire = date('j. n. Y', $parsedDate);

            $retVal[] = array(
                'type' => empty($type) ? '' : (string) $type[0],
                'id' => empty($bib_id) ? '' : $bib_id,
                'location' => empty($location) ? '' : (string) $location[0],
                'reqnum' => empty($reqnum) ? '' : (string) $reqnum[0],
                'expire' => empty($expire) ? '' : $expire,
                'create' => empty($create) ? '' : $create,
                'position' => empty($position) ? '' : (string) $position[0],
                'available' => false, // true means item is ready for check out
                'item_id' => empty($item_id) ? '' : (string) $item_id[0],
                'barcode' => empty($item_id) ? '' : (string) $item_id[0],
                'volume' => '',
                'publication_year' => '',
                'title' => empty($title) ? '' : (string) $title[0],
                'isbn' => '',
                'issn' => '',
                'oclc' => '',
                'upc' => ''
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
        $group = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserPrivilege/UserPrivilegeDescription');

        $rawExpire = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/UserPrivilege/ValidToDate');

        $expireDate = empty($rawExpire) ? '' : date('j. n. Y',
            strtotime((string) $rawExpire[0]));

        $blocksParsed = $this->useXPath($response,
            'LookupUserResponse/UserOptionalFields/BlockOrTrap/BlockOrTrapType');

        $logo = $this->logo;

        foreach ($blocksParsed as $block) {
            if (! empty($logo)) {
                if (! empty($blocks[$logo]))
                    $blocks[$logo] .= ", " . (string) $block;
                else
                    $blocks[$logo] = (string) $block;
            } else
                $blocks[] = (string) $block;
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
            'group' => empty($group) ? '' : (string) $group[0],
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
        $_ENV['exceptions']['ncip'][] = $message;
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
}
