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
        $uof = $response->get(
            'LookupUserResponse',
            'UserOptionalFields'
        );

        // Move email from PhysicalAddress to UserAddressInformation
        $email = $response->getRelative(
            $uof,
            'UserAddressInformation',
            'PhysicalAddress',
            'ElectronicAddressData'
        );

        $electronicAddresses = $response->getArrayRelative(
            $uof,
            'UserAddressInformation',
            'ElectronicAddress'
        );

        $userAddressInformations = $response->getArrayRelative(
            $uof,
            'UserAddressInformation'
        );

        // We are going to append a new ElectronicAddress & UserAddressInformation ;)
        $countOfElectronicAddresses = sizeof($electronicAddresses);
        $countOfUserAddressInformations = sizeof($userAddressInformations);

        $response->setDataValue(
            array(
                'ns1:ElectronicAddressType' => 'mailto',
                'ns1:ElectronicAddressDate' => $email
            ),
            'ns1:LookupUserResponse',
            'ns1:UserOptionalFields',
            "ns1:UserAddressInformation[$countOfUserAddressInformations]",
            "ns1:ElectronicAddress[$countOfElectronicAddresses]"
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

            $usesNamespace = array_key_exists('ns1:LookupUserResponse', $response->toJsonObject());

            if ($usesNamespace) {
                $response->setDataValue(
                    $dateDue,
                    'ns1:LookupUserResponse',
                    "ns1:LoanedItem[$i]",
                    'ns1:DateDue'
                );
            } else {
                $response->setDataValue(
                    $dateDue,
                    'LookupUserResponse',
                    "LoanedItem[$i]",
                    'DateDue'
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