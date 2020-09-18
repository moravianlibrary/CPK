<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver for Verbis system
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

class VerbisNCIPNormalizer extends NCIPNormalizer
{
    public function normalizeLookupUserBlocksAndTraps(JsonXML &$response)
    {
        $accountDetails = $response->getArray('LookupUserResponse', 'UserFiscalAccount', 'AccountDetails');

        foreach ($accountDetails as $i => $accountDetail) {
            $actionType = $response->getRelative(
                $accountDetail,
                'FiscalTransactionInformation',
                'FiscalActionType'
            );

            if ($actionType !== null) {
                $response->setDataValue(
                    $actionType,
                    'ns1:LookupUserResponse',
                    'ns1:UserFiscalAccount',
                    "ns1:AccountDetails[$i]",
                    'FiscalTransactionInformation',
                    'FiscalTransactionDescription'
                );
            }
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
            }
        }
    }

    public function normalizeLookupUserLoanedItemsHistory(JsonXML &$response)
    {
    }
}