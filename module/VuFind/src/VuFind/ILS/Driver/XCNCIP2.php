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
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use DOMDocument;

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class XCNCIP2 extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    protected $NCIPResponder = 'http://localhost:8080/aleph/NCIPResponder';

    protected $requests = null;

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
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
            throw new ILSException('Configuration needs to be set.');
        }
        $this->requests = new Requests();
    }

    /**
     * Add values to an HTTP query string.
     *
     * @param string $url    URL so far
     * @param array  $params Parameters to add
     *
     * @return string
     */
    protected function appendQueryString($url, $params)
    {
        if ($params == null) {
            return $url; // nothing to append
        }
        $sep = (strpos($url, "?") === false)?'?':'&';
        foreach ($params as $key => $value) {
            $url.= $sep . $key . "=" . urlencode($value);
            $sep = "&";
        }
        return $url;
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml XML request document
     *
     * @return object     SimpleXMLElement parsed from response
     */
    protected function sendRequest($xml)
    {
        // TODO: delete this part - begin
        // This is only for development purposes.
        if (!$this->isValidXMLAgainstXSD($xml)) {
            throw new ILSException('Not valid XML request!');
        }
        // delete this part - end

        // Make the NCIP request:
        try {
            $client = $this->httpService->createClient($this->NCIPResponder);
            $client->setRawBody($xml);
            $client->setEncType('text/xml');
            $client->setMethod('POST');
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        // Process the NCIP response:
        $body = $result->getBody();;
        $response = @simplexml_load_string($body);

        if (!is_a($response, 'SimpleXMLElement')) {
            throw new ILSException("Problem parsing XML");
        }
        $response->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');

        if (!$this->isValidXMLAgainstXSD($response)) {
            throw new ILSException('Not valid XML response!');
        }

        if (!$this->isCorrect($response)) {
            // TODO chcek problem type
            throw new ILSException('Problem has occured!');
        }
        return $response;
    }

/********************************************* VuFind methods **********************************************/

    /**
     * Cancel Holds
     *
     * Cancels a list of holds for a specific patron.
     *
     * @param array $cancelDetails - array with two keys: patron (array from patronLogin method) and
     *                  details (an array of strings returned by the driver's getCancelHoldDetails method)
     *
     * @throws ILSException
     * @return array    Status of canceled holds.
     */
    public function cancelHolds($cancelDetails)
    {
        $items = array();
        foreach ($cancelDetails['details'] as $recent)
        {
            $request = $this->requests->cancelHolds($recent, $cancelDetails['patron']['id']);
            $response = $this->sendRequest($request);

            $item_id = $response->xpath('ns1:CancelRequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue');
            $items[(string)$item_id[0]] = array(
                'success' => true,
                'status' => '',
                'sysMessage' => '',
            );
        }
        $retVal = array(
            'count' => count($items),
            'items' => $items,
        );
        return $retVal;
    }

    public function checkRequestIsValid()
    {
        throw new ILSException('Method not implemented!');
    }

    public function findReserves()
    {
        throw new ILSException('Method not implemented!');
    }

    /**
     * Get Cancel Hold Details
     *
     * This method returns a string to use as the input form value for cancelling each hold item.
     *
     * @param array $holdDetails - One of the individual item arrays returned by the getMyHolds method.
     *
     * @throws ILSException
     * @return string   Used as the input value for cancelling each hold item;
     *                  any data needed to identify the hold –
     *                  the output will be used as part of the input to the cancelHolds method.
     */
    public function getCancelHoldDetails($holdDetails)
    {
        throw new ILSException('Method not implemented!');
        /*$request = $this->requests->getCancelHoldDetails($holdDetails);
        $response = $this->sendRequest($request);
        $array = array();
        return $array;*/
    }

    public function getCancelHoldLink()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getConfig()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getCourses()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getDefaultPickUpLocation()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getDepartments()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getFunds()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getHoldDefaultRequiredDate()
    {
        throw new ILSException('Method not implemented!');
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings.
     * @param array  $patron Patron data.
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        do {
            if (isset($nextItemToken[0]))
                $request = $this->requests->getHolding(array($id), (string)$nextItemToken[0]);
            else {
                $request = $this->requests->getHolding(array($id));
                $all_bibinfo = [];
            }
            $response = $this->sendRequest($request);

            $new_bibinfo = $response->xpath(
                    'ns1:LookupItemSetResponse/ns1:BibInformation'
            );
            $all_bibinfo = array_merge($all_bibinfo, $new_bibinfo);

            $nextItemToken = $response->xpath(
                    'ns1:LookupItemSetResponse/ns1:NextItemToken'
            );
        } while ($this->isValidToken($nextItemToken));

        // Build the array of holdings:
        $holdings = array();
        foreach ($all_bibinfo as $current) {
            $holdings[] = $this->getHoldingForChunk($current);
        }
        return $holdings;
    }

    /* Deprecated function. */
    public function getHoldings()
    {
        throw new ILSException('Method not implemented!');
        throw new ILSException('Function is deprecated!');
    }

    public function getHoldLink()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getInstructors()
    {
        throw new ILSException('Method not implemented!');
    }

    /**
     * Get Patron fines
     *
     * This method queries the ILS for a patron's current fines.
     *
     * @param array $patron The patron array
     *
     * @return array        Array of arrays containing fines information.
     */
    public function getMyFines($patron)
    {
        $request = $this->requests->getMyFines($patron);
        $response = $this->sendRequest($request);

        $list = $response->xpath('ns1:LookupUserResponse/ns1:UserFiscalAccount');

        $fines = array();
        foreach ($list as $current) {
            $amount = $current->xpath('ns1:AccountBalance/ns1:MonetaryValue');
            $desc = $current->xpath('ns1:AccountDetails/ns1:FiscalTransactionInformation/ns1:FiscalTransactionType');
            $balance = $current->xpath('ns1:AccountDetails/ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue');
            $date = $current->xpath('ns1:AccountDetails/ns1:AccrualDate');
            /* This is an item ID, not a bib ID, so it's not actually useful:
             $tmp = $current->xpath(
                     'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
                     'ns1:ItemId/ns1:ItemIdentifierValue'
             );
            $id = (string)$tmp[0];
            */
            $fines[] = array(
                'amount' => (string)$amount[0],
                'checkout' => '',
                'fine' => (string)$desc[0],
                'balance' => (string)$balance[0],
                'createdate' => (string)$date[0],
                'duedate' => '',
                'id' => '',
            );
        }
        return $fines;
    }

    /**
     * Get Patron's current holds - books which are reserved.
     *
     * @param array $patron The patron array
     *
     * @return array        Array of arrays, one for each hold.
     */
    public function getMyHolds($patron)
    {
        $request = $this->requests->getMyHolds($patron);
        $response = $this->sendRequest($request);

        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');

        foreach ($list as $current) {
            $type = $current->xpath('ns1:RequestType');
            $id = $current->xpath('ns1:BibliographicId/ns1:BibliographicItemId/ns1:BibliographicItemIdentifier');
            $location = $current->xpath('ns1:PickupLocation');
            $reqnum = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $expire = $current->xpath('ns1:PickupExpiryDate');
            $create = $current->xpath('ns1:DatePlaced');
            $position = $current->xpath('ns1:HoldQueuePosition');
            $item_id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $title = $current->xpath('ns1:Title');
            $retVal[] = array(
                'type' => (string)$type[0],
                'id'  => (string)$id[0],
                'location' => (string)$location[0],
                'reqnum' => (string)$reqnum[0],
                'expire'     => (string)$expire[0],
                'create' => (string)$create[0],
                'position' => (string)$position[0],
                'available' => '',
                'item_id' => (string)$item_id[0],
                'volume' => '',
                'publication_year' => '',
                'title' => (string)$title[0],
                'isbn' => '',
                'issn' => '',
                'oclc' => '',
                'upc' => '',
            );
        }
        return $retVal;
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
        $request = $this->requests->getMyProfile($patron);
        $response = $this->sendRequest($request);

        $name = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:GivenName'
        );
        $surname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:Surname'
        );
        $address1 = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Street'
        );
        $address2 = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:HouseName'
        );
        $city = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Locality'
        );
        $country = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Country'
        );
        $zip = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:PostalCode'
        );
        $electronicAddress = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:ElectronicAddress'
        );
        foreach ($electronicAddress as $recent)
        {
            if ($recent->xpath('ns1:ElectronicAddressType')[0] == 'phone number')
            {
                $phone = $recent->xpath('ns1:ElectronicAddressData');
            }
        }
        $group = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserPrivilege/' .
                'ns1:UserPrivilegeStatus/ns1:UserPrivilegeStatusType'
        );
        $patron = array(
                'firstname' => (string)$name[0],
                'lastname'  => (string)$surname[0],
                'address1'  => (string)$address1[0],
                'address2'  => (string)$address2[0],
                'city'  => (string)$city[0],
                'country'  => (string)$country[0],
                'zip'  => (string)$zip[0],
                'phone'  => (string)$phone[0],
                'group'  => (string)$group[0],
        );
        return $patron;
    }

    /**
     * Get My Transactions
     *
     * This method queries the ILS for a patron's current checked out items.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of arrays, one for each item checked out by the specified account.
     */
    public function getMyTransactions($patron)
    {
        $request = $this->requests->getMyTransactions($patron);
        $response = $this->sendRequest($request);
        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');

        foreach ($list as $current) {
            $id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $bibliographicId = $current->xpath('ns1:BibliographicId');
            $reminderLevel = $current->xpath('ns1:ReminderLevel');
            $dateDue = $current->xpath('ns1:DateDue');
            $amount = $current->xpath('ns1:Amount');
            $title = $current->xpath('ns1:Title');
            $mediumType = $current->xpath('ns1:MediumType');
            $ext = $current->xpath('ns1:Ext');
            $retVal[] = array(
                'duedate' => (string)$dateDue[0],
                'id'  => (string)$id[0],
                'barcode' => '',
                'renew' => '',
                'renewLimit'     => '',
                'request' => '',
                'volume' => '',
                'publication_year' => '',
                'renewable' => '',
                'message' => '',
                'title' => (string)$title[0],
                'item_id' => '',
                'institution_name' => '',
                'isbn' => '',
                'issn' => '',
                'oclc' => '',
                'upc' => '',
                'borrowingLocation' => '',
            );
        }
        return $retVal;
    }

    public function getNewItems()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getOfflineMode()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getPickUpLocations()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getPurchaseHistory($id)
    {
        throw new ILSException('Method not implemented!');
    }

    public function getRenewDetails()
    {
        throw new ILSException('Method not implemented!');
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings.
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        // TODO
        // For now, we'll just use getHolding, since getStatus should return a
        // subset of the same fields, and the extra values will be ignored.
        $tmp[] = array_slice($this->getHolding($id)[0], 0, 6);
        return $tmp;
    }

    /**
     * Get Statuses
     *
     * This method calls getStatus for an array of records.
     *
     * @param array Array of bibliographic record IDs.
     *
     * @throws ILSException
     * @return array    Array of return values from getStatus.
     */
    public function getStatuses($ids)
    {
        $retVal = array();
        foreach ($ids as $recent)
        {
            $retVal[] = $this->getStatus($recent);
        }
        return $retVal;
    }

    public function getSuppressedAuthorityRecords()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getSuppressedRecords()
    {
        throw new ILSException('Method not implemented!');
    }

    public function hasHoldings()
    {
        throw new ILSException('Method not implemented!');
    }

    public function loginIsHidden()
    {
        throw new ILSException('Method not implemented!');
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed        Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $request = $this->requests->patronLogin($username, $password);
        $response = $this->sendRequest($request);
        $id = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
        $firstname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:GivenName'
        );

        $lastname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:Surname'
        );
        $email = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                'ns1:ElectronicAddress/ns1:ElectronicAddressData'
        );
        if (!empty($id)) {
            $patron = array(
                    'id' => (string)$id[0],
                    'firstname' => (string)$firstname[0],
                    'lastname'  => (string)$lastname[0],
                    'cat_username' => $username,
                    'cat_password' => $password,
                    'email' => (string)$email[0],
                    'major' => null,
                    'college' => null,
            );
            return $patron;
        }
        return null;
    }

    public function placeHold()
    {
        throw new ILSException('Method not implemented!');
    }

    public function renewMyItems()
    {
        throw new ILSException('Method not implemented!');
    }

    public function renewMyItemsLink()
    {
        throw new ILSException('Method not implemented!');
    }

    /* zdedena public function setConfig()
    {
        throw new ILSException('Method not implemented!');
    }*/

    public function supportsMethod()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getMyStorageRetrievalRequests()
    {
        throw new ILSException('Method not implemented!');
    }

    public function checkStorageRetrievalRequestIsValid()
    {
        throw new ILSException('Method not implemented!');
    }

    public function placeStorageRetrievalRequest()
    {
        throw new ILSException('Method not implemented!');
    }

    public function cancelStorageRetrievalRequests()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getCancelStorageRetrievalRequestDetails()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getMyILLRequests()
    {
        throw new ILSException('Method not implemented!');
    }

    public function checkILLRequestIsValid()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getILLPickupLibraries()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getILLPickupLocations()
    {
        throw new ILSException('Method not implemented!');
    }

    public function placeILLRequest()
    {
        throw new ILSException('Method not implemented!');
    }

    public function cancelILLRequests()
    {
        throw new ILSException('Method not implemented!');
    }

    public function getCancelILLRequestDetails()
    {
        throw new ILSException('Method not implemented!');
    }

