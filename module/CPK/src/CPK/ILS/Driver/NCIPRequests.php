<?php
/**
 * Subsidiary class for XCNCIP2 driver.
 *
 * PHP version 5
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;

class NCIPRequests {

    protected $noScheme = false;
    protected $agency = null;
    protected $sendUserId = null;

    protected $libsLikeTabor = [
        'TAG001', 'ULG001', 'KHG001', 'ABC016', 'HBG001', 'PRG001', 'OPG001',
    ];
    protected $libsLikeLiberec = [
        'LIA001', 'CBA001', 'KLG001',
    ];

    public function __construct($config) {
        $this->agency = $config['Catalog']['agency'];
        $this->sendUserId = isset($config['Catalog']['sendUserId']) ? $config['Catalog']['sendUserId']: true;
    }

    public function patronLogin($username, $password) {
        $body =
        "<ns1:LookupUser>" .
        $this->insertInitiationHeader() . // TODO add agency
        "<ns1:AuthenticationInput>" .
        "<ns1:AuthenticationInputData>" . htmlspecialchars($username) . "</ns1:AuthenticationInputData>" .
        "<ns1:AuthenticationDataFormatType>text/plain</ns1:AuthenticationDataFormatType>" .
        "<ns1:AuthenticationInputType>" . $this->userAuthenticationInputType() . "</ns1:AuthenticationInputType>" .
        "</ns1:AuthenticationInput>" .
        "<ns1:AuthenticationInput>" .
        "<ns1:AuthenticationInputData>" . htmlspecialchars($password) . "</ns1:AuthenticationInputData>" .
        "<ns1:AuthenticationDataFormatType>text/plain</ns1:AuthenticationDataFormatType>" .
        "<ns1:AuthenticationInputType>Password</ns1:AuthenticationInputType>" .
        "</ns1:AuthenticationInput>" .
        $this->insertUserElementType("Name Information") .
        $this->insertUserElementType("User Address Information") .
        "</ns1:LookupUser>";
        return $this->header() . $body . $this->footer();
    }

    public function patronFullInformation($patron) {
        $extras = $this->allUserElementType();
        return $this->patronInformation($patron, $extras);
    }

    public function patronLoanedItems($patron) {
        $extras = "<ns1:LoanedItemsDesired />";
        return $this->patronInformation($patron, $extras);
    }

    public function patronRequestedItems($patron) {
        $extras = "<ns1:RequestedItemsDesired />";
        return $this->patronInformation($patron, $extras);
    }

    public function patronFiscalAccount($patron) {
        $extras = "<ns1:UserFiscalAccountDesired />";
        return $this->patronInformation($patron, $extras);
    }

    public function patronBlocks($patron) {
        $extras = $this->insertUserElementType("Block Or Trap");
        return $this->patronInformation($patron, $extras);
    }

    public function lookupItem($itemId, $patron) {
        $body =
        "<ns1:LookupItem>" .
        $this->insertInitiationHeader($patron) .
        $this->insertItemIdTag($itemId, $patron) .
        $this->allItemElementType() .
        $this->insertExtPatronId($patron) .
        "</ns1:LookupItem>";
        return $this->header() . $body . $this->footer();
    }

    public function requestItem($patron, $holdDetails) {
        $requestScopeType = "Item";
        // TODO if (library.equals("Liberec")) requestScopeType = "Bibliographic Item";
        $strDate = str_replace('. ', '-', $holdDetails['requiredBy']);
        $date = strtotime($strDate);
        $requiredBy = gmdate('Y-m-d\Th:m:s', $date);

        $body =
        "<ns1:RequestItem>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        $this->insertItemIdTag($holdDetails['item_id'], $patron) .
        $this->insertRequestType("Hold") .
        //$this->insertRequestType("Loan") .
        $this->insertRequestScopeType($requestScopeType);
        $body .= "<ns1:NeedBeforeDate>" . htmlspecialchars($requiredBy) . "</ns1:NeedBeforeDate>";
        if (! empty($holdDetails['pickUpLocation'])) $body .= "<ns1:PickupLocation>" . htmlspecialchars($holdDetails['pickUpLocation']) . "</ns1:PickupLocation>";
        $body .= "</ns1:RequestItem>";
        return $this->header() . $body . $this->footer();
    }

    public function cancelRequestItemUsingItemId($patron, $itemId) {
        $requestType = "Estimate";
        if (in_array($this->agency, $this->libsLikeTabor)) $requestType = "Hold";
        $body =
        "<ns1:CancelRequestItem>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        $this->insertItemIdTag($itemId, $patron) .
        $this->insertRequestType($requestType) .
        $this->insertRequestScopeType("Item") .
        "</ns1:CancelRequestItem>";
        return $this->header() . $body . $this->footer();
    }

    public function cancelRequestItemUsingRequestId($patron, $requestId) {
        $requestType = "Estimate";
        if (in_array($this->agency, $this->libsLikeTabor)) $requestType = "Hold";
        $body =
        "<ns1:CancelRequestItem>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        $this->insertRequestIdTag($requestId, $patron) .
        $this->insertRequestType($requestType) .
        $this->insertRequestScopeType("Bibliographic Item") .
        "</ns1:CancelRequestItem>";
        return $this->header() . $body . $this->footer();
    }

    public function renewItem($patron, $itemId) {
        $body =
        "<ns1:RenewItem>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        $this->insertItemIdTag($itemId, $patron) .
        //$this->allItemElementType() .
        //$this->allUserElementType() .
        "</ns1:RenewItem>";
        return $this->header() . $body . $this->footer();
    }

    public function LUISItemId($itemList, $nextItemToken = null, XCNCIP2 $mainClass = null, $patron = []) {
        $body = "<ns1:LookupItemSet>";
        $body .= $this->insertInitiationHeader($patron);
        foreach ($itemList as $id) {
            if ($mainClass !== null)
                list ($id, $agency) = $mainClass->splitAgencyId($id);
            $body .= $this->insertItemIdTag($id, $patron);
        }
        $body .= $this->allItemElementType();
        if (! empty($mainClass->getMaximumItemsCount())) {
            $body .= "<ns1:MaximumItemsCount>" .
                    htmlspecialchars($mainClass->getMaximumItemsCount()) .
                    "</ns1:MaximumItemsCount>";
        }
        if (! empty($nextItemToken)) {
            $body .= "<ns1:NextItemToken>" . htmlspecialchars($nextItemToken) .
            "</ns1:NextItemToken>";
        }
        $body .= $this->insertExtPatronId($patron);
        $body .= "</ns1:LookupItemSet>";
        return $this->header() . $body . $this->footer();
    }

    public function LUISBibItem($bibId, $nextItemToken = null, XCNCIP2 $mainClass = null, $patron = []) {
        $body = "<ns1:LookupItemSet>";
        $body .= $this->insertInitiationHeader($patron);
        if ($mainClass !== null)
            list ($bibId, $agency) = $mainClass->splitAgencyId($bibId);
        $body .= $this->insertBibliographicItemIdTag($bibId);
        $body .= $this->allItemElementType();
        if (! empty($mainClass->getMaximumItemsCount())) {
            $body .= "<ns1:MaximumItemsCount>" .
                    htmlspecialchars($mainClass->getMaximumItemsCount()) .
                    "</ns1:MaximumItemsCount>";
        }
        if (! empty($nextItemToken)) {
            $body .= "<ns1:NextItemToken>" . htmlspecialchars($nextItemToken) .
            "</ns1:NextItemToken>";
        }
        $body .= $this->insertExtPatronId($patron);
        $body .= "</ns1:LookupItemSet>";
        return $this->header() . $body . $this->footer();
    }

    public function lookupAgency($patron) {
        $body =
        "<ns1:LookupAgency>" .
        $this->insertInitiationHeader($patron) .
        $this->insertAgencyIdTag($patron) .
        $this->insertAgencyElementType("Agency Address Information") .
        $this->insertAgencyElementType("Agency User Privilege Type") .
        $this->insertAgencyElementType("Application Profile Supported Type") .
        $this->insertAgencyElementType("Authentication Prompt") .
        $this->insertAgencyElementType("Consortium Agreement") .
        $this->insertAgencyElementType("Organization Name Information") .
        "</ns1:LookupAgency>";
        return $this->header() . $body . $this->footer();
    }

    public function patronHistory($patron, $page, $perPage) {
        $schemeExtension = "xmlns:ns2=\"https://ncip.knihovny.cz/ILSDI/ncip/2015/extensions\"";
        $body =
        "<ns1:LookupUser>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        "<ns1:Ext>" .
        "<ns2:HistoryDesired>" .
        "<ns2:Page>" . htmlspecialchars($page) . "</ns2:Page>" .
        "</ns2:HistoryDesired>" .
        "</ns1:Ext>" .
        "</ns1:LookupUser>";
        return $this->header($schemeExtension) . $body . $this->footer();
    }

    public function getLibsLikeTabor() {
        return $this->libsLikeTabor;
    }

    public function getLibsLikeLiberec() {
        return $this->libsLikeLiberec;
    }

    protected function header($ext = '') {
        if (! empty($ext)) {
            $ext .= ' ';
        }
        $body = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>" .
            "<ns1:NCIPMessage xmlns:ns1=\"http://www.niso.org/2008/ncip\" " .
            $ext .
            "ns1:version=\"http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd\">";
        return $body;
    }

    protected function footer() {
        return "</ns1:NCIPMessage>";
    }

    protected function patronInformation($patron, $extras = '') {
        $body =
        "<ns1:LookupUser>" .
        $this->insertInitiationHeader($patron) .
        $this->insertUserIdTag($patron) .
        // Do not use htmlspecialchars for $extras.
        $extras .
        "</ns1:LookupUser>";
        return $this->header() . $body . $this->footer();
    }

    protected function insertInitiationHeader($patron, $from = "CPK") {
        $to = (isset($patron['agency']) && ! empty($patron['agency'])) ? $patron['agency'] : $this->agency;
        $initiationHeader =
        "<ns1:InitiationHeader>" .
        "<ns1:FromAgencyId>" .
        ($this->noScheme ?
                "<ns1:AgencyId>" :
                "<ns1:AgencyId ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/agencyidtype/agencyidtype.scm\">") .
        htmlspecialchars($from) . "</ns1:AgencyId>" .
        "</ns1:FromAgencyId>" .
        "<ns1:ToAgencyId>" .
        ($this->noScheme ?
                "<ns1:AgencyId>" :
                "<ns1:AgencyId ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/agencyidtype/agencyidtype.scm\">") .
        htmlspecialchars($to) . "</ns1:AgencyId>" .
        "</ns1:ToAgencyId>" .
        "</ns1:InitiationHeader>";
        return $initiationHeader;
    }

    protected function insertUserIdTag($patron) {
        $body =
        "<ns1:UserId>" .
        $this->insertAgencyIdTag($patron) .
        ($this->noScheme ?
                "<ns1:UserIdentifierType>" :
                "<ns1:UserIdentifierType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/" .
                "visibleuseridentifiertype/visibleuseridentifiertype.scm\">") .
        "Institution Id Number" . "</ns1:UserIdentifierType>" .
        "<ns1:UserIdentifierValue>" . htmlspecialchars($patron['id']) . "</ns1:UserIdentifierValue>" .
        "</ns1:UserId>";
        return $body;
    }

    protected function insertAgencyIdTag($patron) {
        $agency = (isset($patron['agency']) && ! empty($patron['agency'])) ? $patron['agency'] : $this->agency;
        if (empty($agency)) return '';
        return ($this->noScheme ?
                "<ns1:AgencyId>" :
                "<ns1:AgencyId ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/agencyidtype/agencyidtype.scm\">") .
        htmlspecialchars($agency) . "</ns1:AgencyId>";
    }

    protected function insertItemIdTag($itemId, $patron) {
        $body =
        "<ns1:ItemId>" .
        $this->insertAgencyIdTag($patron) .
        $this->insertItemIdentifierType() .
        "<ns1:ItemIdentifierValue>" . htmlspecialchars($itemId) . "</ns1:ItemIdentifierValue>" .
        "</ns1:ItemId>";
        return $body;
    }

    protected function insertRequestIdTag($requestId, $patron) {
        $body =
        "<ns1:RequestId>" .
        $this->insertAgencyIdTag($patron) .
        ($this->noScheme ?
                "<ns1:RequestIdentifierType>" :
                "<ns1:RequestIdentifierType ns1:Scheme=\"http://www.library.sk/ncip/v2_02/schemes.scm\">") .
                "IDX" . "</ns1:RequestIdentifierType>" .
        "<ns1:RequestIdentifierValue>" . htmlspecialchars($requestId) . "</ns1:RequestIdentifierValue>" .
        "</ns1:RequestId>";
        return $body;
    }

    protected function insertBibliographicItemIdTag($itemId) {
        $body =
        "<ns1:BibliographicId>" .
        "<ns1:BibliographicItemId>" .
        "<ns1:BibliographicItemIdentifier>" .
        htmlspecialchars($itemId) .
        "</ns1:BibliographicItemIdentifier>" .
        $this->bibliographicItemIdentifierCode("Legal Deposit Number") .
        "</ns1:BibliographicItemId>" .
        "</ns1:BibliographicId>";
        return $body;
    }

    /* Allowed values are: Accession Number, Barcode. */
    protected function insertItemIdentifierType() {
        $itemIdentifierType = "Accession Number";
        if (in_array($this->agency, $this->libsLikeLiberec)) $itemIdentifierType = "Barcode";
        return ($this->noScheme ?
                "<ns1:ItemIdentifierType>" :
                "<ns1:ItemIdentifierType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/" .
                "visibleitemidentifiertype/visibleitemidentifiertype.scm\">") .
        $itemIdentifierType . "</ns1:ItemIdentifierType>";
    }

    protected function allItemElementType() {
        $body =
        $this->insertItemElementType("Bibliographic Description") .
        $this->insertItemElementType("Hold Queue Length") .
        $this->insertItemElementType("Circulation Status") .
        $this->insertItemElementType("Electronic Resource") .
        $this->insertItemElementType("Item Use Restriction Type") .
        $this->insertItemElementType("Location") .
        $this->insertItemElementType("Physical Condition") .
        $this->insertItemElementType("Security Marker") .
        $this->insertItemElementType("Item Description") .
        $this->insertItemElementType("Sensitization Flag");
        // TODO
        /*if (library.equals("Liberec")) {
            body =
            itemElementType("Bibliographic Description") +
            itemElementType("Hold Queue Length") +
            itemElementType("Circulation Status") +
            itemElementType("Item Use Restriction Type") +
            itemElementType("Location") +
            itemElementType("Item Description");
        }*/
        return $body;
    }

    protected function allUserElementType() {
        $body =
        $this->insertUserElementType("Authentication Input") .
        $this->insertUserElementType("Block Or Trap") .
        $this->insertUserElementType("Date Of Birth") .
        $this->insertUserElementType("Name Information") .
        $this->insertUserElementType("User Address Information") .
        $this->insertUserElementType("User Language") .
        $this->insertUserElementType("User Privilege") .
        $this->insertUserElementType("User Id") .
        $this->insertUserElementType("Previous User Id");
        if (in_array($this->agency, $this->libsLikeLiberec)) {
            $body =
            $this->insertUserElementType("Block Or Trap") .
            $this->insertUserElementType("Name Information") .
            $this->insertUserElementType("User Address Information") .
            $this->insertUserElementType("User Privilege");
        }
        return $body;
    }

    protected function insertItemElementType($value) {
        return ($this->noScheme ?
                "<ns1:ItemElementType>" :
                "<ns1:ItemElementType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/itemelementtype/" .
                "itemelementtype.scm\">") .
                htmlspecialchars($value) . "</ns1:ItemElementType>";
    }

    protected function insertUserElementType($value) {
        return ($this->noScheme ?
                "<ns1:UserElementType>" :
                "<ns1:UserElementType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/userelementtype/" .
                "userelementtype.scm\">") .
                htmlspecialchars($value) . "</ns1:UserElementType>";
    }

    /* Allowed values are: Hold, Loan. Estimate is also used. */
    protected function insertRequestType($value) {
        return ($this->noScheme ?
                "<ns1:RequestType>" :
                "<ns1:RequestType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/requesttype/requesttype.scm\">") .
                htmlspecialchars($value) . "</ns1:RequestType>";
    }

    /* Allowed values are: Item, Bibliographic Item. */
    protected function insertRequestScopeType($value) {
        return ($this->noScheme ?
                "<ns1:RequestScopeType>" :
                "<ns1:RequestScopeType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/requestscopetype/" .
                "requestscopetype.scm\">") .
                htmlspecialchars($value) . "</ns1:RequestScopeType>";
    }

    /* Allowed values are: Legal Deposit Number, ISBN. */
    protected function bibliographicItemIdentifierCode($value) {
        return ($this->noScheme ?
                "<ns1:BibliographicItemIdentifierCode>" :
                "<ns1:BibliographicItemIdentifierCode ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/" .
                "bibliographicitemidentifiercode/bibliographicitemidentifiercode.scm\">") .
                htmlspecialchars($value) .
                "</ns1:BibliographicItemIdentifierCode>";
    }

    protected function userAuthenticationInputType() {
        //if (library.equals("Zlin")) return "Username"; // TODO
        return "User Id";
    }

    protected function insertAgencyElementType($value) {
        return ($this->noScheme ?
                "<ns1:AgencyElementType>" :
                "<ns1:AgencyElementType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/agencyelementtype/" .
                "agencyelementtype.scm\">") .
                $value . "</ns1:AgencyElementType>";
    }

    /* Append the Ext element containing the UserId. */
    protected function insertExtPatronId($patron) {
        $body = "";
        if ($this->sendUserId) {
            if (! empty($patron)) {
                $body .= '<ns1:Ext>';
                $body .= $this->insertUserIdTag($patron);
                $body .= '</ns1:Ext>';
            }
        }
        return $body;
    }
}
