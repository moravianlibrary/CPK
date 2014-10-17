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
use Zend\XmlRpc\Value\String;

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
class XCNCIP2 extends AbstractBase implements
        \VuFindHttp\HttpServiceAwareInterface
{

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    protected $requests = null;

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service
     *            HTTP service
     *
     * @return void
     */
    public function setHttpService (\VuFindHttp\HttpServiceInterface $service)
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
    public function init ()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
        $this->requests = new NCIPRequests();
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml
     *            XML request document
     *
     * @return object SimpleXMLElement parsed from response
     */
    protected function sendRequest ($xml, $testing = false)
    {
        // TODO: delete this part - begin
        // This is only for development purposes.
        if (! $this->isValidXMLAgainstXSD($xml)) {
            throw new ILSException('Not valid XML request!');
        }
        // delete this part - end

        // Make the NCIP request:
        try {
            $client = $this->httpService->createClient(
                    $this->config['Catalog']['url']);
            $client->setRawBody($xml);
            $client->setEncType('application/xml; "charset=utf-8"');
            $client->setMethod('POST');
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (! $result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        // Process the NCIP response:
        $body = $result->getBody();
        $response = @simplexml_load_string($body);

        if (! is_a($response, 'SimpleXMLElement')) {
            throw new ILSException("Problem parsing XML");
        }
        $response->registerXPathNamespace('ns1',
                'http://www.niso.org/2008/ncip');

        if (! $this->isValidXMLAgainstXSD($response)) {
            throw new ILSException('Not valid XML response!');
        }

        if (! $this->isCorrect($response) && ! $testing) {
            // TODO chcek problem type
            throw new ILSException('Problem has occured!');
        }
        return $response;
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
    protected function getHoldingsForChunk ($current, $bibinfo)
    {
        // Extract details from the XML:
        $status = $current->xpath(
                'ns1:ItemOptionalFields/ns1:CirculationStatus');

        $id = $bibinfo->xpath(
                'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                         'ns1:BibliographicRecordIdentifier');
        $itemIdentifierCode = (string) $current->xpath(
                'ns1:ItemId/ns1:ItemIdentifierType')[0];

        $parsingLoans = $current->xpath('ns1:LoanedItem') != null;

        if ($itemIdentifierCode == 'Accession Number') {

            $item_id = (string) $current->xpath(
                    'ns1:ItemId/ns1:ItemIdentifierValue')[0];
        }

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
        /*
         * $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
         * $location = ''; foreach ($locationNodes as $curLoc) { $type =
         * $curLoc->xpath('ns1:LocationType'); if ((string)$type[0] ==
         * 'Permanent') { $tmp =
         * $curLoc->xpath('ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue');
         * } else { $tmp[0] = 'temporary unknown'; } $location =
         * (string)$tmp[0]; }
         */
        // TODO tmp solution of getting location
        $additRequest = $this->requests->getLocation($item_id);
        $additResponse = $this->sendRequest($additRequest);
        $locationNameInstance = $additResponse->xpath(
                'ns1:LookupItemResponse/ns1:ItemOptionalFields/ns1:Location/ns1:LocationName/' .
                         'ns1:LocationNameInstance');
        foreach ($locationNameInstance as $recent) {
            $locationLevel = $recent->xpath('ns1:LocationNameLevel')[0];

            if ($locationLevel == 1) {
                $agency = (string) $recent->xpath('ns1:LocationNameValue')[0];
            } else
                if ($locationLevel == 2) {
                    $location = (string) $recent->xpath('ns1:LocationNameValue')[0];
                }
        }

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        // $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
        $itemCallNo = $current->xpath(
                'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber');
        // $itemCallNo = (string)$itemCallNo[0];

        $bibliographicItemIdentifierCode = (string) $current->xpath(
                'ns1:ItemOptionalFields/ns1:BibliographicDescription/ns1:BibliographicItemId/ns1:BibliographicItemIdentifierCode')[0];

        if ($bibliographicItemIdentifierCode == 'Legal Deposit Number') {

            $barcode = (string) $current->xpath(
                    'ns1:ItemOptionalFields/ns1:BibliographicDescription/ns1:BibliographicItemId/ns1:BibliographicItemIdentifier')[0];
        }

        $number = $current->xpath(
                'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:NumberOfPieces');

        $holdQueue = $current->xpath(
                'ns1:ItemOptionalFields/ns1:HoldQueueLength');

        $itemRestriction = (string) $current->xpath(
                'ns1:ItemOptionalFields/ns1:ItemUseRestrictionType')[0];

        $available = (string) $status[0] === 'On Shelf';

        $dueDate = $available ? null : explode("; ", (string) $status[0])[0];

        if (! empty($dueDate) && $dueDate != 'On Hold') {

            // Localize Aleph date to dd. MM. yyyy from Aleph unstructued
            // response
            $dueDate = explode("/", $dueDate);

            $dueDate[1] = date('n', strtotime($dueDate[1]));

            $dueDate = implode(". ", $dueDate);
        }

        $onStock = substr($location, 0, 5) == 'Stock';

        $restrictedToLibrary = ($itemRestriction == 'In Library Use Only');

        $monthLoanPeriod = ($itemRestriction ==
                 'Limited Circulation, Normal Loan Period');

        // FIXME: Add link logic
        $link = false;
        if ($onStock && $restrictedToLibrary) {
            // This means the reader needs to place a request to prepare the
            // item -> pick up the item from stock & bring it to circulation
            // desc
            // E.g. https://vufind.mzk.cz/Record/MZK01-000974548#bd
            $link = $this->createLinkFromAlephItemId($item_id);
        } else
            if ($onStock && $monthLoanPeriod) {
                // Pickup from stock & prepare for month loan
                $link = $this->createLinkFromAlephItemId($item_id);
            } else
                if (! $available && ! $onStock) {
                    // Reserve item
                    $link = $this->createLinkFromAlephItemId($item_id);
                }
        // End of FIXME

        return array(
                'id' => empty($id) ? "" : (string) $id[0],
                'availability' => empty($available) ? false : $available ? true : false,
                'status' => empty($status) ? "" : (string) $status[0],
                'location' => empty($itemCallNo) ? "" : (string) $itemCallNo[0],
                'reserve' => "",
                'callnumber' => "",
                'collection_desc' => empty($location) ? "" : $location,
                'duedate' => empty($dueDate) ? "" : (string) $dueDate,
                'returnDate' => false,
                'number' => empty($number) ? "" : (string) $number[0],
                'requests_placed' => empty($holdQueue) ? "" : (string) $holdQueue[0],
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

    private function createLinkFromAlephItemId ($item_id)
    {
        // Input: MZK01000974548-MZK50000974548000010
        // Output: MZK01-000974548/ExtendedHold?barcode=MZK50000974548000020
        $itemIdParts = explode("-", $item_id);

        $link = substr($itemIdParts[0], 0, 5) . "-" . substr($itemIdParts[0], 5);
        $link .= '/ExtendedHold?barcode=';
        $link .= $itemIdParts[1];
        return $link;
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
    public function getStatus ($id)
    {
        // TODO
        // For now, we'll just use getHolding, since getStatus should return a
        // subset of the same fields, and the extra values will be ignored.
        $holding = $this->getHolding($id);

        foreach ($holding as $recent) {
            $tmp[] = array_slice($recent, 0, 6);
        }
        return $tmp;
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
    public function getStatuses ($ids)
    {
        $retVal = array();
        foreach ($ids as $recent) {
            $retVal[] = $this->getStatus($recent);
        }
        return $retVal;
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
    public function getHolding ($id, array $patron = null)
    {
        $maxItemsCount = 5; // use null for unlimited count of items
        do {
            if (isset($nextItemToken[0]))
                $request = $this->requests->getHolding(array(
                        $id
                ), $maxItemsCount, (string) $nextItemToken[0]);
            else {
                $request = $this->requests->getHolding(array(
                        $id
                ), $maxItemsCount);
                $all_iteminfo = [];
            }
            $testing = ($id == "1") ? true : false;

            $response = $this->sendRequest($request, $testing);

            $new_iteminfo = $response->xpath(
                    'ns1:LookupItemSetResponse/ns1:BibInformation/ns1:HoldingsSet/ns1:ItemInformation');
            $all_iteminfo = array_merge($all_iteminfo, $new_iteminfo);

            $nextItemToken = $response->xpath(
                    'ns1:LookupItemSetResponse/ns1:NextItemToken');
        } while ($this->isValidToken($nextItemToken));
        $bibinfo = $response->xpath(
                'ns1:LookupItemSetResponse/ns1:BibInformation');
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
    public function getPurchaseHistory ($id)
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
    public function patronLogin ($username, $password)
    {
        $request = $this->requests->patronLogin($username, $password);
        $response = $this->sendRequest($request);
        $id = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue');
        $firstname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                         'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:GivenName');

        $lastname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                         'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:Surname');
        $email = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:ElectronicAddress/ns1:ElectronicAddressData');
        if (! empty($id)) {
            $patron = array(
                    'id' => empty($id) ? '' : (string)$id[0],
                    'firstname' => empty($firstname) ? '' : (string)$firstname[0],
                    'lastname'  => empty($lastname) ? '' : (string)$lastname[0],
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
    public function getMyTransactions ($patron)
    {
        $request = $this->requests->getMyTransactions($patron);
        $response = $this->sendRequest($request);
        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');

        foreach ($list as $current) {
            $request = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $item_id = $current->xpath(
                    'ns1:Ext/ns1:BibliographicDescription/' .
                             'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier');
            $bibliographicId = substr(explode("-", (string) $item_id[0])[0], 5);
            $dateDue = $current->xpath('ns1:DateDue');
            $title = $current->xpath(
                    'ns1:Ext/ns1:BibliographicDescription/ns1:Title');
            $amount = $current->xpath('ns1:Amount');
            $reminderLevel = $current->xpath('ns1:ReminderLevel');
            $mediumType = $current->xpath('ns1:MediumType');
            $ext = $current->xpath('ns1:Ext');

            // $additRequest =
            // $this->requests->getItemInfo((string)$item_id[0]);
            $additRequest = $this->requests->getItemInfo((string) $item_id[0]);
            $additResponse = $this->sendRequest($additRequest);
            $isbn = $additResponse->xpath(
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/ns1:BibliographicDescription/' .
                             'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier');
            $barcode = $additResponse->xpath(
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/ns1:BibliographicDescription/' .
                             'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier');

            $parsedDate = strtotime((string) $dateDue[0]);

            $dateDue = date('j. n. Y', $parsedDate);

            $bib_id = empty($item_id) ? null : explode('-', (string)$item_id[0])[0];
            $bib_id = substr_replace($bib_id, '-', 5, 0); // number 5 is position
            $retVal[] = array(
                    'duedate' => empty($dateDue) ? '' : $dateDue,
                    'id'  => empty($bib_id) ? '' : $bib_id,
                    'barcode' => '', // TODO
//                     'renew' => '',
//                     'renewLimit'     => '',
                    'request' => empty($request) ? '' : (string) $request[0],
                    'volume' => '',
                    'publication_year' => '', // TODO
                    'renewable' => empty($request) ? false : true,
                    'message' => '',
                    'title' => empty($title) ? '' : (string) $title[0],
                    'item_id' => empty($item_id) ? '' : (string) $item_id[0],
                    'institution_name' => '',
                    'isbn' => empty($isbn) ? '' : (string) $isbn[0],
                    'issn' => '',
                    'oclc' => '',
                    'upc' => '',
                    'borrowingLocation' => ''
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
    public function getMyFines ($patron)
    {
        $request = $this->requests->getMyFines($patron);
        $response = $this->sendRequest($request);

        $list = $response->xpath('ns1:LookupUserResponse/ns1:UserFiscalAccount');

        $fines = array();
        foreach ($list as $current) {
            $amount = $current->xpath('ns1:AccountBalance/ns1:MonetaryValue');
            $desc = $current->xpath(
                    'ns1:AccountDetails/ns1:FiscalTransactionInformation/ns1:FiscalTransactionType');
            $balance = $current->xpath(
                    'ns1:AccountDetails/ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue');
            $date = $current->xpath('ns1:AccountDetails/ns1:AccrualDate');
            /*
             * This is an item ID, not a bib ID, so it's not actually useful:
             * $tmp = $current->xpath(
             * 'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
             * 'ns1:ItemId/ns1:ItemIdentifierValue' ); $id = (string)$tmp[0];
             */
            $fines[] = array(
                    'amount' => (string) $amount[0],
                    'checkout' => '',
                    'fine' => (string) $desc[0],
                    'balance' => (string) $balance[0],
                    'createdate' => (string) $date[0],
                    'duedate' => '',
                    'id' => ''
            );
        }
        // TODO vymaz
        /*$fines[] = array(
            'amount' => '10',
            'checkout' => '03. 08. 2014',
            'fine' => 'takto to bude vyzerat',
            'balance' => '-260',
            'createdate' => '01. 08. 2014',
            'duedate' => '09. 09. 2014',
            'id' => 'MZK01-001276830',
        );*/
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
    public function getMyHolds ($patron)
    {
        $request = $this->requests->getMyHolds($patron);
        $response = $this->sendRequest($request);

        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');

        foreach ($list as $current) {
            $type = $current->xpath('ns1:RequestType');
            $id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $location = $current->xpath('ns1:PickupLocation');
            $reqnum = $current->xpath(
                    'ns1:RequestId/ns1:RequestIdentifierValue');
            $expire = $current->xpath('ns1:PickupExpiryDate');
            $create = $current->xpath('ns1:DatePlaced');
            $position = $current->xpath('ns1:HoldQueuePosition');
            $item_id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $title = $current->xpath('ns1:Title');
            $bib_id = empty($id) ? null : explode('-', (string)$id[0])[0];
            $bib_id = substr_replace($bib_id, '-', 5, 0); // number 5 is position

            $parsedDate = empty($create) ? '' : strtotime($create[0]);
            $create = date('j. n. Y', $parsedDate);
            $parsedDate = empty($expire) ? '' : strtotime($expire[0]);
            $expire = date('j. n. Y', $parsedDate);
            $retVal[] = array(
                    'type' => empty($type) ? '' : (string) $type[0],
                    'id'  => empty($bib_id) ? '' : $bib_id,
                    'location' => empty($location) ? '' : (string) $location[0],
                    'reqnum' => empty($reqnum) ? '' : (string) $reqnum[0],
                    'expire' => empty($expire) ? '' : $expire,
                    'create' => empty($create) ? '' : $create,
                    'position' => empty($position) ? '' : (string) $position[0],
                    'available' => '',
                    'item_id' => empty($item_id) ? '' : (string) $item_id[0],
                    'volume' => '',
                    'publication_year' => '',
                    'title' => empty($title) ? '' : (string) $title[0],
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
     * @param array $patron
     *            The patron array
     *
     * @throws ILSException
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile ($patron)
    {
        $request = $this->requests->getMyProfile($patron);
        $response = $this->sendRequest($request);

        $name = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                         'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:GivenName');
        $surname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
                         'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/ns1:Surname');
        $address1 = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Street');
        $address2 = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:HouseName');
        $city = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Locality');
        $country = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Country');
        $zip = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:PhysicalAddress/ns1:StructuredAddress/ns1:PostalCode');
        $electronicAddress = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserAddressInformation/' .
                         'ns1:ElectronicAddress');
        foreach ($electronicAddress as $recent) {
            if ($recent->xpath('ns1:ElectronicAddressType')[0] == 'tel') {
                $phone = $recent->xpath('ns1:ElectronicAddressData');
            }
        }
        $group = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserPrivilege/ns1:UserPrivilegeDescription');
        $patron = array(
                'firstname' => empty($name) ? '' : (string) $name[0],
                'lastname' => empty($surname) ? '' : (string) $surname[0],
                'address1' => empty($address1) ? '' : (string) $address1[0],
                'address2' => empty($address2) ? '' : (string) $address2[0],
                'city' => empty($city) ? '' : (string) $city[0],
                'country' => empty($country) ? '' : (string) $country[0],
                'zip' => empty($zip) ? '' : (string) $zip[0],
                'phone' => empty($phone) ? '' : (string) $phone[0],
                'group' => empty($group) ? '' : (string) $group[0]
        );
        return $patron;
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
    public function getNewItems ($page, $limit, $daysOld, $fundId = null)
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
    public function getFunds ()
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
    public function getDepartments ()
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
    public function getInstructors ()
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
    public function getCourses ()
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
    public function findReserves ($course, $inst, $dept)
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
    public function getSuppressedRecords ()
    {
        // TODO
        return array();
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
    protected function isValidXMLAgainstXSD ($XML,
            $path_to_XSD = './module/VuFind/tests/fixtures/ils/xcncip2/schemas/v2.02.xsd')
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true); // Begin - Disable xml error messages.
        if (is_string($XML))
            $doc->loadXML($XML);
        else
            if (get_class($XML) == 'SimpleXMLElement')
                $doc->loadXML($XML->asXML());
            else
                throw new ILSException(
                        'Expected SimpleXMLElement or string containing XML.');
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
     * @return boolean Returns true, if response is without problem.
     */
    protected function isCorrect ($response)
    {
        $problem = $response->xpath('//ns1:Problem');
        if ($problem == null)
            return true;
            // print_r($problem[0]->AsXML());
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
    protected function isValidToken ($nextItemToken)
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
 * @package ILS_Drivers
 * @author Matus Sabik <sabik@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *          License
 */
class NCIPRequests
{

    /**
     * Build NCIP request XML for cancel holds.
     *
     * @param array $cancelDetails
     *            Patron's information and details about cancel request.
     *
     * @return string XML request
     */
    public function cancelHolds ($itemID, $patronID)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ns1:version' .
                 '="http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd"><ns1:CancelRequestItem>' .
                 '<ns1:UserId><ns1:UserIdentifierValue>' .
                 htmlspecialchars($patronID) .
                 '</ns1:UserIdentifierValue></ns1:UserId>' .
                 '<ns1:ItemId><ns1:ItemIdentifierValue>' .
                 htmlspecialchars($itemID) .
                 '</ns1:ItemIdentifierValue></ns1:ItemId>' .
                 '<ns1:RequestType>cancel</ns1:RequestType>' .
                 '</ns1:CancelRequestItem></ns1:NCIPMessage>';
        return $xml;
    }

    /**
     * Build NCIP request XML for item status information.
     *
     * @param array $idList
     *            IDs to look up.
     * @param string $resumption
     *            Resumption token (null for first page of set).
     *
     * @return string XML request
     */
    public function getHolding ($idList, $maxItemsCount = null, $resumption = null)
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
            $id = str_replace("-", "", $id);
            $xml .= '<ns1:BibliographicId>' . '<ns1:BibliographicRecordId>' .
                     '<ns1:BibliographicRecordIdentifier>' .
                     htmlspecialchars($id) .
                     '</ns1:BibliographicRecordIdentifier>' .
                     '<ns1:AgencyId ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/agencyidtype/agencyidtype.scm">MZK</ns1:AgencyId>' .
                     '</ns1:BibliographicRecordId>' . '</ns1:BibliographicId>';
        }
        // Add the desired data list:
        foreach ($desiredParts as $current) {
            $xml .= '<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">' .
                     htmlspecialchars($current) . '</ns1:ItemElementType>';
        }
        if (! empty($maxItemsCount)) {
            $xml .= '<ns1:MaximumItemsCount>' . htmlspecialchars($maxItemsCount) .
                     '</ns1:MaximumItemsCount>';
        }
        // Add resumption token if necessary:
        if (! empty($resumption)) {
            $xml .= '<ns1:NextItemToken>' . htmlspecialchars($resumption) .
                     '</ns1:NextItemToken>';
        }
        // Close the XML and send it to the caller:
        $xml .= '</ns1:LookupItemSet></ns1:NCIPMessage>';
        return $xml;
    }

    /**
     * Temporary method for dealing with item's location.
     *
     * @param string $itemID
     */
    public function getItemInfo ($itemID)
    {
        $desiredParts = array(
                'Bibliographic Description',
                'Circulation Status',
                'Electronic Resource',
                'Hold Queue Length',
                'Item Description',
                'Item Use Restriction Type',
                'Location',
                'Physical Condition',
                'Security Marker',
                'Sensitization Flag'
        );

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                 'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_0.xsd">' .
                 '<ns1:LookupItem>' . '<ns1:ItemId><ns1:ItemIdentifierValue>' .
                 htmlspecialchars($itemID) .
                 '</ns1:ItemIdentifierValue></ns1:ItemId>';

        foreach ($desiredParts as $current) {
            $xml .= '<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">' .
                     htmlspecialchars($current) . '</ns1:ItemElementType>';
        }
        $xml .= '</ns1:LookupItem></ns1:NCIPMessage>';
        return $xml;
    }

    /**
     * Temporary method for dealing with item's location.
     *
     * @param string $itemID
     */
    public function getLocation ($itemID)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                 'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_0.xsd">' .
                 '<ns1:LookupItem>' . '<ns1:ItemId><ns1:ItemIdentifierValue>' .
                 htmlspecialchars($itemID) .
                 '</ns1:ItemIdentifierValue></ns1:ItemId>' .
                 '<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">' .
                 'Location</ns1:ItemElementType>' . '</ns1:LookupItem>' .
                 '</ns1:NCIPMessage>';
    }

    /**
     * Build the NCIP request XML to get patron's fines.
     *
     * @param array $patron
     *            The patron array
     *
     * @return string NCIP request XML
     */
    public function getMyFines ($patron)
    {
        $extras = array(
                '<ns1:UserFiscalAccountDesired/>'
        );
        return $this->getMyProfile($patron, $extras);
    }

    /**
     * Build the NCIP request XML to get patron's current holds - books which
     * are reserved.
     *
     * @param array $patron
     *            The patron array
     *
     * @return string NCIP request XML
     */
    public function getMyHolds ($patron)
    {
        $extras = array(
                '<ns1:RequestedItemsDesired/>'
        );
        return $this->getMyProfile($patron, $extras);
    }

    /**
     * Build the NCIP request XML to get patron's details.
     *
     * @param array $patron
     *            The patron array
     *
     * @return string NCIP request XML
     */
    public function getMyProfile ($patron, $extras = null)
    {
        if ($extras == null) {
            $extras = array(
                    '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                             'schemes/userelementtype/userelementtype.scm">' .
                             'Name Information' . '</ns1:UserElementType>' .
                             '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                             'schemes/userelementtype/userelementtype.scm">' .
                             'User Address Information' .
                             '</ns1:UserElementType>' .
                             '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                             'schemes/userelementtype/userelementtype.scm">' .
                             'User Privilege' . '</ns1:UserElementType>'
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                 'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_0.xsd">' .
                 '<ns1:LookupUser>' . '<ns1:UserId>' .
                 '<ns1:UserIdentifierValue>' . htmlspecialchars($patron['id']) .
                 '</ns1:UserIdentifierValue>' . '</ns1:UserId>' .
                 implode('', $extras) . '</ns1:LookupUser>' .
                 '</ns1:NCIPMessage>';
    }

    /**
     * Build the NCIP request XML to get patron's current checked out items.
     *
     * @param array $patron
     *            The patron array
     *
     * @return string NCIP request XML
     */
    public function getMyTransactions ($patron)
    {
        $extras = array(
                '<ns1:LoanedItemsDesired/>'
        );
        return $this->getMyProfile($patron, $extras);
    }

    /**
     * Build the request XML to log in a user.
     *
     * @param string $username
     *            Username for login
     * @param string $password
     *            Password for login
     * @param string $extras
     *            Extra elements to include in the request
     *
     * @return string NCIP request XML
     */
    public function patronLogin ($username, $password)
    {
        $extras = array(
                '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                         'schemes/userelementtype/userelementtype.scm">' .
                         'Name Information' . '</ns1:UserElementType>' .
                         '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                         'schemes/userelementtype/userelementtype.scm">' .
                         'User Address Information' . '</ns1:UserElementType>'
        );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
                 'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
                 'xsd/ncip_v2_0.xsd">' . '<ns1:LookupUser>' .
                 '<ns1:AuthenticationInput>' . '<ns1:AuthenticationInputData>' .
                 htmlspecialchars($username) . '</ns1:AuthenticationInputData>' .
                 '<ns1:AuthenticationDataFormatType>' . 'text' .
                 '</ns1:AuthenticationDataFormatType>' .
                 '<ns1:AuthenticationInputType>' . 'User Id' .
                 '</ns1:AuthenticationInputType>' . '</ns1:AuthenticationInput>' .
                 '<ns1:AuthenticationInput>' . '<ns1:AuthenticationInputData>' .
                 htmlspecialchars($password) . '</ns1:AuthenticationInputData>' .
                 '<ns1:AuthenticationDataFormatType>' . 'text' .
                 '</ns1:AuthenticationDataFormatType>' .
                 '<ns1:AuthenticationInputType>' . 'Password' .
                 '</ns1:AuthenticationInputType>' . '</ns1:AuthenticationInput>' .
                 implode('', $extras) . '</ns1:LookupUser>' .
                 '</ns1:NCIPMessage>';
    }

    /**
     * Build the NCIP request XML to renew patron's items.
     *
     * @param array $patron
     * @param string $item
     *
     * @return string NCIP request XML
     */
    public function renewMyItems ($patron, $item)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ns1:version' .
                 '="http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd"><ns1:RenewItem>' .
                 '<ns1:UserId>' . '<ns1:UserIdentifierValue>' .
                 htmlspecialchars($patron['id']) . '</ns1:UserIdentifierValue>' .
                 '</ns1:UserId>' . '<ns1:ItemId>' . '<ns1:ItemIdentifierValue>' .
                 htmlspecialchars($item) . '</ns1:ItemIdentifierValue>' .
                 '</ns1:ItemId>' . '</ns1:RenewItem></ns1:NCIPMessage>';
        return $xml;
    }
}