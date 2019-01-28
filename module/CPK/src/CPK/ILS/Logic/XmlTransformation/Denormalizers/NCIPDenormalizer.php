<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 5.5.17
 * Time: 18:25
 */

namespace CPK\ILS\Logic\XmlTransformation\Denormalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;
use CPK\ILS\Logic\XmlTransformation\Normalizers\NCIPNormalizer;


/**
 * Class NCIPDenormalizer
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class NCIPDenormalizer implements LoggerAwareInterface, NCIPDenormalizerInterface
{
    /**
     * Name of the XCNCIP2 method that has been used
     *
     * @var string
     */
    protected $methodName;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * NCIPDenormalizer constructor.
     *
     * @param string $methodName
     */
    public function __construct(string $methodName)
    {
        $this->methodName = $methodName;
    }

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

    public function denormalizeLookupItemSetStatus(JsonXML &$request){}

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