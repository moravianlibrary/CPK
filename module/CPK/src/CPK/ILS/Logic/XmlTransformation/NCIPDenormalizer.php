<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 5.5.17
 * Time: 18:25
 */

namespace CPK\ILS\Logic\XmlTransformation;


/**
 * Class NCIPDenormalizer
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class NCIPDenormalizer
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
     * NCIPDenormalizer constructor.
     *
     * @param string $methodName
     * @param string $source
     */
    public function __construct(string $methodName, string $source)
    {
        $this->methodName = $methodName;
        $this->source = $source;
    }

    /**
     * Returns denormalized NCIP request with respect to source & method.
     *
     * @param string $request
     * @return string
     */
    public function denormalize(string $request)
    {
        return $request;
    }
}