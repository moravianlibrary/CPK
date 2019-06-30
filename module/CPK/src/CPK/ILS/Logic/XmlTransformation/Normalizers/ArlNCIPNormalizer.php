<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver for ARL systems
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

class ArlNCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupUserLoanedItemsHistory(JsonXML &$response)
    {
        throw new ILSException('driver_no_history');
    }

    public function normalizeLookupItemSetStatus(JsonXML &$response)
    {
        $items = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation');


        // Just make sure it is an array before the manipulation
        $response->unsetDataValue(
            'ns1:LookupItemSetResponse',
            'ns1:BibInformation',
            'ns1:HoldingsSet',
            'ns1:ItemInformation'
        );

        $response->setDataValue(
            $items,
            'ns1:LookupItemSetResponse',
            'ns1:BibInformation',
            'ns1:HoldingsSet',
            'ns1:ItemInformation'
        );

        foreach ($items as $i => $itemInformation) {

            // Fix the status if needed ..
            $status = $response->getRelative($itemInformation, 'ItemOptionalFields', 'CirculationStatus');

            $newStatus = $this->normalizeStatus($status);

            if ($newStatus !== null)
                $response->setDataValue(
                    $newStatus,
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]",
                    'ns1:ItemOptionalFields',
                    'ns1:CirculationStatus'
                );

            // Move DateDue to proper position
            if ($status == 'On Loan') {
                $dueDate = $response->getRelative($itemInformation, 'DateDue');
                if ($dueDate === null) {
                    $dueDate = $response->getRelative($itemInformation, 'ItemOptionalFields', 'DateDue');

                    if ($dueDate !== null) {
                        $response->setDataValue(
                            $dueDate,
                            'ns1:LookupItemSetResponse',
                            'ns1:BibInformation',
                            'ns1:HoldingsSet',
                            "ns1:ItemInformation[$i]",
                            'ns1:DateDue'
                        );
                    }
                }
            }

            // Find new item_id if not present as expected ..
            $item_id = $response->getRelative($itemInformation, 'ItemId', 'ItemIdentifierValue');

            if ($item_id === null) {
                $new_item_id = $response->getRelative(
                    $itemInformation,
                    'ItemOptionalFields',
                    'BibliographicDescription',
                    'ComponentId',
                    'ComponentIdentifier'
                );

                if ($new_item_id === null) { // this is for LIA's periodicals (without item_id)
                    $new_item_id = $response->getRelative(
                        $itemInformation,
                        'ItemOptionalFields',
                        'ItemDescription',
                        'CopyNumber'
                    );
                }

                if ($new_item_id !== null) {
                    $response->setDataValue(
                        $new_item_id,
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        "ns1:ItemInformation[$i]",
                        'ns1:ItemId',
                        'ns1:ItemIdentifierValue'
                    );
                }
            }

            // We always need department to determine normalization, so parse it unconditionally
            $department = null;

            $locations = $response->getArrayRelative($itemInformation, 'ItemOptionalFields', 'Location');
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
                if (!empty($value)) {
                    if ($level == '1') {
                        $department = $value;
                        break;
                    } else if (empty($department) && $level == '4') {
                        $department = $value;
                        break;
                    }
                }
            }

            $this->normalizeItemRestrictionType($response, $itemInformation, $i);

            // This condition is very weird ... it would be nice to find out what agency it belongs, to avoid misuse
            if ($department == 'Podlesí') {

                // Only append 'Not For Loan' to the end of item restriction
                $itemRestrictions = $response->getArrayRelative(
                    $itemInformation,
                    'ItemOptionalFields',
                    'ItemUseRestrictionType'
                );
                $j = sizeof($itemRestrictions);

                $response->setDataValue(
                    'Not For Loan',
                    'ns1:LookupItemSetResponse',
                    'ns1:BibInformation',
                    'ns1:HoldingsSet',
                    "ns1:ItemInformation[$i]",
                    'ns1:ItemOptionalFields',
                    "ns1:ItemUseRestrictionType[$j]"
                );
            }

        }
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

            $type = $response->getRelative(
                $requestedItem,
                'RequestType'
            );

            // Periodicals request cannot be returned
            if ($type == 'w') {

                // Mark request as non returnable
                $response->setDataValue(
                    array(), // Empty array is used for empty XML elements
                    'ns1:LookupUserResponse',
                    "ns1:RequestedItem[$i]",
                    'ns1:Ext',
                    'ns1:NonReturnableFlag'
                );
            }

            // CamelCase each bibliographic id
            $bibliographicIds = $response->getRelative(
                $requestedItem,
                'BibliographicId'
            );

            foreach ($bibliographicIds as $bibliographicIdKey => $bibliographicId) {

                $id = $response->getRelative(
                    $bibliographicId,
                    'ns1:BibliographicItemIdentifier'
                );

                if ($id !== null) {

                    $id = str_replace(
                        array('li_us_cat*', 'cbvk_us_cat*', 'kl_us_cat*', 'vy_us_cat*'),
                        array('LiUsCat_', 'CbvkUsCat_', 'KlUsCat_', 'VyUsCat_'),
                        $id,
                        $count
                    );

                    if ($count > 0) {
                        $response->setDataValue(
                            $id,
                            'ns1:LookupUserResponse',
                            count($requestedItems) > 1 ? "ns1:RequestedItem[$i]" : "ns1:RequestedItem",
                            "ns1:BibliographicId",
                            'ns1:BibliographicItemId',
                            'ns1:BibliographicItemIdentifier'
                        );
                    }
                }
            }
        }
    }
}
