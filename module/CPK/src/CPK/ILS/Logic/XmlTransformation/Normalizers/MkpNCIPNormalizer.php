<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver for MKP system
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

class MkpNCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupAgencyLocations(JsonXML &$response)
    {
        $locations = $response->getArray('LookupAgencyResponse', 'Ext', 'LocationName', 'LocationNameInstance');

        $skipped_count = 0;

        $response->unsetDataValue(
            'ns1:LookupAgencyResponse',
            "ns1:AgencyAddressInformation"
        );

        foreach ($locations as $i => $location) {

            $i -= $skipped_count;

            $id = $response->getRelative($location, 'LocationNameLevel');
            $name = $response->getRelative($location, 'LocationNameValue');
            $address = $response->getRelative(
                $location,
                'Ext',
                'PhysicalAddress',
                'UnstructuredAddress',
                'UnstructuredAddressData'
            );

            if ($id === null) {
                ++$skipped_count;
                continue;
            }

            $response->setDataValue(
                array(
                    'ns1:AgencyAddressRoleType' => $id,
                    'ns1:PhysicalAddress' => array(
                        'ns1:UnstructuredAddress' => array(
                            'ns1:UnstructuredAddressType' => $name,
                            'ns1:UnstructuredAddressData' => $address
                        )
                    )
                ),
                'ns1:LookupAgencyResponse',
                "ns1:AgencyAddressInformation[$i]"
            );
        }
    }

    public function normalizeLookupItemStatus(JsonXML &$response)
    {

        $status = $response->get('LookupItemResponse', 'ItemOptionalFields', 'CirculationStatus');

        $newStatus = $this->normalizeStatus($status);

        // We always need department to determine normalization, so parse it unconditionally
        $department = null;

        $locations = $response->getArray('LookupItemResponse', 'ItemOptionalFields', 'Location');
        foreach ($locations as $locElement) {
            $level = $response->getRelative(
                $locElement,
                'LocationName',
                'LocationNameInstance',
                'LocationNameLevel'
            );
            $value = $response->getRelative(
                $locElement,
                'LocationName',
                'LocationNameInstance',
                'LocationNameValue'
            );
            if ($value !== null) {
                if ($level == '1') {
                    // We're only looking for the department ..
                    $department = $value;
                    break;
                }
            }
        }

        $itemRestriction = $response->getArray(
            'LookupItemResponse',
            'ItemOptionalFields',
            'ItemUseRestrictionType'
        );

        // Always show MKP's hold link, because it is hold for record, not item.

        $restrictions_deleted = 0;
        foreach ($itemRestriction as $i => $item) {

            $i -= $restrictions_deleted;

            if ($item === 'Not For Loan') {

                $response->unsetDataValue(
                    'ns1:LookupItemResponse',
                    'ns1:ItemOptionalFields',
                    "ns1:ItemUseRestrictionType[$i]"
                );

                ++$restrictions_deleted;
            }
        }

        if (($status === 'Circulation Status Undefined') || ($status === 'Not Available') || ($status === 'Lost')) {
            $newStatus = 'In Process';
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
            $itemRestriction = $response->getArray(
                'LookupItemResponse',
                'ItemOptionalFields',
                'ItemUseRestrictionType'
            );
            $i = sizeof($itemRestriction);

            $response->setDataValue(
                'Not For Loan',
                'ns1:LookupItemResponse',
                'ns1:ItemOptionalFields',
                "ns1:ItemUseRestrictionType[$i]"
            );
        }
    }

    public function normalizeLookupItemSetStatus(JsonXML &$response)
    {
        $itemInformations = $response->getArray(
            'LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation'
        );

        // Just make sure it is an array before the manipulation
        $response->unsetDataValue(
            'ns1:LookupItemSetResponse',
            'ns1:BibInformation',
            'ns1:HoldingsSet',
            'ns1:ItemInformation'
        );

        $response->setDataValue(
            $itemInformations,
            'ns1:LookupItemSetResponse',
            'ns1:BibInformation',
            'ns1:HoldingsSet',
            'ns1:ItemInformation'
        );

        foreach ($itemInformations as $i => $itemInformation) {
            $dateDue = $response->getRelative($itemInformation, 'ItemOptionalFields', 'DateDue');
            $status = $response->getRelative($itemInformation, 'ItemOptionalFields', 'CirculationStatus');

            if ($status) {
                $newStatus = $this->normalizeStatus($status);
                $response->setDataValue(
                    $newStatus,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]",
                    'ns1:ItemOptionalFields',
                    'ns1:CirculationStatus'
                );
            }

            if ($dateDue) {
                $response->setDataValue(
                    $dateDue,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]",
                    'ns1:DateDue'
                );
            }
        }
    }
}
