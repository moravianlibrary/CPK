<?php

namespace CPK\ILS\Logic\XmlTransformation\Denormalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;

class TritiusNCIPDenormalizer extends NCIPDenormalizer
{
    protected $agency;

    public function __construct($methodName, $agency)
    {
        parent::__construct($methodName);
        $this->agency = $agency;
    }

    public function denormalizeLookupItemSetStatus(JsonXML &$request)
    {

        $bibId = $request->get('LookupItemSet', 'BibliographicId', 'BibliographicItemId', 'BibliographicItemIdentifier');

        $newBibId = null;
        if ($this->agency === 'SOG504') {
            $newBibId = '00124' . sprintf('%010d', $bibId);
        } elseif ($this->agency === 'KHG001') {
            $newBibId = '00160' . sprintf('%010d', $bibId);
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
}