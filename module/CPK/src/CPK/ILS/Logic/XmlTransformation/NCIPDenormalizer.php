<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 5.5.17
 * Time: 18:25
 */

namespace CPK\ILS\Logic\XmlTransformation;

use CPK\ILS\Driver\NCIPRequests;
use Zend\I18n\Translator\Translator;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;


/**
 * Class NCIPDenormalizer
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class NCIPDenormalizer implements LoggerAwareInterface
{

    /**
     * Name of the XCNCIP2 method that has been used
     *
     * @var string
     */
    protected $methodName;

    /**
     * Library source to communicate with
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
    protected $libsLikeTabor = null;

    /**
     * @var array
     */
    protected $libsLikeLiberec = null;

    /**
     * @var Translator
     */
    protected $translator = null;

    /**
     * NCIPDenormalizer constructor.
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
        $this->libsLikeTabor = $ncipRequests->getLibsLikeTabor();
        $this->libsLikeLiberec = $ncipRequests->getLibsLikeLiberec();
        $this->translator = $translator;
    }

    /**
     * Returns denormalized NCIP request with respect to source & method.
     *
     * @param string $request
     * @return JsonXML
     * @throws JsonXMLException
     */
    public function denormalize(string $request)
    {
        $no_ns2_namespaces = NCIPNormalizer::NAMESPACES;
        unset($no_ns2_namespaces['ns2']);

        $jsonXml = JsonXML::fabricateFromXmlString($request, $no_ns2_namespaces);

        switch ($this->methodName) {
            case 'getStatuses':
                $this->denormalizeLookupItemSetStatus($jsonXml);
                break;
        }

        return $jsonXml;
    }

    /**
     * @param JsonXML $request
     * @throws JsonXMLException
     */
    protected function denormalizeLookupItemSetStatus(JsonXML &$request)
    {

        $bibId = $request->get('LookupItemSet', 'BibliographicId', 'BibliographicItemId', 'BibliographicItemIdentifier');

        $newBibId = null;
        if (in_array($this->agency, $this->libsLikeTabor)) {
            if ($this->agency === 'SOG504') {
                $newBibId = '00124' . sprintf('%010d', $bibId);
            }
        } elseif ($this->agency === 'KHG001') {
            $newBibId = '00160' . sprintf('%010d', $bibId);
        } else if ($this->agency === 'AAA001' || $this->agency === 'SOG504') {
            $newBibId = '0002' . sprintf('%011d', $bibId);
        } else if ($this->agency === 'ZLG001') {
            $newBibId = str_replace('oai:', '', $bibId);
        } else if (in_array($this->agency, $this->libsLikeLiberec)) {
            $newBibId = str_replace('LiUsCat_', 'li_us_cat*', $bibId);
            $newBibId = str_replace('CbvkUsCat_', 'cbvk_us_cat*', $newBibId);
            $newBibId = str_replace('KlUsCat_', 'kl_us_cat*', $newBibId);
        }

        if ($newBibId !== null)
            $request->setDataValue(
                $newBibId,
                'ns1:LookupItemSet',
                'ns1:BibliographicId',
                'ns1:BibliographicItemId',
                'ns1:BibliographicItemIdentifier'
            );
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