/********************************************* Additional methods **********************************************/

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param array $current Current XCItemAvailability chunk.
     *
     * @return array
     */
    protected function getHoldingForChunk($current)
    {
        // Maintain an internal static count of line numbers:
        static $number = 1;

        // Extract details from the XML:
        $status = $current->xpath(
                'ns1:HoldingsSet/ns1:ItemInformation/' .
                'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];

        $id = $current->xpath(
                'ns1:BibliographicId/ns1:BibliographicItemId/' .
                'ns1:BibliographicItemIdentifier'
        );

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
        $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
        $location = '';
        foreach ($locationNodes as $curLoc) {
            $type = $curLoc->xpath('ns1:LocationType');
            if ((string)$type[0] == 'Permanent') {
                $tmp = $curLoc->xpath('ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue');
            }
            else {
                $tmp[0] = 'temporary unknown';
            }
            $location = (string)$tmp[0];
        }

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
        //$holdCallNo = (string)$holdCallNo[0];
        // TODO holdCallNo and itemCallNO
        $itemCallNo = $current->xpath(
                'ns1:HoldingsSet/ns1:ItemInformation/' .
                'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        //$itemCallNo = (string)$itemCallNo[0];

        // Build return array:
        return array(
            'id' => empty($id) ? '' : (string)$id[0],
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => empty($itemCallNo) ? (string)$holdCallNo[0] : (string)$itemCallNo[0],
            'duedate' => '',        // not supported
            'returnDate' => '',
            'number' => $number,
            'requests_placed' => '',
            // XC NCIP does not support barcode, but we need a placeholder here to display anything on the record screen:
            'barcode' => 'placeholder' . $number++,
            'notes'        => '',
            'summary'        => '',
            'supplements'        => '',
            'indexes'        => '',
            'is_holdable'        => '',
            'holdtype'        => '',
            'addLink'        => '',
            'item_id'        => '',
            'holdOverride'        => '',
            'addStorageRetrievalRequestLink'        => '',
            'addILLRequestLink'        => '',
        );
    }

    /**
     * Validate XML against XSD schema.
     *
     * @param string $XML or SimpleXMLElement $XML
     * @param $path_to_XSD
     *
     * @throws ILSException
     * @return boolean Returns true, if XML is valid.
     */
    protected function isValidXMLAgainstXSD($XML, $path_to_XSD =
            './module/VuFind/tests/fixtures/ils/xcncip2/schemas/v2.02.xsd')
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);  // Begin - Disable xml error messages.
        if (is_string($XML))
            $doc->loadXML($XML);
        else if (get_class($XML) == 'SimpleXMLElement')
            $doc->loadXML($XML->asXML());
        else
            throw new ILSException('Expected SimpleXMLElement or string containing XML.');
        libxml_clear_errors();  // End - Disable xml error messages.
        return $doc->schemaValidate($path_to_XSD);
    }

    /**
     * Check response from NCIP responder.
     * Check if $response is not containing problem tag.
     *
     * @param $response SimpleXMLElement Object
     *
     * @return boolean Returns true, if response is without problem.
     */
    protected function isCorrect($response)
    {
        $problem = $response->xpath('ns1:Problem');
        if ($problem == null) return true;
        //print_r($problem[0]->AsXML());
        return false;
    }

    /**
     * Validate next item token.
     * Check if $nextItemToken was set and contains data.
     *
     * @param array, at index [0] SimpleXMLElement Object
     *
     * @return boolean Returns true, if token is valid.
     */
    protected function isValidToken($nextItemToken)
    {
        if (isset($nextItemToken[0])) {
            if ($nextItemToken[0] != '')
                return true;
        }
        return false;
    }
}

