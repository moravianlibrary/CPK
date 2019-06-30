<?php

namespace CPK\ILS\Logic\XmlTransformation\Denormalizers;


use CPK\ILS\Logic\XmlTransformation\JsonXML;

class ArlNCIPDenormalizer extends NCIPDenormalizer
{
    public function denormalizeLookupItemSetStatus(JsonXML &$request)
    {

        $bibId = $request->get(
            'LookupItemSet',
            'BibliographicId',
            'BibliographicItemId',
            'BibliographicItemIdentifier'
        );

        $newBibId = str_replace('LiUsCat_', 'li_us_cat*', $bibId);
        $newBibId = str_replace('CbvkUsCat_', 'cbvk_us_cat*', $newBibId);
	$newBibId = str_replace('KlUsCat_', 'kl_us_cat*', $newBibId);
	$newBibId = str_replace('VyUsCat_', 'vy_us_cat*', $newBibId);

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
