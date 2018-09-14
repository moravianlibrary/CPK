<?php

namespace CPK\ILS\Logic\XmlTransformation\Normalizers;

use CPK\ILS\Driver\NCIPRequests;
use CPK\ILS\Logic\XmlTransformation\JsonXML;
use VuFind\Exception\ILS as ILSException;
use Zend\I18n\Translator\Translator;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

/**
 * Class NCIPNormalizer
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class NCIPNormalizer implements LoggerAwareInterface, NCIPNormalizerInterface
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
        $this->libsNeedsPickUpLocation = $ncipRequests->getLibsNeedsPickUpLocation();
        $this->translator = $translator;
    }

    /**
     * Returns normalized NCIP request with respect to source & method.
     *
     * @param string $stringXml
     * @return JsonXML
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

    public function normalizeLookupUserBlocksAndTraps(JsonXML &$response){}

    public function normalizeRequestedItems(JsonXML &$response)
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

            $positionHidden = false;

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
        }
    }

    public function normalizeLookupAgencyLocations(JsonXML &$response)
    {
        if(!in_array($this->agency, $this->libsNeedsPickUpLocation)) {
            $response->unsetDataValue('ns1:LookupAgencyResponse', 'ns1:AgencyAddressInformation');
        }
    }

    public function normalizeLookupUserLoanedItemsHistory(JsonXML &$response)
    {
        if ($this->agency != 'UOG505') {
            throw new ILSException('driver_no_history');
        }
    }

    public function normalizeLookupUserProfile(JsonXML &$response){}

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

    public function normalizeLookupItemStatus(JsonXML &$response)
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

    public function normalizeLookupItemSetStatus(JsonXML &$response){}

    public function normalizeLookupUserLoanedItems(JsonXML &$response)
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
        }
    }

    protected function normalizeItemRestrictionType(&$response, &$itemInformation, $itemId) {
        $itemRestrictions = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'ItemUseRestrictionType');

        if (!$itemRestrictions) {
            $response->setDataValue(
                '',
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                "ns1:ItemInformation[$itemId]",
                'ns1:ItemOptionalFields',
                "ns1:ItemUseRestrictionType"
            );
        } elseif (count($itemRestrictions) == 1) {
            //renew element
            $response->setDataValue(
                "",
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                "ns1:ItemInformation[$itemId]",
                'ns1:ItemOptionalFields',
                "ns1:ItemUseRestrictionType"
            );
            $response->setDataValue(
                $itemRestrictions[0],
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                "ns1:ItemInformation[$itemId]",
                'ns1:ItemOptionalFields',
                "ns1:ItemUseRestrictionType[0]"
            );

            $isOrderable = $itemRestrictions[0] === "Orderable";
        } else {
            foreach ($itemRestrictions as $j => $item) {
                $itemText = JsonXML::getElementText($item);
                $response->setDataValue(
                    $itemText,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$itemId]",
                    'ns1:ItemOptionalFields',
                    "ns1:ItemUseRestrictionType[$j]"
                );

                if ($itemText === "Orderable") {
                    $isOrderable = true;
                }
            }
        }

        if (isset($isOrderable) && $isOrderable === false) {
            $j = sizeof($itemRestrictions);
            $response->setDataValue(
                'Hide Link',
                'ns1:LookupItemSetResponse',
                'ns1:BibInformation',
                'ns1:HoldingsSet',
                "ns1:ItemInformation[$itemId]",
                "ns1:ItemOptionalFields",
                "ns1:ItemUseRestrictionType[$j]"
            );
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
}