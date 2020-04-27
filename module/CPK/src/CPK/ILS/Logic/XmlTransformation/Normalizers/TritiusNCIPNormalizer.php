<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver for Tritius systems
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

class TritiusNCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupUserLoanedItemsHistory(JsonXML &$response)
    {
        throw new ILSException('driver_no_history');
    }

    public function normalizeLookupAgencyLocations(JsonXML &$response)
    {
        $response->unsetDataValue('LookupAgencyResponse', 'AgencyAddressInformation');
    }

    public function normalizeLookupUserProfile(JsonXML &$response)
    {
        $uai = $response->get(
            'LookupUserResponse',
            'UserOptionalFields',
            'UserAddressInformation'
        );

        // Move email from PhysicalAddress to UserAddressInformation -> ElectronicAddress
        $email = null;
        foreach( $uai as $addressInfo) {
            $potencialEmail = $response->getRelative(
                $addressInfo,
                'PhysicalAddress',
                'ElectronicAddressData'
            );
            $email = $potencialEmail ?? null;
        }

        $electronicAddresses = $response->getArrayRelative(
            $uai,
            'ElectronicAddress'
        );

        // We are going to append a new ElectronicAddress & UserAddressInformation ;)
        $countOfElectronicAddresses = sizeof($electronicAddresses);
        $countOfUserAddressInformations = sizeof($uai);

        $namespace = array_key_exists('ns1:LookupUserResponse', $response->toJsonObject()) ? 'ns1:' : '';

        $response->setDataValue(
            array(
                $namespace . 'ElectronicAddressType' => 'mailto',
                $namespace . 'ElectronicAddressData' => $email
            ),
            $namespace . 'LookupUserResponse',
            $namespace . 'UserOptionalFields',
            $namespace . "UserAddressInformation[$countOfUserAddressInformations]",
            $namespace . "ElectronicAddress[$countOfElectronicAddresses]"
        );
    }

    public function normalizeLookupUserLoanedItems(JsonXML &$response)
    {
        $loanedItems = $response->getArray('LookupUserResponse', 'LoanedItem');

        foreach ($loanedItems as $i => $loanedItem) {
            // Translate 'dateDue' element to 'DateDue' element
            $dateDue = $response->getRelative(
                $loanedItem,
                'dateDue'
            );

            $namespace = array_key_exists('ns1:LookupUserResponse', $response->toJsonObject()) ? 'ns1:' : '';

            $response->setDataValue(
                $dateDue,
                $namespace . 'LookupUserResponse',
                $namespace . (count($loanedItems) > 1) ? "LoanedItem[$i]" : 'LoanedItem',
                $namespace . 'DateDue'
            );

            $renewalNotPermitted = $response->getRelative(
                $loanedItem,
                'Ext',
                'RenewalNotPermitted'
            );

            if(count($loanedItems) > 1) {
                $response->unsetDataValue(
                    $namespace . 'LookupUserResponse',
                    $namespace . 'LoanedItem',
                    $i,
                    $namespace . 'Ext',
                    $namespace . 'RenewalNotPermitted'
                );
            } else {
                $response->unsetDataValue(
                    $namespace . 'LookupUserResponse',
                    $namespace . 'LoanedItem',
                    $namespace . 'Ext',
                    $namespace . 'RenewalNotPermitted'
                );
            }
            if($renewalNotPermitted == "true") {
                $response->setDataValue(
                    '',
                    $namespace . 'LookupUserResponse',
                    $namespace . (count($loanedItems) > 1) ? "LoanedItem[$i]" : 'LoanedItem',
                    $namespace . 'Ext',
                    $namespace . 'RenewalNotPermitted'
                );
            }
        }
    }

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

        $items = $response->getArray('LookupItemSetResponse', 'BibInformation', 'HoldingsSet', 'ItemInformation');

        // Move DateDue to proper position
        foreach ($items as $i => $itemInformation) {
            $dueDate = $response->getRelative($itemInformation, 'DateDue');
            if ($dueDate === null) {
                $dueDate = $response->getRelative(
                    $itemInformation, 'ItemOptionalFields', 'DateDue'
                );

                if ($dueDate !== null) {
                    $response->setDataValue(
                        $dueDate,
                        'ns1:LookupItemSetResponse',
                        'ns1:BibInformation',
                        'ns1:HoldingsSet',
                        (count($items) > 1) ? "ns1:ItemInformation[$i]" : 'ns1:ItemInformation',
                        'ns1:DateDue'
                    );
                }
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
                        array('li_us_cat*', 'cbvk_us_cat*', 'kl_us_cat*'),
                        array('LiUsCat_', 'CbvkUsCat_', 'KlUsCat_'),
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
