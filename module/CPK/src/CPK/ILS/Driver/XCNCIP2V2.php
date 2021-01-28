<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) Moravian Library 2017.
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

use CPK\ILS\Logic\XmlTransformation\JsonXML;
use CPK\ILS\Logic\XmlTransformation\JsonXMLException;
use CPK\ILS\Logic\XmlTransformation\NCIPDenormalizerRouter;
use CPK\ILS\Logic\XmlTransformation\NCIPNormalizer;
use CPK\ILS\Logic\XmlTransformation\NCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\NCIPNormalizerRouter;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Driver\AbstractBase;
use VuFindHttp\HttpServiceAwareInterface;
use VuFindHttp\HttpServiceInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

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
class XCNCIP2V2 extends AbstractBase implements HttpServiceAwareInterface, TranslatorAwareInterface, CPKDriverInterface,
    LoggerAwareInterface, MultiBackendInterface
{

    const AGENCY_ID_DELIMITER = ':';

    const CANCEL_REQUEST_PREFIX = 'req';

    /**
     * @var int
     */
    protected $maximumItemsCount = null;

    /**
     * HTTP service
     *
     * @var HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * @var NCIPRequests
     */
    protected $requests = null;

    /**
     * @var bool
     */
    protected $cannotUseLUIS = false;

    /**
     * @var bool
     */
    protected $hideHoldLinks = false;

    /**
     * @var bool
     */
    protected $hasUntrustedSSL = false;

    /**
     * @var string
     */
    protected $cacert = null;

    /**
     * @var int
     */
    protected $timeout = null;

    /**
     * @var string
     */
    protected $logo = null;

    /**
     * @var string
     */
    protected $agency = '';

    /**
     * @var string
     */
    protected $source = '';

    /**
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = false;

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    const NAMESPACES = array(
        'ns2' => 'https://ncip.knihovny.cz/ILSDI/ncip/2015/extensions',
        'ns1' => 'http://www.niso.org/2008/ncip',
    );

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service
     *            HTTP service
     *
     * @return void
     */
    public function setHttpService(HttpServiceInterface $service)
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

            $this->addEnvironmentalException($message);
            throw new ILSException($message);
        }

        $maximumItemsCount = $this->config['Catalog']['maximumItemsCount'];

        if (!empty($maximumItemsCount))
            $this->maximumItemsCount = intval($maximumItemsCount);
        else
            $this->maximumItemsCount = 20;

        if (isset($this->config['Catalog']['cannotUseLUIS']) && $this->config['Catalog']['cannotUseLUIS'])
            $this->cannotUseLUIS = true;

        if (!empty($this->config['Catalog']['hideHoldLinks']))
            $this->hideHoldLinks = true;

        if (isset($this->config['Catalog']['hasUntrustedSSL']) && $this->config['Catalog']['hasUntrustedSSL'])
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
    }

    /**
     * @param TranslatorInterface $translator
     * @return void
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        if (method_exists($translator, "getTranslator")) {
            $this->translator = $translator->getTranslator();
        } else {
            $this->logger->err("Error setting up translator");
        }
    }

    /**
     * Set logger instance
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $id
     * @param array|null $patron
     * @return array|mixed
     * @throws JsonXMLException
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getStatus($id, $patron);
    }

    /**
     * @param string $id
     * @return array
     */
    public function getPurchaseHistory($id)
    {
        return array();
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml NCIP request
     * @return JsonXML
     * @throws ILSException
     */
    protected function sendRequest($xml)
    {
        $this->denormalizeRequest($xml);


        // Make the NCIP request:
        try {
            $this->httpService->setDefaultAdapter(new \Zend\Http\Client\Adapter\Socket());
            $client = $this->httpService->createClient($this->config['Catalog']['url']);
            //$client->setRawBody($jsonXML->toXmlString());
            $client->setRawBody($xml);
            $client->setEncType('application/xml; "charset=utf-8"');
            $client->setMethod('POST');
            $client->setHeaders(array(
                'Content-Type' => 'application/xml'
            ));

            if (isset($this->timeout))
                $client->setOptions(array(
                    'timeout' => $this->timeout
                ));

            if ((!empty($this->config['Catalog']['username'])) && (!empty($this->config['Catalog']['password']))) {

                $user = $this->config['Catalog']['username'];
                $password = $this->config['Catalog']['password'];
                $client->setAuth($user, $password);
            }

            if ($this->hasUntrustedSSL) {
                // Do not verify SSL certificate
                $client->setOptions(array(
                    'adapter' => 'Zend\Http\Client\Adapter\Curl',
                    'curloptions' => array(
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_SSL_VERIFYPEER => false
                    )
                ));
            } elseif (isset($this->cacert)) {
                $client->setOptions(array(
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

            $this->addEnvironmentalException($message);
            throw new ILSException($message);
        }

        if (!$result->isSuccess()) {
            $message = 'HTTP error: ' . $result->getStatusCode() . ' ' . $result->getReasonPhrase();

            $this->addEnvironmentalException($message);
            throw new ILSException($message);
        }

        // Process the NCIP response:
        $body = $result->getBody();

        $response = $this->normalizeResponse($body);

        if ($problem = $this->getProblem($response)) {

            $message = 'Problem recieved in XCNCIP2 Driver. Content:' . str_replace('\n', '<br/>', $problem);

            throw new ILSException($message);
        }

        return $response;
    }

    /**
     * Cancel Holds
     * Cancels a list of holds for a specific patron.
     *
     * @param array $cancelDetails
     *            - array with two keys: patron (array from patronLogin method) and
     *            details (an array of strings returned by the driver's getCancelHoldDetails method)
     *
     * @throws ILSException
     * @return array Status of canceled holds.
     * @throws JsonXMLException
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

                    $request = $this->requests->cancelRequestItemUsingItemId($patron, $recent);
                    try {
                        $this->sendRequest($request);
                    } catch (ILSException $e) {
                        $problemOccurred = true;
                        $problemValue = $e->getMessage();
                    }

                    break;
                } else
                    if ($hold['reqnum'] == $recent) { // Biblio-leveled cancel request

                        $request_id = substr($hold['reqnum'], strlen(static::CANCEL_REQUEST_PREFIX));

                        if (!$request_id > 0) {
                            $message = 'XCNCIP2 Driver cannot cancel biblio-leveled request without request id!';

                            $this->addEnvironmentalException($message);
                            throw new ILSException($message);
                        }

                        $desiredRequestFound = true;

                        $request = $this->requests->cancelRequestItemUsingRequestId($patron, $request_id);
                        try {
                            $this->sendRequest($request);
                        } catch (ILSException $e) {
                            $problemOccurred = true;
                            $problemValue = $e->getMessage();
                        }

                        break;
                    }
            }

            $didWeTheJob = $desiredRequestFound && !$problemOccurred;
            if (!$didWeTheJob)
                $this->addEnvironmentalException("Item has not been canceled.");

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
                $rawDate = $response->get('RenewItemResponse', 'DateForReturn');
                $date = $this->parseDate($rawDate);
                $result[$item] = array(
                    'success' => true,
                    'new_date' => $date,
                    'new_time' => $date, // used the same like previous
                    'item_id' => $item,
                    'sysMessage' => ''
                );
            } catch (ILSException | JsonXMLException $e) {
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

    /**
     * Returns payment URL from config
     *
     * @param $patron
     * @param $fine
     * @return string | null
     */
    public function getPaymentURL($patron, $fine)
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
     * @return string Used as the input value for cancelling each hold item;
     *         any data needed to identify the hold –
     *         the output will be used as part of the input to the cancelHolds method.
     */
    public function getCancelHoldDetails($holdDetails)
    {
        if (strpos($holdDetails['item_id'], '.') != null) {
            $holdDetails['item_id'] = substr($holdDetails['item_id'], strpos($holdDetails['item_id'], '.') + 1); // strip prefix
        }
        if ($holdDetails['item_id'] == 'N/A')
            $holdDetails['item_id'] = '';
        return empty($holdDetails['item_id']) ? $holdDetails['reqnum'] : $holdDetails['item_id'];
    }

    /**
     * @param $holdDetails
     * @return array
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $status = true;
        $message = '';
        $request = $this->requests->requestItem($patron, $holdDetails);
        try {
            $this->sendRequest($request);
        } catch (ILSException $ex) {
            $status = false;
            $message = 'hold_error_fail';
        }
        return array(
            'success' => $status,
            'sysMessage' => $message,
            'source' => $patron['agency']
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

    /**
     * @param null $patron
     * @param null $holdInformation
     * @return array
     * @throws ILSException
     * @throws JsonXMLException
     */
    public function getPickUpLocations($patron = null, $holdInformation = null)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->lookupAgency($patron);
        $response = $this->sendRequest($request);

        $retVal = array();

        $locations = $response->getArray('LookupAgencyResponse', 'AgencyAddressInformation');
        foreach ($locations as $location) {

            $id = $response->getRelative(
                $location,
                'AgencyAddressRoleType'
            );

            $name = $response->getRelative(
                $location,
                'PhysicalAddress',
                'UnstructuredAddress',
                'UnstructuredAddressType'
            );

            $address = $response->getRelative(
                $location,
                'PhysicalAddress',
                'UnstructuredAddress',
                'UnstructuredAddressData'
            );

            if ($name !== null && $name == 'Newline-Delimited Text')
                continue;

            $retVal[] = array(
                'locationID' => empty($id) ? '' : $id,
                'locationDisplay' => empty($name) ? '' : $name,
                'locationAddress' => empty($address) ? '' : $address
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

    /**
     * @param $patron
     * @param $page
     * @param $perPage
     * @return array
     * @throws ILSException
     * @throws JsonXMLException
     */
    public function getMyHistoryPage($patron, $page, $perPage)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronHistory($patron, $page, $perPage);
        $response = $this->sendRequest($request);

        $totalPages = $response->get('LookupUserResponse', 'Ext', 'LoanedItemsHistory', 'LastPage');
        $items = $response->getArray('LookupUserResponse', 'Ext', 'LoanedItemsHistory', 'LoanedItem');
        $inc = 0;

        $historyPage = array();

        foreach ($items as $item) {

            $inc++;

            $title = $response->getRelative(
                $item,
                'Title'
            );

            $itemId = $response->getRelative(
                $item,
                'ItemId',
                'ItemIdentifierValue'
            );

            $dueDate = $response->getRelative(
                $item,
                'DateDue'
            );
            $dueDate = $this->parseDate($dueDate);

            $additRequest = $this->requests->lookupItem($itemId, $patron);
            try {
                $additResponse = $this->sendRequest($additRequest);
                $bib_id = $additResponse->get('LookupItemResponse', 'ItemOptionalFields', 'BibliographicDescription', 'BibliographicItemId', 'BibliographicItemIdentifier');
                if (empty($title)) {
                    $title = $additResponse->get('LookupItemResponse', 'ItemOptionalFields', 'BibliographicDescription', 'Title');
                }
            } catch (ILSException $e) {
            }

            $historyPage[] = array(
                'id' => empty($bib_id) ? '' : $bib_id,
                'item_id' => empty($itemId) ? '' : $itemId,
                'barcode' => '',
                'title' => empty($title) ? $this->translator->translate('unknown_title') : $title,
                'author' => '',
                'reqnum' => '',
                'loandate' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'returned' => '',
                'publicationYear' => '',
                'rowNo' => ($page - 1) * $perPage + $inc
            );
        }

        return [
            'historyPage' => empty($historyPage) ? [] : $historyPage,
            'totalPages' => empty($totalPages) ? 0 : (int)$totalPages
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
     * @param array $patron
     * @return mixed On success, an associative array with the following keys:
     *         id, availability (boolean), status, location, reserve,
     *         callnumber.
     * @throws JsonXMLException
     */
    public function getStatus($id, $patron = [])
    {
        // id may have the form of "Id:agencyId"
        list ($id, $agencyId) = $this->splitAgencyId($id);

        $request = $this->requests->lookupItem($id, $patron);
        try {
            $response = $this->sendRequest($request);
        } catch (ILSException $e) {
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
        $status = $response->get('LookupItemResponse', 'ItemOptionalFields', 'CirculationStatus');

        $locations = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'Location');
        foreach ($locations as $locElement) {
            $level = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameLevel');
            $value = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameValue');
            if ($value !== null) {
                if ($level == '1')
                    $department = $value;
                if ($level == '2')
                    $collection = $value;
            }
        }

        $numberOfPieces = $response->get('LookupItemResponse', 'ItemOptionalFields', 'ItemDescription', 'NumberOfPieces');

        $holdQueue = $response->get('LookupItemResponse', 'ItemOptionalFields', 'HoldQueueLength');

        if ($status == 'On Loan') {
            $dueDate = $response->get('LookupItemResponse', 'ItemOptionalFields', 'DateDue');
            $dueDate = $this->parseDate($dueDate);
        } else {
            $dueDate = false;
        }

        $label = $this->determineLabel($status);

        $itemRestriction = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'ItemUseRestrictionType');
        $addLink = $this->isLinkAllowed($status, $itemRestriction);

        return array(
            'id' => empty($id) ? "" : $id,
            'availability' => empty($itemRestriction) ? '' : $itemRestriction,
            'status' => empty($status) ? '' : $status,
            'location' => '',
            'sub_lib_desc' => '',
            'collection' => isset($collection) ? $collection : '',
            'department' => isset($department) ? $department : '',
            'number' => empty($numberOfPieces) ? '' : $numberOfPieces,
            'requests_placed' => empty($holdQueue) ? "" : $holdQueue,
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

    /**
     * @param $id
     * @param $bibId
     * @param $patron
     * @return mixed
     * @throws JsonXMLException
     */
    public function getItemStatus($id, $bibId, $patron)
    {
        return $this->getStatus($id, $patron);
    }

    /**
     * Get Statuses
     *
     * This method calls getStatus for an array of records.
     *
     * @param array $ids
     * @param array $patron
     * @param array $filter
     * @param array $bibId
     * @param null $nextItemToken
     * @return array
     * @throws ILSException
     * @throws JsonXMLException
     */
    public function getStatuses($ids, $patron = [], $filter = [], $bibId = [], $nextItemToken = null)
    {
        if (empty($bibId))
            $this->cannotUseLUIS = true;

        if ($this->cannotUseLUIS) {
            // If we cannot use LUIS we will parse only the first one
            $retVal = [];
            $retVal[] = $this->getStatus(reset($ids), $patron);
            return $retVal;
        } else {
            $request = $this->requests->LUISBibItemV2($bibId, $nextItemToken, $this, $patron);
            $response = $this->sendRequest($request);
            return $this->handleStatuses($response);

        }
    }

    /**
     * @param JsonXML $response
     * @return array
     * @throws JsonXMLException
     */
    private function handleStatuses(JsonXML $response)
    {
        $retVal = array();

        $bib_id = $response->get('LookupItemSetResponse', 'BibInformation', 'BibliographicId', 'BibliographicItemId', 'BibliographicItemIdentifier');

        $nextItemToken = $response->get('LookupItemSetResponse', 'NextItemToken');

        $items = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation');
        foreach ($items as $itemInformation) {
            $collection = '';
            $department = '';

            $item_id = $response->getRelative($itemInformation, 'ItemId', 'ItemIdentifierValue');
            $status = $response->getRelative($itemInformation, 'ItemOptionalFields', 'CirculationStatus');

            if ($status == 'On Loan') {
                $dueDate = $response->getRelative($itemInformation, 'DateDue');
                $dueDate = $this->parseDate($dueDate);
            } else {
                /* 'On Order' means that item is ordered from stock and will be loaned, but we don't know dueDate yet. */
                $dueDate = false;
            }

            $holdQueue = $response->getRelative($itemInformation, 'ItemOptionalFields', 'HoldQueueLength');

            $locations = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'Location');
            foreach ($locations as $locElement) {
                $level = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameLevel');
                $value = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameValue');
                if (!empty($value)) {
                    if ($level == '1')
                        $department = $value;
                    if ($level == '2')
                        $collection = $value;
                    if (empty($department) && $level == '4') {
                        $department = $value;
                    }
                }
            }

            $label = $this->determineLabel($status);

            $itemRestriction = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'ItemUseRestrictionType');

            $addLink = $this->isLinkAllowed($status, $itemRestriction, $itemInformation);

            $retVal[] = array(
                'id' => empty($bib_id) ? "" : $bib_id,
                'availability' => empty($itemRestriction) ? '' : $itemRestriction[0],
                'status' => empty($status) ? "" : $status,
                'location' => '',
                'collection' => $collection,
                'sub_lib_desc' => '',
                'department' => $department,
                'requests_placed' => !isset($holdQueue) ? "" : $holdQueue,
                'item_id' => empty($item_id) ? "" : $item_id,
                'label' => $label,
                'hold_type' => isset($holdQueue) && intval($holdQueue) > 0 ? 'Recall This' : 'Place a Hold',
                'restrictions' => '',
                'duedate' => empty($dueDate) ? '' : $dueDate,
                'next_item_token' => is_string($nextItemToken) ? $nextItemToken : null,
                'addLink' => $addLink
            );
        }
        return $retVal;
    }

    /**
     * Determines the color of item status' frame.
     *
     * @param \SimpleXMLElement $status
     * @return string $label
     */
    protected function determineLabel($status)
    {
        $status = empty($status) ? '' : $status;
        $label = 'label-danger';
        if (($status === 'Available On Shelf') || ($status === 'Available For Pickup'))
            $label = 'label-success';
        elseif (($status === 'On Loan') || ($status === 'On Order') || ($status === 'In Process') || ($status === 'In Transit Between Library Locations'))
            $label = 'label-warning';
        elseif (($status === 'Circulation Status Undefined'))
            $label = 'label-unknown';
        return $label;
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
     * @throws JsonXMLException
     */
    public function getMyTransactions($patron)
    {
        $_ENV['processing'] = 'loaned-items';

        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronLoanedItems($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);
        return $this->handleTransactions($response, $patron);
    }

    /**
     * @param JsonXML $response
     * @param $patron
     * @return array
     * @throws JsonXMLException
     */
    private function handleTransactions(JsonXML $response, $patron)
    {
        $retVal = array();
        $loanedItems = $response->getArray('LookupUserResponse', 'LoanedItem');

        foreach ($loanedItems as $loanedItem) {
            $isbn = $bib_id = $author = $mediumType = null;
            $item_id = $response->getRelative(
                $loanedItem,
                'ItemId',
                'ItemIdentifierValue'
            );

            $dateDue = $response->getRelative(
                $loanedItem,
                'DateDue'
            );

            $title = $response->getRelative(
                $loanedItem,
                'Title'
            );

            $publicationYear = $response->getRelative(
                $loanedItem,
                'Ext',
                'BibliographicDescription',
                'PublicationDate'
            );

            $dueStatus = $this->hasOverdue($dateDue);
            $dateDue = $this->parseDate($dateDue);

            $renewalNotPermitted = $response->getRelative(
                $loanedItem,
                'Ext',
                'RenewalNotPermitted'
            );

            $renewable = $renewalNotPermitted === null;

            $additRequest = $this->requests->lookupItem($item_id, $patron);
            try {
                $additResponse = $this->sendRequest($additRequest);

                $bibliographicDescription = $additResponse->get(
                    'LookupItemResponse',
                    'ItemOptionalFields',
                    'BibliographicDescription'
                );

                $isbn = $additResponse->getRelative(
                    $bibliographicDescription,
                    'BibliographicRecordId',
                    'BibliographicRecordIdentifier'
                );

                $bib_id = $additResponse->getRelative(
                    $bibliographicDescription,
                    'BibliographicItemId',
                    'BibliographicItemIdentifier'
                );

                $author = $additResponse->getRelative(
                    $bibliographicDescription,
                    'Author'
                );

                if (empty($title))
                    $title = $additResponse->getRelative(
                        $bibliographicDescription,
                        'Title'
                    );
            } catch (ILSException $e) {
                // Silent catch
            }

            $retVal[] = array(
                'cat_username' => $patron['cat_username'],
                'duedate' => $dateDue,
                'id' => $bib_id,
                'barcode' => $item_id,
                'request' => '',
                'volume' => '',
                'author' => $author,
                'publication_year' => $publicationYear,
                'renewable' => $renewable,
                'message' => '',
                'title' => $title,
                'item_id' => $item_id,
                'institution_name' => '',
                'isbn' => $isbn,
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
     * @return array|bool Array of arrays containing fines information.
     * @throws ILSException
     * @throws JsonXMLException
     */
    public function getMyFines($patron)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronFiscalAccount($patron);
        $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $accountDetails = $response->getArray('LookupUserResponse', 'UserFiscalAccount', 'AccountDetails');
        $monetaryValue = $response->get('LookupUserResponse', 'UserFiscalAccount', 'AccountBalance', 'MonetaryValue');

        $fines = array();
        $sum = 0;
        $leastOne = false;
        foreach ($accountDetails as $accountDetail) {
            $excluded = false;

            $date = $response->getRelative(
                $accountDetail,
                'AccrualDate'
            );

            $amount = $response->getRelative(
                $accountDetail,
                'FiscalTransactionInformation',
                'Amount',
                'MonetaryValue'
            );

            $desc = $response->getRelative(
                $accountDetail,
                'FiscalTransactionInformation',
                'FiscalTransactionDescription'
            );

            $type = $response->getRelative(
                $accountDetail,
                'FiscalTransactionInformation',
                'FiscalTransactionType'
            );

            $item_id = $response->getRelative(
                $accountDetail,
                'FiscalTransactionInformation',
                'ItemDetails',
                'ItemId',
                'ItemIdentifierValue'
            );

            if ($this->isAncientFee($date))
                $excluded = true; // exclude old fees

            $date = $this->parseDate($date);

            $amount_int = (int)$amount;
            if ($amount_int == 0)
                continue; // remove zero fees

            if (!$excluded)
                $leastOne = true;

            $sum += $amount_int;


            $fine = $this->translator->translate($type);
            $fine .= empty($desc) || gettype($desc) !== 'string' ? '' : " ($desc)";
            $fines[] = array(
                'amount' => (string)$amount_int,
                'checkout' => $date,
                'fine' => $fine,
                'balance' => (string)$sum,
                'createdate' => '',
                'duedate' => '',
                'id' => '',
                'excluded' => $excluded,
                'item_id' => $item_id,
            );

        }
        if (empty($fines) && !empty($monetaryValue) && (int)$monetaryValue != 0) {
            return false;
        }

        if (!empty($fines) && !$leastOne)
            $fines[count($fines) - 1]['excluded'] = false;

        return $fines;
    }

    /**
     * Get Patron's current holds - books which are reserved.
     *
     * @param array $patron
     *            The patron array
     *
     * @return array Array of arrays, one for each hold.
     * @throws ILSException
     * @throws JsonXMLException
     */
    public function getMyHolds($patron)
    {
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);
        $request = $this->requests->patronRequestedItems($patron);
         $response = $this->sendRequest($request);
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);

        $retVal = array();
        $requestedItems = $response->getArray('LookupUserResponse', 'RequestedItem');

        foreach ($requestedItems as $requestedItem) {

            $type = $response->getRelative(
                $requestedItem,
                'RequestType'
            );

            $requestStatusType = $response->getRelative(
                $requestedItem,
                'RequestStatusType'
            );

            $item_id = $response->getRelative(
                $requestedItem,
                'ItemId',
                'ItemIdentifierValue'
            );

            $bibliographicIds = $response->getArrayRelative(
                $requestedItem,
                'BibliographicId'
            );

            $id = null;
            foreach ($bibliographicIds as $bibliographicId) {
                $id = $response->getRelative(
                    $bibliographicId,
                    'BibliographicItemId',
                    'BibliographicItemIdentifier'
                );

                if ($id !== null)
                    break;
            }

            // We need to make sure we have ID ...
            if ($id === null) {

                if ($item_id === null)
                    // No bibliographic ID nor item ID provided, cannot look it up! So just skip this one
                    continue;

                $idRequest = $this->requests->lookupItem($item_id, $patron);
                $idResponse = $this->sendRequest($idRequest);
                $id = $idResponse->get(
                    'LookupItemResponse',
                    'ItemOptionalFields',
                    'BibliographicDescription',
                    'BibliographicItemId',
                    'BibliographicItemIdentifier'
                );
            }

            $title = $response->getRelative(
                $requestedItem,
                'Title'
            );

            $location = $response->getRelative(
                $requestedItem,
                'PickupLocation'
            );

            $reqnum = $response->getRelative(
                $requestedItem,
                'RequestId',
                'RequestIdentifierValue'
            );

            $expire = $response->getRelative(
                $requestedItem,
                'PickupExpiryDate'
            );

            $create = $response->getRelative(
                $requestedItem,
                'DatePlaced'
            );

            $position = $response->getRelative(
                $requestedItem,
                'HoldQueuePosition'
            );

            $cannotCancel = $response->getRelative(
                    $requestedItem,
                    'Ext',
                    'NonReturnableFlag'
                ) || false;

            if ($position === '0')
                $position = null; // hide queue position

            $bib_id = empty($id) ? null : explode('-', $id)[0];
            $create = $this->parseDate($create);
            $expire = $this->parseDate($expire);

            $available = false;
            if ($requestStatusType === 'Available For Pickup') {
                $available = true;
            }

            $retVal[] = array(
                'type' => $type,
                'id' => $bib_id,
                'location' => $location,
                'reqnum' => empty($reqnum) ? '' : static::CANCEL_REQUEST_PREFIX . $reqnum,
                'expire' => $expire,
                'create' => $create,
                'position' => $position,
                'available' => $available, // true means item is ready for check out
                'item_id' => $item_id,
                'barcode' => $item_id,
                'volume' => '',
                'publication_year' => '',
                'title' => $title,
                'isbn' => '',
                'issn' => '',
                'oclc' => '',
                'upc' => '',
                'cannotcancel' => $cannotCancel,
                'fake_id' => $this->source . '.N/A'
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
     * @throws JsonXMLException
     */
    public function getMyProfile($patron)
    {
        // id may have the form of "patronId:agencyId"
        list ($patron['id'], $patron['agency']) = $this->splitAgencyId($patron['id']);

        $request = $this->requests->patronFullInformation($patron);
        $response = $this->sendRequest($request);

        // Merge it back if the agency was specified before ..
        $patron['id'] = $this->joinAgencyId($patron['id'], $patron['agency']);


        $uof = $response->get(
            'LookupUserResponse',
            'UserOptionalFields'
        );

        $structuredName = $response->getRelative(
            $uof,
            'NameInformation',
            'PersonalNameInformation',
            'StructuredPersonalUserName',
            'GivenName'
        );

        $structuredSurname = $response->getRelative(
            $uof,
            'NameInformation',
            'PersonalNameInformation',
            'StructuredPersonalUserName',
            'Surname'
        );

        if (empty($structuredName) && empty($structuredSurname)) {
            $structuredName = $response->getRelative(
                $uof,
                'NameInformation',
                'PersonalNameInformation',
                'UnstructuredPersonalUserName'
            );
        }

        $physicalAddress = $response->getRelative(
            $uof,
            'UserAddressInformation',
            'PhysicalAddress'
        );


        if (!is_null($physicalAddress)) {

            $address1 = $response->getRelative(
                $physicalAddress,
                'StructuredAddress',
                'Street|Line1|District'
            );

            if ($address1 === null) {
                $address1 = $response->getRelative(
                    $physicalAddress,
                    'UnstructuredAddress',
                    'UnstructuredAddressData'
                );
            }

            $address2 = $response->getRelative(
                $physicalAddress,
                'StructuredAddress',
                'HouseName'
            );

            $city = $response->getRelative(
                $physicalAddress,
                'StructuredAddress',
                'Locality|Line2'
            );

            $country = $response->getRelative(
                $physicalAddress,
                'StructuredAddress',
                'Country'
            );

            $zip = $response->getRelative(
                $physicalAddress,
                'StructuredAddress',
                'PostalCode|PostOfficeBox'
            );
        } else {
            $address1 = $address2 = $city = $country = $zip = null;
        }

        $uai = $response->getArrayRelative(
            $uof,
            'UserAddressInformation'
        );

        $phone = $email = null;

        foreach ($uai as $userAddressInformation) {

            $type = $response->getRelative(
                $userAddressInformation,
                'ElectronicAddress',
                'ElectronicAddressType'
            );

            if ($type === null)
                continue;

            $data = $response->getRelative(
                $userAddressInformation,
                'ElectronicAddress',
                'ElectronicAddressData'
            );

            switch ($type) {
                case 'tel':
                    $phone = $data;
                    break;
                case 'mailto':
                    $email = $data;
                    break;
            }
        }

        $userPrivileges = $response->getArrayRelative(
            $uof,
            'UserPrivilege'
        );

        $group = null;
        foreach($userPrivileges as $userPrivilege) {
            $privilegeDescription = $response->getRelative(
                $userPrivilege,
                'UserPrivilegeDescription'
            );

            $privilegeType = $response->getRelative(
                $userPrivilege,
                'AgencyUserPrivilegeType'
            );

            $group = $privilegeDescription;

            if ($privilegeType == "Category")
                break;
        }

        if ($group !== null) {
            $institutionGroupTranslateKey = $this->source . '_group_' . $group;
            $institutionGroupTranslation = $this->translator->translate($institutionGroupTranslateKey);

            if ($institutionGroupTranslation != $institutionGroupTranslateKey)
                $group = $institutionGroupTranslation;
        }

        $rawExpire = $response->getRelative(
            $uof,
            'UserPrivilege',
            'ValidToDate'
        );

        $expireDate = $this->parseDate($rawExpire);

        $blocksOrTraps = $response->getArrayRelative(
            $uof,
            'BlockOrTrap'
        );

        $logo = $this->logo;

        $blocks = array();

        foreach ($blocksOrTraps as $blockOrTrap) {

            $blockOrTrapVal = $response->getRelative(
                $blockOrTrap,
                'BlockOrTrapType'
            );
            $blockOrTrapVal = $this->translator->translate($blockOrTrapVal);

            if (!empty($logo)) {
                if (!empty($blocks[$logo]))
                    $blocks[$logo] .= ", " . $blockOrTrapVal;
                else
                    $blocks[$logo] = $blockOrTrapVal;
            } else
                $blocks[] = $blockOrTrapVal;
        }

        $patron = array(
            'cat_username' => $patron['id'],
            'id' => $patron['id'],
            'firstname' => $structuredName,
            'lastname' => $structuredSurname,
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'country' => $country,
            'zip' => $zip,
            'phone' => $phone,
            'group' => $group,
            'blocks' => $blocks,
            'email' => $email,
            'expire' => $expireDate
        );
        return $patron;
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
        return $this->config['Catalog']['contactPerson'] ?? null;
    }

    /**
     * Check response from NCIP responder.
     * Check if $response is not containing problem tag.
     *
     * @param $response JsonXML
     *
     * @return mixed string Problem | boolean Returns false, if response is without problem.
     */
    protected function getProblem($response)
    {
        foreach ($response as $elementName => $elementValue) {
            if (is_array($elementValue)) {
                foreach ($elementValue as $subelementName => $subelementValue)
                    if (strpos($subelementName, 'Problem') !== false) {
                        return json_encode($subelementValue);
                    }
            }
        }
        return false;
    }

    protected function addEnvironmentalException($message)
    {
        $_ENV['exceptions']['ncip'] = $message;
    }

    /**
     * Validate next item token.
     * Check if $nextItemToken was set and contains data.
     *
     * @param
     *            array, at index [0] \SimpleXMLElement Object
     *
     * @return boolean Returns true, if token is valid.
     */
    protected function isValidToken($nextItemToken)
    {
        if (isset($nextItemToken[0])) {
            return !empty((string)$nextItemToken[0]);
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
        $parsedDate = empty($date) ? null : strtotime($date);
        return ($parsedDate == null) ? '' : date('j. n. Y', $parsedDate);
    }

    protected function hasOverdue($dateDue)
    {
        $parsedDate = empty($dateDue) ? 0 : strtotime($dateDue);
        $today_time = strtotime(date("Y-m-d"));
        $expire_time = strtotime(date('Y-m-d', $parsedDate));
        return ($expire_time < $today_time) ? 'overdue' : false;
    }

    protected function isAncientFee($date)
    {
        $parsedDate = empty($date) ? '' : strtotime($date);
        $fee_time = strtotime(date('Y-m-d', $parsedDate));
        $filter_time = strtotime(date("Y-m-d") . ' -1 year');
        return ($fee_time < $filter_time) ? true : false;
    }

    /**
     * Determines if item's hold link is available.
     *
     * @param string $status
     * @param array $itemRestriction
     * @return boolean $addLink
     */
    protected function isLinkAllowed($status, &$itemRestriction)
    {
        $addLink = true;
        if (
            !empty($this->hideHoldLinks)
            || $status === 'Circulation Status Undefined'
            || $status === 'Not Available'
            || $status === 'Lost'
            || $itemRestriction[0] === 'Not For Loan'
        ) {
            $addLink = false;
        }

        foreach ($itemRestriction as $i => $item) {
            if ($item === 'Orderable') {
                unset($itemRestriction[$i]);
                //reindex array
                $itemRestriction = array_values($itemRestriction);
                $addLink = true;
            } elseif ($item === "Hide Link") {
                $addLink = false;
            }
        }

        return $addLink;
    }

    /**
     * Extract the first data from array of \SimpleXMLElement.
     *
     * @param array of \SimpleXMLElement $elements
     * @return string $data
     */
    protected function extractData($elements)
    {
        $data = '';
        if (!empty($elements)) {
            foreach ($elements as $element) {
                if (!empty($element)) {
                    $data = (string)$element;
                    break;
                }
            }
        }
        return $data;
    }

    /**
     * "Denormalizes" outbound NCIP request.
     *
     * This is needed because every library have implemented NCIP their way.
     *
     * @param string $ncipRequest
     */
    private function denormalizeRequest(string &$ncipRequest)
    {
        $backup_copy_request = $ncipRequest;
        try {
            $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)[3]['function'];
            $this->logger->debug("Denormalizing NCIP request for " . $method . ": \n" . $ncipRequest);

            $denormalizedJsonXmlRequest = $this->getNewNCIPDenormalizer($method)->denormalize($ncipRequest);
            $ncipRequest = $denormalizedJsonXmlRequest->toXmlString();
            $this->logger->debug("Denormalized NCIP response for " . $method . ": \n" . $ncipRequest);
        } catch (JsonXMLException $e) {
            $this->logger->crit("NCIP: Failed to denormalize the request.");
            $this->logger->crit($e->getMessage());
            $this->logger->crit($e->getTraceAsString());

            // Restore the backup copy ..
            $ncipRequest = $backup_copy_request;
        }
    }

    /**
     * Normalizes incoming NCIP message
     *
     * @param string $inboundResponse
     * @return JsonXML
     * @throws ILSException
     */
    private function normalizeResponse(string $inboundResponse)
    {
        try {
            $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)[3]['function'];
            $this->logger->debug("Normalizing NCIP response for " . $method . ": \n" . $inboundResponse);
            $normalizedJsonXmlResponse = $this->getNewNCIPNormalizer($method)->normalize($inboundResponse);
            $this->logger->debug("Normalized NCIP response for " . $method . ": \n" . $normalizedJsonXmlResponse->toXmlString());
            return $normalizedJsonXmlResponse;

        } catch (JsonXMLException $e) {
            $this->logger->crit("NCIP: Failed to normalize the response.");
            $this->logger->crit($e->getMessage());
            $this->logger->crit($e->getTraceAsString());

            // Return unchanged, yet parsed
            try {
                return JsonXML::fabricateFromXmlString($inboundResponse);
            } catch (JsonXMLException $e) {
                throw new ILSException("Malformed XML came from the NCIP server!");
            }

        }
    }

    /**
     * @param $method
     * @return NCIPDenormalizer
     */
    private function getNewNCIPDenormalizer($method)
    {
        $router = new NCIPDenormalizerRouter();

        $normalizer = $router->route($method, $this->requests);

        $normalizer->setLogger($this->logger);

        return $normalizer;
    }

    /**
     * @return bool|NCIPNormalizer|\CPK\ILS\Logic\XmlTransformation\VerbisNCIPNormalizer
     * @internal param $method
     */
    private function getNewNCIPNormalizer($method)
    {
        $router = new NCIPNormalizerRouter();

        $normalizer = $router->route($method, $this->source, $this->agency, $this->requests, $this->translator);

        $normalizer->setLogger($this->logger);

        return $normalizer;
    }

    public function getProlongRegistrationUrl(array $patron)
    {
        // Required for MultiBackend
        return null;
    }
}
