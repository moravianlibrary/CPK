<?php
/**
 * Created by PhpStorm.
 * User: kozlovsky
 * Date: 5.5.17
 * Time: 18:25
 */

namespace CPK\ILS\Logic\XmlTransformation;

use CPK\ILS\Driver\NCIPRequests;
use VuFind\SimpleXML;
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
     * @var NCIPRequests
     */
    protected $ncipRequests;

    /**
     * @var \SimpleXMLElement
     */
    protected $processedRequest;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $libsLikeTabor = null;

    /**
     * @var array
     */
    protected $libsLikeLiberec = null;

    /**
     * NCIPNormalizer constructor.
     *
     * @param string $methodName
     * @param string $source
     * @param NCIPRequests $ncipRequests
     */
    public function __construct(string $methodName, string $source, NCIPRequests $ncipRequests)
    {
        $this->methodName = $methodName;
        $this->source = $source;
        $this->ncipRequests = $ncipRequests;
        $this->libsLikeTabor = $ncipRequests->getLibsLikeTabor();
        $this->libsLikeLiberec = $ncipRequests->getLibsLikeLiberec();
    }

    /**
     * Returns normalized NCIP request with respect to source & method.
     *
     * @param string $stringXml
     * @return \SimpleXMLElement
     */
    public function normalize(string $stringXml)
    {
        $jsonXml = JsonXML::fabricateFromXmlString($stringXml, self::NAMESPACES);

        switch ($this->methodName) {
            case 'getMyFines':
                break;
            case 'getMyHolds':
                $this->normalizeRequestedItems($jsonXml);
                break;
            case 'cancelHolds':
                break;
            case 'placeHold':
                break;
            case 'getPickupLocations':
                break;
            case 'getMyHistoryPage':
                break;
            case 'getMyProfile':
                break;
            case 'getBlocks':
                break;
            case 'getStatus':
                break;
            case 'getStatuses':
                break;
            case 'renewMyItems':
                break;
            case 'getMyTransactions':
                break;
        }

        $problem = array(
            'ns1:Problem' => array(
                'ns1:Type' => 'Serious Testing Problem!',
                'ns1:Value' => ' Oh no !! :\'(',
            )
        );

        $jsonXml['ns1:LookupUserResponse'] = array_merge($jsonXml['ns1:LookupUserResponse'], $problem);

        $normalizedXml = $jsonXml->toSimpleXml();

        return $normalizedXml;
    }

    /**
     * @param JsonXML $jsonXmlResponse
     * @return JsonXML
     */
    protected function normalizeRequestedItems(JsonXML $jsonXmlResponse)
    {
        $normalizedJsonXmlResponse = clone $jsonXmlResponse;

        // TODO: Rewrite all useXPath (even in XCNCIP2) to this nicer API ...

        $requestedItems = $jsonXmlResponse->first("LookupUserResponse")->all("RequestedItem");


        foreach ($requestedItems as $requestedItem) {

            $extId = $requestedItem->first('Ext', 'BibliographicDescription', 'BibliographicItemId', 'BibliographicItemIdentifier');

            $id = $requestedItem->first('BibliographicId', 'BibliographicItemId', 'BibliographicItemIdentifier');

            unset($id[0]);
            unset($requestedItem->first('BibliographicId')[0]);

            if (!$id) {
                $extId = $requestedItem->first('Ext', 'BibliographicDescription', 'BibliographicItemId', 'BibliographicItemIdentifier');

                if (!$extId)
                    $this->logger->err("Could not normalize NCIP inbound for method " . $this->methodName, ["! Missing 'BibliographicItemId' ..."]);
                else {
                    // Move ext Id to normal Id ..
                    $requestedItem
                        ->prependChild(
                            'BibliographicId/BibliographicItemId/BibliographicItemIdentifier',
                            $extId[0],
                            1 // It must be right after RequestId ..
                        );
                }
            }


            $title = $requestedItem->first('Title');

            if (!$title) {
                $extTitle = $requestedItem->first('Ext', 'BibliographicDescription', 'Title');

                if (!$extTitle)
                    $this->logger->err("Could not normalize NCIP inbound for method " . $this->methodName, ["! Missing 'Title' ..."]);
                else {
                    // Move ext Title to normal Title ..
                    $requestedItem->addChild('Title', $extTitle[0]);
                }

            }

            return $normalizedJsonXmlResponse;

            //TODO

            $location = $this->useXPath($requestedItem, 'PickupLocation');
            $expire = $this->useXPath($requestedItem, 'PickupExpiryDate');
            $item_id = $this->useXPath($requestedItem, 'ItemId/ItemIdentifierValue');

            $position = $this->useXPath($requestedItem, 'HoldQueuePosition');

            // Deal with Liberec.
            if (empty($position))
                $position = $this->useXPath($requestedItem, 'Ext/HoldQueueLength');


            $type = $requestedItem->first('RequestType');
            if (in_array($this->agency, $this->libsLikeLiberec)) {
                $title = $extTitle; // TODO temporary solution for periodicals
                if ((!empty($type)) && ((string)$type[0] == 'w'))
                    $cannotCancel = true;
            }

            // Deal with Tabor.
            if (in_array($this->agency, $this->libsLikeTabor)) {

                // $type == 'Hold' => rezervace; $type == 'Stack Retrieval' => objednávka
                if (empty($expire))
                    $expire = $this->useXPath($requestedItem, 'Ext/NeedBeforeDate');

                if ((!empty($type)) && ((string)$type[0] == 'Stack Retrieval')) {
                    $cannotCancel = true;
                    $position = null; // hide queue position
                }
            }

            if ($this->agency === 'ABA008') { // NLK
                $parts = explode("@", (string)$location[0]);
                $location[0] = $this->translator->translate(isset($parts[0]) ? $this->source . '_location_' . $parts[0] : '');
                $additId = empty($item_id) ? '' : (string)$item_id[0];
                $additRequest = $this->requests->lookupItem($additId, $patron);
                try {
                    $additResponse = $this->sendRequest($additRequest);
                    $id = $this->useXPath($additResponse, 'LookupItemResponse/ItemOptionalFields/BibliographicDescription/BibliographicItemId/BibliographicItemIdentifier');
                } catch (ILSException $e) {
                }
            }
        }

        if (in_array($this->agency, $this->libsLikeLiberec)) {
            $bib_id = str_replace('li_us_cat*', 'LiUsCat_', $bib_id);
            $bib_id = str_replace('cbvk_us_cat*', 'CbvkUsCat_', $bib_id);
            $bib_id = str_replace('kl_us_cat*', 'KlUsCat_', $bib_id);
        }

        return $jsonXmlResponse;
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