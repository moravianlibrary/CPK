<?php

namespace CPK\ILS\Logic\XmlTransformation\Denormalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;

interface NCIPDenormalizerInterface
{
    /**
     * Processes denormalizing
     *
     * @param string $request
     * @return mixed
     */
    public function denormalize(string $request);

    /**
     * Denormalize statuses
     *
     * @param JsonXML $request
     * @return mixed
     */
    public function denormalizeLookupItemSetStatus(JsonXML &$request);
}