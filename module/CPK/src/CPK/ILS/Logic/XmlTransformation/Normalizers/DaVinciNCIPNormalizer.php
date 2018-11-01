<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver for DaVinci system
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
use VuFind\Exception\ILS as ILSException;

class DaVinciNCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupUserBlocksAndTraps(JsonXML &$response)
    {
        throw new ILSException('driver_no_fines');
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

        if ($department !== null) {

            $parts = explode("@", $department);
            $translate = $this->translator->translate(
                isset($parts[0]) ? $this->source . '_location_' . $parts[0] : ''
            );
            $parts = explode(" ", $translate, 2);
            $department = isset($parts[0]) ? $parts[0] : '';
            $collection = isset($parts[1]) ? $parts[1] : '';

            $response->unsetDataValue(
                'LookupItemResponse', 'ItemOptionalFields', 'Location', 'LocationName'
            );
            $response->unsetDataValue(
                'LookupItemResponse', 'ItemOptionalFields', 'Location', 'LocationType'
            );

            $response->setDataValue(
                array(
                    array(
                        'ns1:LocationName' => array(
                            'ns1:LocationNameInstance' => array(
                                'ns1:LocationNameLevel' => '1',
                                'ns1:LocationNameValue' => $department
                            )
                        )
                    ),
                    array(
                        'ns1:LocationName' => array(
                            'ns1:LocationNameInstance' => array(
                                'ns1:LocationNameLevel' => '2',
                                'ns1:LocationNameValue' => $collection
                            )
                        )
                    )
                ),
                'ns1:LookupItemResponse',
                'ns1:ItemOptionalFields',
                'ns1:Location'
            );
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
        $this->normalizeLookupItemStatus($response);
    }

    public function normalizeRequestedItems(JsonXML &$response)
    {
        $requestedItems = $response->getArray('LookupUserResponse', 'RequestedItem');

        foreach ($requestedItems as $i => $requestedItem) {

            $extBibliographicDescription = $response->getRelative(
                $requestedItem,
                'Ext',
                'BibliographicDescription'
            );

            if ($extBibliographicDescription !== null) {

                // Now we will move BibId from Ext to it's standard place if necessary

                $bibliographicIds = $response->getRelative(
                    $requestedItem,
                    'BibliographicId'
                );

                $countOfBibIds = sizeof($bibliographicIds);

                // Check if it is really necessary
                $tryToMoveIdFromExt = false;
                if ($countOfBibIds === 0) {
                    $tryToMoveIdFromExt = true;
                } else {
                    $found = false;
                    foreach ($bibliographicIds as $bibliographicIdKey => $bibliographicId) {
                        $found = $response->getRelative(
                                $bibliographicId,
                                'BibliographicItemIdentifier'
                            ) !== null;

                        if ($found)
                            break;
                    }

                    if (!$found) {
                        $tryToMoveIdFromExt = true;
                    }
                }

                if ($tryToMoveIdFromExt) {

                    $extId = $response->getRelative(
                        $extBibliographicDescription,
                        'BibliographicItemId',
                        'BibliographicItemIdentifier'
                    );

                    $response->setDataValue(
                        $extId,
                        'ns1:LookupUserResponse',
                        count($requestedItems) > 1 ? "ns1:RequestedItem[$i]" : "ns1:RequestedItem",
                        "ns1:BibliographicId[$countOfBibIds]",
                        'ns1:BibliographicItemId',
                        'ns1:BibliographicItemIdentifier'
                    );

                }

                // Now we will move ExtTitle to standard Title location

                $title = $response->getRelative(
                    $requestedItem,
                    'Title'
                );

                if ($title === null) {

                    $extTitle = $response->getRelative(
                        $extBibliographicDescription,
                        'Title'
                    );

                    $response->setDataValue(
                        $extTitle,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:Title'
                    );
                }

                // Reload current requested item to apply the changes for the rest of current function
                $requestedItem = $response->getArray('LookupUserResponse', 'RequestedItem')[$i];
            }

            $positionHidden = false;

            // Now we will move ExtPosition to standard Position location if necessary

            if (!$positionHidden) {

                $position = $response->getRelative(
                    $requestedItem,
                    'HoldQueuePosition'
                );

                if ($position === null) {
                    $extPosition = $response->getRelative(
                        $requestedItem,
                        'Ext',
                        'HoldQueueLength'
                    );

                    $response->setDataValue(
                        $extPosition,
                        'ns1:LookupUserResponse',
                        "ns1:RequestedItem[$i]",
                        'ns1:HoldQueuePosition'
                    );
                }
            }

            $location = $response->getRelative(
                $requestedItem,
                'PickupLocation'
            );

            if ($location !== null) {
                $parts = explode("@", $location);
                $location = $this->translator->translate(
                    isset($parts[0])
                        ? $this->source . '_location_' . $parts[0]
                        : ''
                );

                $response->setDataValue(
                    $location,
                    'ns1:LookupUserResponse',
                    "ns1:RequestedItem[$i]",
                    'ns1:PickupLocation'
                );
            }
        }
    }
}