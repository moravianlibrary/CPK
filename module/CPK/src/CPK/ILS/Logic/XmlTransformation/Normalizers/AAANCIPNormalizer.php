<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver fro test system with sigla AAA001
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Kozlovsky Jiri <Jiri.Kozlovsky@mzk.cz>
 * @author   Inhliziian Bohdan <inhliziian@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */

namespace CPK\ILS\Logic\XmlTransformation\Normalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;

class AAANCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupItemSetStatus(JsonXML &$response)
    {
        $holdingSets = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet');

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

            $this->normalizeItemRestrictionType($response, $itemInformation, $i);
        }
    }
}