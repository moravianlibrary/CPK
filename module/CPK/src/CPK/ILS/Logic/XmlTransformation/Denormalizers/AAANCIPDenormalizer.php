<?php

namespace CPK\ILS\Logic\XmlTransformation\Denormalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;

class AAANCIPDenormalizer extends NCIPDenormalizer
{
    public function denormalizeLookupItemSetStatus(JsonXML &$request)
    {
        $bibId = $request->get('LookupItemSet', 'BibliographicId', 'BibliographicItemId', 'BibliographicItemIdentifier');

        $newBibId = '0002' . sprintf('%011d', $bibId);

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