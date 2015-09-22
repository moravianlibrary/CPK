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

class NCIPRequests extends OldNCIPRequests {

    protected $noScheme = false;

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

    public function lookupItem($itemId, $patron) {
        $body =
        "<ns1:LookupItem>" .
        $this->insertInitiationHeader($patron['agency']) .
        $this->insertItemIdTag($itemId, $patron) .
        $this->allItemElementType() .
        "</ns1:LookupItem>";
        return $this->header() . $body . $this->footer();
    }

    public function requestItem($patron, $holdDetails) {
        $requestScopeType = "Item";
        // TODO if (library.equals("Liberec")) requestScopeType = "Bibliographic Item";

        $body =
        "<ns1:RequestItem>" .
        $this->insertInitiationHeader($patron['agency']) .
        $this->insertUserIdTag($patron) .
        $this->insertItemIdTag($holdDetails['item_id'], $patron) .
        $this->insertRequestType("Hold") .
        $this->insertRequestScopeType($requestScopeType);
        if (! empty($holdDetails['pickUpLocation'])) $body .= "<ns1:PickupLocation>" . htmlspecialchars($holdDetails['pickUpLocation']) . "</ns1:PickupLocation>";
        $body .= "</ns1:RequestItem>";
        return $this->header() . $body . $this->footer();
    }

    protected function header() {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>" .
        "<ns1:NCIPMessage xmlns:ns1=\"http://www.niso.org/2008/ncip\" " .
        "ns1:version=\"http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd\">";
    }

    protected function footer() {
        return "</ns1:NCIPMessage>";
    }

    protected function patronInformation($patron, $extras) {
        $body =
        "<ns1:LookupUser>" .
        $this->insertInitiationHeader($patron['agency']) .
        $this->insertUserIdTag($patron) .
        $extras .
        "</ns1:LookupUser>";
        return $this->header() . $body . $this->footer();
    }

    protected function insertInitiationHeader($to, $from = "CPK") {
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
        $this->insertAgencyIdTag($patron['agency']) .
        ($this->noScheme ?
                "<ns1:UserIdentifierType>" :
                "<ns1:UserIdentifierType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/" .
                "visibleuseridentifiertype/visibleuseridentifiertype.scm\">") .
        "Institution Id Number" . "</ns1:UserIdentifierType>" .
        "<ns1:UserIdentifierValue>" . htmlspecialchars($patron['id']) . "</ns1:UserIdentifierValue>" .
        "</ns1:UserId>";
        return $body;
    }

    protected function insertAgencyIdTag($agency) {
        return ($this->noScheme ?
                "<ns1:AgencyId>" :
                "<ns1:AgencyId ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/agencyidtype/agencyidtype.scm\">") .
        htmlspecialchars($agency) . "</ns1:AgencyId>";
    }

    protected function insertItemIdTag($itemId, $patron) {
        $body =
        "<ns1:ItemId>" .
        $this->insertAgencyIdTag($patron['agency']) .
        $this->insertItemIdentifierType() .
        "<ns1:ItemIdentifierValue>" . htmlspecialchars($itemId) . "</ns1:ItemIdentifierValue>" .
        "</ns1:ItemId>";
        return $body;
    }

    /* Allowed values are: Accession Number, Barcode. */
    protected function insertItemIdentifierType() {
        $itemIdentifierType = "Accession Number";
        // TODO if (library.equals("Liberec")) itemIdentifierType = "Barcode";
        return ($this->noScheme ?
                "<ns1:ItemIdentifierType>" :
                "<ns1:ItemIdentifierType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/imp1/schemes/" .
                "visibleitemidentifiertype/visibleitemidentifiertype.scm\">") .
        $itemIdentifierType . "</ns1:ItemIdentifierType>";
    }

    protected function allItemElementType() {
        $body =
        $this->itemElementType("Bibliographic Description") .
        $this->itemElementType("Hold Queue Length") .
        $this->itemElementType("Circulation Status") .
        $this->itemElementType("Electronic Resource") .
        $this->itemElementType("Item Use Restriction Type") .
        $this->itemElementType("Location") .
        $this->itemElementType("Physical Condition") .
        $this->itemElementType("Security Marker") .
        $this->itemElementType("Item Description") .
        $this->itemElementType("Sensitization Flag");
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

    protected function itemElementType($value) {
        return ($this->noScheme ?
                "<ns1:ItemElementType>" :
                "<ns1:ItemElementType ns1:Scheme=\"http://www.niso.org/ncip/v1_0/schemes/itemelementtype/" .
                "itemelementtype.scm\">") .
                htmlspecialchars($value) . "</ns1:ItemElementType>";
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
}