/**
 * Building NCIP requests version 2.02.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class Requests
{
    /**
     * Build NCIP request XML for cancel holds.
     *
     * @param array  $cancelDetails     Patron's information and details about cancel request.
     *
     * @return string            XML request
     */
    public function cancelHolds($itemID, $patronID)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ns1:version' .
                '="http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd"><ns1:CancelRequestItem>';
        $xml .= '<ns1:UserId><ns1:UserIdentifierValue>' . $patronID .
            '</ns1:UserIdentifierValue></ns1:UserId>';
        $xml .= '<ns1:ItemId><ns1:ItemIdentifierValue>' . $itemID . '</ns1:ItemIdentifierValue></ns1:ItemId>';
        $xml .= '<ns1:RequestType>cancel</ns1:RequestType>';
        $xml .= '</ns1:CancelRequestItem></ns1:NCIPMessage>';
        return $xml;
    }

    /**
     * Build NCIP request XML for identify hold.
     *
     * @param array  $idList     IDs to look up.
     *
     * @return string            XML request
     */
    public function getCancelHoldDetails($holdDetails)
    {
        throw new ILSException('NCIP request not implemented!');
    }

	/**
	 * Build NCIP request XML for item status information.
	 *
	 * @param array  $idList     IDs to look up.
	 * @param string $resumption Resumption token (null for first page of set).
	 *
	 * @return string            XML request
	 */
	public function getHolding($idList, $resumption = null)
	{
		// Build a list of the types of information we want to retrieve:
		$desiredParts = array(
				'Bibliographic Description',
				'Circulation Status',
				'Electronic Resource',
				'Hold Queue Length',
				'Item Description',
				'Item Use Restriction Type',
				'Location'
		);
		// Start the XML:
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
				'<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ns1:version' .
				'="http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd"><ns1:LookupItemSet>';
		// Add the ID list:
		foreach ($idList as $id) {
			$xml .= '<ns1:BibliographicId>' .
					'<ns1:BibliographicItemId>' .
					'<ns1:BibliographicItemIdentifier>' .
					htmlspecialchars($id) .
					'</ns1:BibliographicItemIdentifier>' .
					'</ns1:BibliographicItemId>' .
					'</ns1:BibliographicId>';
		}
		// Add the desired data list:
		foreach ($desiredParts as $current) {
			$xml .= '<ns1:ItemElementType>' .
					htmlspecialchars($current) . '</ns1:ItemElementType>';
		}
		// Add resumption token if necessary:
		if (!empty($resumption)) {
			$xml .= '<ns1:NextItemToken>' . htmlspecialchars($resumption) .
			'</ns1:NextItemToken>';
		}
		// Close the XML and send it to the caller:
		$xml .= '</ns1:LookupItemSet></ns1:NCIPMessage>';
		return $xml;
	}

	/**
	 * Build the NCIP request XML to get patron's fines.
	 *
	 * @param array $patron The patron array
	 *
	 * @return string        NCIP request XML
	 */
	public function getMyFines($patron)
	{
	    $extras = array('<ns1:UserFiscalAccountDesired/>');
	    return $this->getMyProfile($patron, $extras);
	}

	/**
	 * Build the NCIP request XML to get patron's current holds - books which are reserved.
	 *
	 * @param array $patron The patron array
	 *
	 * @return string        NCIP request XML
	 */
	public function getMyHolds($patron)
	{
	    $extras = array('<ns1:RequestedItemsDesired/>');
	    return $this->getMyProfile($patron, $extras);
	}

    /**
     * Build the NCIP request XML to get patron's details.
     *
     * @param array $patron    The patron array
     *
     * @return string        NCIP request XML
     */
    public function getMyProfile($patron, $extras = null)
    {
        if ($extras == null)
        {
            $extras = array(
                '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'Name Information' .
                '</ns1:UserElementType>'.
                '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'User Address Information' .
                '</ns1:UserElementType>'
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_0.xsd">' .
                '<ns1:LookupUser>' .
                '<ns1:UserId>' .
                '<ns1:UserIdentifierValue>' .
                htmlspecialchars($patron['id']) .
                '</ns1:UserIdentifierValue>' .
                '</ns1:UserId>' .
                implode('', $extras) .
                '</ns1:LookupUser>' .
                '</ns1:NCIPMessage>';
    }

    /**
     * Build the NCIP request XML to get patron's current checked out items.
     *
     * @param array $patron    The patron array
     *
     * @return string        NCIP request XML
     */
    public function getMyTransactions($patron)
    {
        $extras = array('<ns1:LoanedItemsDesired/>');
        return $this->getMyProfile($patron, $extras);
    }

    /**
     * Build the request XML to log in a user.
     *
     * @param string $username Username for login
     * @param string $password Password for login
     * @param string $extras   Extra elements to include in the request
     *
     * @return string          NCIP request XML
     */
    public function patronLogin($username, $password)
    {
        $extras = array(
                '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'Name Information' .
                '</ns1:UserElementType>'.
                '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'User Address Information' .
                '</ns1:UserElementType>'
        );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
                'xsd/ncip_v2_0.xsd">' .
                '<ns1:LookupUser>' .
                '<ns1:AuthenticationInput>' .
                '<ns1:AuthenticationInputData>' .
                htmlspecialchars($username) .
                '</ns1:AuthenticationInputData>' .
                '<ns1:AuthenticationDataFormatType>' .
                'text' .
                '</ns1:AuthenticationDataFormatType>' .
                '<ns1:AuthenticationInputType>' .
                'Username' .
                '</ns1:AuthenticationInputType>' .
                '</ns1:AuthenticationInput>' .
                '<ns1:AuthenticationInput>' .
                '<ns1:AuthenticationInputData>' .
                htmlspecialchars($password) .
                '</ns1:AuthenticationInputData>' .
                '<ns1:AuthenticationDataFormatType>' .
                'text' .
                '</ns1:AuthenticationDataFormatType>' .
                '<ns1:AuthenticationInputType>' .
                'Password' .
                '</ns1:AuthenticationInputType>' .
                '</ns1:AuthenticationInput>' .
                implode('', $extras) .
                '</ns1:LookupUser>' .
                '</ns1:NCIPMessage>';
    }
}
