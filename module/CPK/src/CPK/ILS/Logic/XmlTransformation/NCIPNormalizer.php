<?php
/**
 * Created by PhpStorm.
 * User: kozlovsky
 * Date: 5.5.17
 * Time: 18:25
 */

namespace CPK\ILS\Logic\XmlTransformation;

use CPK\ILS\Driver\NCIPRequests;
use VuFind\Exception\ILS as ILSException;
use Zend\I18n\Translator\Translator;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

/**
 * Class NCIPNormalizer
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class NCIPNormalizer implements LoggerAwareInterface
{
    const NAMESPACES = array(
        'ns2' => 'https://ncip.knihovny.cz/ILSDI/ncip/2015/extensions',
        'ns1' => 'http://www.niso.org/2008/ncip',
    );

    const NAMESPACE_VERSIONS = array(
        'ns1' => 'http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd',
        'ns2' => 'https://raw.githubusercontent.com/eXtensibleCatalog/NCIP2-Toolkit/master/core/trunk/binding/ilsdiv1_1/src/main/xsd/ncip_v2_02_ils-di_extensions.xsd'
    );

    /**
     * Name of the XCNCIP2 method that has been used
     *
     * @var string
     */
    protected $methodName;

    /**
     * Library source communicated with
     *
     * @var string
     */
    protected $source;

    /**
     * Library agency communicated with (SIGLA)
     *
     * @var string
     */
    protected $agency;

    /**
     * @var NCIPRequests
     */
    protected $ncipRequests;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $libsWithClavius = null;

    /**
     * @var array
     */
    protected $libsWithARL = null;

    /**
     * @var null
     */
    protected $libsWithVerbis = null;

    /**
     * @var null
     */
    protected $libsNeedsPickUpLocation = null;

    /**
     * @var Translator
     */
    protected $translator = null;

    /**
     * NCIPNormalizer constructor.
     *
     * @param string $methodName
     * @param string $source
     * @param string $agency
     * @param NCIPRequests $ncipRequests
     * @param Translator $translator
     */
    public function __construct(
        string $methodName,
        string $source,
        string $agency,
        NCIPRequests $ncipRequests,
        Translator $translator
    )
    {
        $this->methodName = $methodName;
        $this->source = $source;
        $this->agency = $agency;
        $this->ncipRequests = $ncipRequests;
        $this->libsWithClavius = $ncipRequests->getLibsWithClavius();
        $this->libsWithARL = $ncipRequests->getLibsWithARL();
        $this->libsWithVerbis = $ncipRequests->getLibsWithVerbis();
        $this->libsNeedsPickUpLocation = $ncipRequests->getLibsNeedsPickUpLocation();
        $this->translator = $translator;
    }

    /**
     * Returns normalized NCIP request with respect to source & method.
     *
     * @param string $stringXml
     * @return JsonXML
     * @throws JsonXMLException
     * @throws ILSException
     */
    public function normalize(string $stringXml)
    {
        $jsonXml = JsonXML::fabricateFromXmlString($stringXml, self::NAMESPACES);

        switch ($this->methodName) {
            case 'getMyFines':
                $this->normalizeLookupUserBlocksAndTraps($jsonXml);
                break;
            case 'getMyHolds':
                $this->normalizeRequestedItems($jsonXml);
                break;
            case 'getPickUpLocations':
                $this->normalizeLookupAgencyLocations($jsonXml);
                break;
            case 'getMyHistoryPage':
                $this->normalizeLookupUserLoanedItemsHistory($jsonXml);
                break;
            case 'getMyProfile':
                $this->normalizeLookupUserProfile($jsonXml);
                break;
            case 'getStatus':
                $this->normalizeLookupItemStatus($jsonXml);
                break;
            case 'getStatuses':
                $this->normalizeLookupItemSetStatus($jsonXml);
                break;
            case 'getMyTransactions':
                $this->normalizeLookupUserLoanedItems($jsonXml);
                break;
            case 'cancelHolds':
                $this->normalizeRequestedItems($jsonXml);
                break;
        }

        return $jsonXml;

    }

    /**
     * @param JsonXML $response
     * @throws ILSException
     * @throws JsonXMLException
     */
    protected function normalizeLookupUserBlocksAndTraps(JsonXML &$response)
    {
        if ($this->agency === 'ABA008') { // NLK
            throw new ILSException('driver_no_fines');
        }

        if (in_array($this->agency, $this->libsWithVerbis)) {

            // FiscalActionType belongs to FiscalTransactionDescription

            $accountDetails = $response->getArray('LookupUserResponse', 'UserFiscalAccount', 'AccountDetails');

            foreach ($accountDetails as $i => $accountDetail) {
                $actionType = $response->getRelative(
                    $accountDetail,
                    'FiscalTransactionInformation',
                    'FiscalActionType'
                );

                if ($actionType !== null)
                    $response->setDataValue(
                        $actionType,
                        'ns1:LookupUserResponse',
                        'ns1:UserFiscalAccount',
                        "ns1:AccountDetails[$i]",
                        'FiscalTransactionInformation',
                        'FiscalTransactionDescription'
                    );
            }
        }
    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected function normalizeRequestedItems(JsonXML &$response)
    {

        $requestedItems = $response->getArray('LookupUserResponse', 'RequestedItem');

        foreach ($requestedItems as $i => $requestedItem) {

            $extBibliographicDescription = $response->getRelative(
                $requestedItem,
                'Ext',
                'BibliographicDescription'
            );

            if ($extBibliographicDescription !== null) {

                // Now we will move BibId from Ext to it's standard place if necessary

                $bibliographicIds = $response->getRelative(
                    $requestedItem,
                    'BibliographicId'
                );

                $countOfBibIds = sizeof($bibliographicIds);

                // Check if it is really necessary
                $tryToMoveIdFromExt = false;
                if ($countOfBibIds === 0) {
                    $tryToMoveIdFromExt = true;
                } else {
                    $found = false;
                    foreach ($bibliographicIds as $bibliographicIdKey => $bibliographicId) {
                        $found = $response->getRelative(
                                $bibliographicId,
                                'BibliographicItemId',
                                'BibliographicItemIdentifier'
                            ) !== null;

                        if ($found)
                            break;
                    }

                    if (!$found) {
                        $tryToMoveIdFromExt = true;
                    }
                }

                if ($tryToMoveIdFromExt) {

                    $extId = $response->getRelative(
                        $extBibliographicDescription,
                        'BibliographicItemId',
                        'BibliographicItemIdentifier'
                    );

                    $response->setDataValue(
                        $extId,
                        'ns1:LookupUserResponse',
                        count($requestedItems) > 1 ? "ns1:RequestedItem[$i]" : "ns1:RequestedItem",
                        "ns1:BibliographicId[$countOfBibIds]",
                        'ns1:BibliographicItemId',
                        'ns1:BibliographicItemIdentifier'
                    );

                }

                // Now we will move ExtTitle to standard Title location

                $title = $response->getRelative(
                    $requestedItem,
                    'Title'
                );

                if ($title === null) {

                    $extTitle = $response->getRelative(
                        $extBibliographicDescription,
                        'Title'
                    );

                    $response->setDataValue(
                        $extTitle,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:Title'
                    );
                }

                // Reload current requested item to apply the changes for the rest of current function
                $requestedItem = $response->getArray('LookupUserResponse', 'RequestedItem')[$i];
            }

            $type = $response->getRelative(
                $requestedItem,
                'RequestType'
            );

            if (in_array($this->agency, $this->libsWithARL)) {

                // Periodicals request cannot be returned
                if ($type == 'w') {

                    // Mark request as non returnable
                    $response->setDataValue(
                        array(), // Empty array is used for empty XML elements
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:Ext',
                        'ns1:NonReturnableFlag'
                    );
                }

                // CamelCase each bibliographic id
                $bibliographicIds = $response->getRelative(
                    $requestedItem,
                    'BibliographicId'
                );

                foreach ($bibliographicIds as $bibliographicIdKey => $bibliographicId) {

                    $id = $response->getRelative(
                        $bibliographicId,
                        'BibliographicItemId',
                        'BibliographicItemIdentifier'
                    );

                    if ($id !== null) {

                        $id = str_replace(
                            array(
                                'li_us_cat*',
                                'cbvk_us_cat*',
                                'kl_us_cat*'
                            ),
                            array(
                                'LiUsCat_',
                                'CbvkUsCat_',
                                'KlUsCat_'
                            ),
                            $id,
                            $count
                        );

                        if ($count > 0) {
                            $response->setDataValue(
                                $id,
                                'ns1:LookupUserResponse',
                                "ns1:RequestedItem[$i]",
                                "ns1:BibliographicId[$bibliographicIdKey]",
                                'ns1:BibliographicItemId',
                                'ns1:BibliographicItemIdentifier'
                            );
                        }
                    }
                }
            }


            $positionHidden = false;
            if (in_array($this->agency, $this->libsWithClavius)) {

                // Now we will move NeedBeforeDate to standard PickupExpiryDate if necessary

                $expire = $response->getRelative(
                    $requestedItem,
                    'PickupExpiryDate'
                );

                if ($expire === null) {
                    $extExpire = $response->getRelative(
                        $requestedItem,
                        'Ext',
                        'NeedBeforeDate'
                    );

                    $response->setDataValue(
                        $extExpire,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:PickupExpiryDate'
                    );
                }

                // Normalize cannotCancel flag

                if ($type === 'Stack Retrieval') {

                    // Mark request as non returnable
                    $response->setDataValue(
                        array(), // Empty array is used for empty XML elements
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:Ext',
                        'ns1:NonReturnableFlag'
                    );

                    $positionHidden = true;

                    $response->setDataValue(
                        '0',
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:HoldQueuePosition'
                    );
                }
            }

            // Now we will move ExtPosition to standard Position location if necessary

            if (!$positionHidden) {

                $position = $response->getRelative(
                    $requestedItem,
                    'HoldQueuePosition'
                );

                if ($position === null) {
                    $extPosition = $response->getRelative(
                        $requestedItem,
                        'Ext',
                        'HoldQueueLength'
                    );

                    $response->setDataValue(
                        $extPosition,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:HoldQueuePosition'
                    );
                }
            }

            if ($this->agency === 'ABA008') { // NLK


                $location = $response->getRelative(
                    $requestedItem,
                    'PickupLocation'
                );

                if ($location !== null) {
                    $parts = explode("@", $location);
                    $location = $this->translator->translate(
                        isset($parts[0])
                            ? $this->source . '_location_' . $parts[0]
                            : ''
                    );

                    $response->setDataValue(
                        $location,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:PickupLocation'
                    );
                }

            }
        }
    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected function normalizeLookupAgencyLocations(JsonXML &$response)
    {
        if(!in_array($this->agency, $this->libsNeedsPickUpLocation)) {
            $response->unsetDataValue('ns1:LookupAgencyResponse', 'ns1:AgencyAddressInformation');
        }

        if ($this->agency === 'ABG001') { // mkp

            $locations = $response->getArray('LookupAgencyResponse', 'Ext', 'LocationName', 'LocationNameInstance');

            $skipped_count = 0;
            foreach ($locations as $i => $location) {

                $i -= $skipped_count;

                $id = $response->getRelative($location, 'LocationNameLevel');
                $name = $response->getRelative($location, 'LocationNameValue');
                $address = $response->getRelative($location, 'Ext', 'PhysicalAddress', 'UnstructuredAddress', 'UnstructuredAddressData');

                if ($id === null) {
                    ++$skipped_count;
                    continue;
                }

                $response->setDataValue(
                    array(
                        'ns1:AgencyAddressRoleType' => $id,
                        'ns1:PhysicalAddress' => array(
                            'ns1:UnstructuredAddress' => array(
                                'ns1:UnstructuredAddressType' => $name,
                                'ns1:UnstructuredAddressData' => $address
                            )
                        )
                    ),
                    'ns1:LookupAgencyResponse',
                    "ns1:AgencyAddressInformation[$i]"
                );
            }
        }
    }

    /**
     * @param JsonXML $response
     * @throws ILSException
     */
    protected function normalizeLookupUserLoanedItemsHistory(JsonXML &$response)
    {
        if ($this->agency != 'UOG505') {
            throw new ILSException('driver_no_history');
        }

    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected function normalizeLookupUserProfile(JsonXML &$response)
    {

        // Move email where it belongs
        if ($this->agency === 'KHG001' || $this->agency === 'SOG504') {

            $uof = $response->get(
                'LookupUserResponse',
                'UserOptionalFields'
            );

            // Move email from PhysicalAddress to UserAddressInformation
            $email = $response->getRelative(
                $uof,
                'UserAddressInformation',
                'PhysicalAddress',
                'ElectronicAddressData'
            );

            $electronicAddresses = $response->getArrayRelative(
                $uof,
                'UserAddressInformation',
                'ElectronicAddress'
            );

            $userAddressInformations = $response->getArrayRelative(
                $uof,
                'UserAddressInformation'
            );

            // We are going to append a new ElectronicAddress & UserAddressInformation ;)
            $countOfElectronicAddresses = sizeof($electronicAddresses);
            $countOfUserAddressInformations = sizeof($userAddressInformations);

            $response->setDataValue(
                array(
                    'ns1:ElectronicAddressType' => 'mailto',
                    'ns1:ElectronicAddressDate' => $email
                ),
                'ns1:LookupUserResponse',
                'ns1:UserOptionalFields',
                "ns1:UserAddressInformation[$countOfUserAddressInformations]",
                "ns1:ElectronicAddress[$countOfElectronicAddresses]"
            );
        }
    }

    /**
     * @param string $status
     * @return null|string
     */
    protected function normalizeStatus(string $status)
    {

        $newStatus = null;
        if ($status !== null) {
            // Let's correct improper statuses
            if ($status === 'Available on Shelf')
                $newStatus = 'Available On Shelf';
            else if ($status === 'Not available')
                $newStatus = 'Not Available';
            else if ($status === 'Available for Pickup')
                $newStatus = 'On Order';
            else if ($status === 'Available For Pickup')
                $newStatus = 'On Order';
            else if ($status === 'Waiting To Be Reshelved')
                $newStatus = 'In Process';
        }

        return $newStatus;
    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected function normalizeLookupItemStatus(JsonXML &$response)
    {

        $status = $response->get('LookupItemResponse', 'ItemOptionalFields', 'CirculationStatus');

        $newStatus = $this->normalizeStatus($status);

        // We always need department to determine normalization, so parse it unconditionally
        $department = null;

        $locations = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'Location');
        foreach ($locations as $locElement) {
            $level = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameLevel');
            $value = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameValue');
            if ($value !== null) {
                if ($level == '1') {
                    // We're only looking for the department ..
                    $department = $value;
                    break;
                }
            }
        }

        if ($this->agency === 'ABA008') { // NLK

            // parse department properly

            if ($department !== null) {

                $parts = explode("@", $department);
                $translate = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
                $parts = explode(" ", $translate, 2);
                $department = isset($parts[0]) ? $parts[0] : '';
                $collection = isset($parts[1]) ? $parts[1] : '';

                $response->setDataValue(
                    array(
                        array(
                            'ns1:LocationName' => array(
                                'ns1:LocationNameInstance' => array(
                                    'ns1:LocationNameLevel' => '1',
                                    'ns1:LocationNameValue' => $department
                                )
                            )
                        ),
                        array(
                            'ns1:LocationName' => array(
                                'ns1:LocationNameInstance' => array(
                                    'ns1:LocationNameLevel' => '2',
                                    'ns1:LocationNameValue' => $collection
                                )
                            )
                        )
                    ),
                    'ns1:LookupItemResponse',
                    'ns1:ItemOptionalFields',
                    'ns1:Location'
                );
            }
        }

        if ($this->agency === 'ABG001') {

            $itemRestriction = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'ItemUseRestrictionType');

            // Always show MKP's hold link, because it is hold for record, not item.

            $restrictions_deleted = 0;
            foreach ($itemRestriction as $i => $item) {

                $i -= $restrictions_deleted;

                if ($item === 'Not For Loan') {

                    $response->unsetDataValue(
                        'ns1:LookupItemResponse',
                        'ns1:ItemOptionalFields',
                        "ns1:ItemUseRestrictionType[$i]"
                    );

                    ++$restrictions_deleted;
                }
            }

            if (($status === 'Circulation Status Undefined') || ($status === 'Not Available') || ($status === 'Lost'))
                $newStatus = 'In Process';
        }

        // Update status only if it have changed
        if ($newStatus !== null)
            $response->setDataValue(
                $newStatus,
                'ns1:LookupItemResponse',
                'ns1:ItemOptionalFields',
                'ns1:CirculationStatus'
            );

        // This condition is very weird ... it would be nice to find out what agency it belongs, to avoid misuse
        if ($department == 'Podlesí') {

            // Only append 'Not For Loan' to the end of item restriction
            $itemRestriction = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'ItemUseRestrictionType');
            $i = sizeof($itemRestriction);

            $response->setDataValue(
                'Not For Loan',
                'ns1:LookupItemResponse',
                'ns1:ItemOptionalFields',
                "ns1:ItemUseRestrictionType[$i]"
            );
        }
    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected function normalizeLookupItemSetStatus(JsonXML &$response)
    {
        if (
            in_array($this->agency, $this->libsWithClavius)
            || $this->agency === 'AAA001'
            || $this->agency === 'SOG504'
        ) {
            $holdingSets = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet');

            $response->unsetDataValue('ns1:LookupItemSetResponse', 'ns1:BibInformation', 'ns1:HoldingsSet');

            // Rewind holdingSets to ItemInformation ..
            foreach ($holdingSets as $i => $holdingSet) {
                $itemInformation = $response->getRelative($holdingSet, 'ItemInformation');
                $response->setDataValue(
                    $itemInformation,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]"
                );

                if (in_array($this->agency, $this->libsWithClavius)) {
                    $itemRestrictions = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'ItemUseRestrictionType');
                    if (!$itemRestrictions) {
                        $response->setDataValue(
                            'Not For Loan',
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:ItemOptionalFields',
                            "ns1:ItemUseRestrictionType"
                        );
                    } elseif (count($itemRestrictions) == 1) {
                        $response->setDataValue(
                            $itemRestrictions[0],
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:ItemOptionalFields',
                            "ns1:ItemUseRestrictionType"
                        );
                    } else {
                        foreach ($itemRestrictions as $j => $item) {
                            $itemText = JsonXML::getElementText($item);
                            $response->setDataValue(
                                $itemText,
                                'ns1:LookupItemSetResponse',
                                'ns1:BibInformation',
                                'ns1:HoldingsSet',
                                "ns1:ItemInformation[$i]",
                                'ns1:ItemOptionalFields',
                                "ns1:ItemUseRestrictionType[$j]"
                            );
                        }
                    }
                }
            }
        } // End of libsWithARL + AAA001 + SOG504 ...

        if (in_array($this->agency, $this->libsWithARL)) {

            $items = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation');


            // Just make sure it is an array before the manipulation
            $response->unsetDataValue(
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                'ns1:ItemInformation'
            );

            $response->setDataValue(
                $items,
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                'ns1:ItemInformation'
            );

            foreach ($items as $i => $itemInformation) {

                // Fix the status if needed ..
                $status = $response->getRelative($itemInformation, 'ItemOptionalFields', 'CirculationStatus');

                $newStatus = $this->normalizeStatus($status);

                if ($newStatus !== null)
                    $response->setDataValue(
                        $newStatus,
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        "ns1:ItemInformation[$i]",
                        'ns1:ItemOptionalFields',
                        'ns1:CirculationStatus'
                    );

                // Move DateDue to proper position
                if ($status == 'On Loan') {
                    $dueDate = $response->getRelative($itemInformation, 'DateDue');
                    if ($dueDate === null) {
                        $dueDate = $response->getRelative($itemInformation, 'ItemOptionalFields', 'DateDue');

                        if ($dueDate !== null) {
                            $response->setDataValue(
                                $dueDate,
                                'ns1:LookupItemSetResponse',
                                'ns1:BibInformation',
                                'ns1:HoldingsSet',
                                "ns1:ItemInformation[$i]",
                                'ns1:DateDue'
                            );
                        }
                    }
                }

                // Find new item_id if not present as expected ..
                $item_id = $response->getRelative($itemInformation, 'ItemId', 'ItemIdentifierValue');

                if ($item_id === null) {
                    $new_item_id = $response->getRelative($itemInformation, 'ItemOptionalFields', 'BibliographicDescription', 'ComponentId', 'ComponentIdentifier');

                    if ($new_item_id === null) { // this is for LIA's periodicals (without item_id)
                        $new_item_id = $response->getRelative($itemInformation, 'ItemOptionalFields', 'ItemDescription', 'CopyNumber');
                    }

                    if ($new_item_id !== null) {
                        $response->setDataValue(
                            $new_item_id,
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:ItemId',
                            'ns1:ItemIdentifierValue'
                        );
                    }
                }

                // We always need department to determine normalization, so parse it unconditionally
                $department = null;

                $locations = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'Location');
                foreach ($locations as $locElement) {
                    $level = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameLevel');
                    $value = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameValue');
                    if (!empty($value)) {
                        if ($level == '1') {
                            $department = $value;
                            break;
                        } else if (empty($department) && $level == '4') {
                            $department = $value;
                            break;
                        }
                    }
                }

                // This condition is very weird ... it would be nice to find out what agency it belongs, to avoid misuse
                if ($department == 'Podlesí') {

                    // Only append 'Not For Loan' to the end of item restriction
                    $itemRestrictions = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'ItemUseRestrictionType');
                    $j = sizeof($itemRestrictions);

                    $response->setDataValue(
                        'Not For Loan',
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        "ns1:ItemInformation[$i]",
                        'ns1:ItemOptionalFields',
                        "ns1:ItemUseRestrictionType[$j]"
                    );
                }

            }
        } // End of libsWithARL

        if ($this->agency === 'ABA008') { // NLK

            $holdingSets = $response->get('LookupItemSetResponse', 'BibInformation', 'HoldingsSet');
            $response->unsetDataValue('LookupItemSetResponse', 'BibInformation', 'HoldingsSet');

            // Rewind holdingSets to ItemInformation ..
            foreach ($holdingSets as $i => $holdingSet) {
                $itemInformation = $response->getRelative($holdingSet, 'ItemInformation');
                $response->setDataValue(
                    $itemInformation,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]"
                );

                // Move locations to correct position

                $locations = $response->getArrayRelative($holdingSet, 'Location');
                if (!empty($locations)) {

                    // We always need department to determine normalization, so parse it unconditionally
                    $department = null;

                    foreach ($locations as $locElement) {
                        $level = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameLevel');
                        $value = $response->getRelative($locElement, 'LocationName', 'LocationNameInstance', 'LocationNameValue');
                        if (!empty($value)) {
                            if ($level == '1') {
                                $department = $value;
                                break;
                            }
                        }
                    }

                    if ($department !== null) {

                        // parse department properly

                        $parts = explode("@", $department);
                        $translate = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
                        $parts = explode(" ", $translate, 2);
                        $department = isset($parts[0]) ? $parts[0] : '';
                        $collection = isset($parts[1]) ? $parts[1] : '';

                        $response->setDataValue(
                            array(
                                array(
                                    'ns1:LocationName' => array(
                                        'ns1:LocationNameInstance' => array(
                                            'ns1:LocationNameLevel' => '1',
                                            'ns1:LocationNameValue' => $department
                                        )
                                    )
                                ),
                                array(
                                    'ns1:LocationName' => array(
                                        'ns1:LocationNameInstance' => array(
                                            'ns1:LocationNameLevel' => '2',
                                            'ns1:LocationNameValue' => $collection
                                        )
                                    )
                                )
                            ),
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:ItemOptionalFields',
                            'ns1:Location'
                        );
                    } else
                        // Just move it ..
                        $response->setDataValue(
                            $locations,
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:ItemOptionalFields',
                            'ns1:Location'
                        );
                }
            }


        } // End of NLK (ABA008)

        if (
            $this->agency === 'ABG001'  // mkp
            || in_array($this->agency, $this->libsWithVerbis) // kfbz
        ) {

            $itemInformations = $response->getArray(
                'LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation'
            );

            // Just make sure it is an array before the manipulation
            $response->unsetDataValue(
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                'ns1:ItemInformation'
            );

            $response->setDataValue(
                $itemInformations,
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                'ns1:ItemInformation'
            );

            foreach ($itemInformations as $i => $itemInformation) {
                $dateDue = $response->getRelative($itemInformation, 'ItemOptionalFields', 'DateDue');
                $status = $response->getRelative($itemInformation, 'ItemOptionalFields', 'CirculationStatus');

                if ($status) {
                    $newStatus = $this->normalizeStatus($status);
                    $response->setDataValue(
                        $newStatus,
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        "ns1:ItemInformation[$i]",
                        'ns1:ItemOptionalFields',
                        'ns1:CirculationStatus'
                    );
                }

                if ($dateDue) {
                    $response->setDataValue(
                        $dateDue,
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        "ns1:ItemInformation[$i]",
                        'ns1:DateDue'
                    );
                }
            }

        } // End of MKP
    }

    /**
     * @param JsonXML $response
     * @throws JsonXMLException
     */
    protected
    function normalizeLookupUserLoanedItems(JsonXML &$response)
    {

        $loanedItems = $response->getArray('LookupUserResponse', 'LoanedItem');

        $deleted_items_count = 0;
        foreach ($loanedItems as $i => $loanedItem) {

            $i -= $deleted_items_count;

            $item_id = $response->getRelative(
                $loanedItem,
                'ItemId',
                'ItemIdentifierValue'
            );

            if (!$item_id) {
                // Items without item_id cannot be shown to a user, so skip those
                $deletion_succeeded = $response->unsetDataValue(
                    'ns1:LookupUserResponse',
                    "ns1:LoanedItem[$i]"
                );

                if ($deletion_succeeded)
                    ++$deleted_items_count;

                continue;
            }

            if ($this->agency === 'KHG001' || $this->agency === 'SOG504') {

                // Translate 'dateDue' element to 'DateDue' element
                $dateDue = $response->getRelative(
                    $loanedItem,
                    'dateDue'
                );

                $usesNamespace = array_key_exists('ns1:LookupUserResponse', $response->toJsonObject());

                if ($usesNamespace)
                    $response->setDataValue(
                        $dateDue,
                        'ns1:LookupUserResponse',
                        "ns1:LoanedItem[$i]",
                        'ns1:DateDue'
                    );
                else
                    $response->setDataValue(
                        $dateDue,
                        'LookupUserResponse',
                        "LoanedItem[$i]",
                        'DateDue'
                    );
            }
        }
    }

    /**
     * Set logger instance
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public
    function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}