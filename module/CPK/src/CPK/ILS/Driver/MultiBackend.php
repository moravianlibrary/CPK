<?php
/**
 * Multiple Backend Driver.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2012.
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
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;

use CPK;
use VuFind\Exception\ILS as ILSException, VuFind\ILS\Driver\MultiBackend as MultiBackendBase, CPK\ILS\Driver\SolrIdResolver as SolrIdResolver, CPK\Db\Table\AlephConfigs, CPK\Db\Table\XCNCIP2Configs, CPK\ILS\Driver\Aleph, CPK\ILS\Driver\XCNCIP2;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package ILSdrivers
 * @author Ere Maijala <ere.maijala@helsinki.fi>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class MultiBackend extends MultiBackendBase
{

    /**
     * Search service (used for lookups by barcode number)
     */
    protected $searchService = null;

    /**
     * Resolver for translation of bibliographic ids, used in a case
     * of more bibliographic bases
     *
     * @var \VuFind\ILS\Driver\IdResolver
     */
    protected $idResolver = null;

    /**
     * DB table for aleph configs to effectively initialize drivers
     *
     * @var AlephConfigs $alephConfigsTable
     */
    protected $alephConfigsTable = null;
    
    /**
     * DB table for XCNCIP2 configs to effectively initialize drivers
     * 
     * @var XCNCIP2Configs
     */
    protected $xcncip2ConfigsTable = null;

    public function __construct($configLoader, $ilsAuth, \VuFindSearch\Service $searchService = null)
    {
        parent::__construct($configLoader, $ilsAuth);
        
        $this->searchService = $searchService;
    }

    public function init()
    {
        parent::init();
        
        $this->idResolver = new SolrIdResolver($this->searchService, $this->config);
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $cancelDetails
     *            An array of item and patron data
     *            
     * @return array An array of data on each request including
     *         whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $patronSource = $this->getSource($cancelDetails['patron']['cat_username']);
        
        // MyResearch Controller sends us here all the cancelHolds the user want to process
        // & doesn't care about the institutions the hold belongs in compared to passed
        // patron array - which is always only one in order to properly determine
        // current patron being iterated
        $cancelDetails['details'] = $this->getDetailsFromCurrentSource($patronSource, $cancelDetails['details']);
        
        if (count($cancelDetails['details']) > 0) {
            $driver = $this->getDriver($patronSource);
            if ($driver) {
                foreach ($cancelDetails['details'] as $key => $detail) {
                    // stripIdPrefixed does not work correctly here ..
                    $cancelDetails['details'][$key] = preg_replace("/$patronSource\./", '', $detail);
                }
                
                return $driver->cancelHolds($this->stripIdPrefixes($cancelDetails, $patronSource));
            }
            throw new ILSException('No suitable backend driver found');
        } else
            return [
                'count' => 0
            ];
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, the ILS requires some information on the hold.
     * This function returns the required information, which is then submitted
     * as form data in Hold.php. This value is then extracted by the CancelHolds
     * function.
     *
     * @param array $holdDetails
     *            An array of item data
     *            
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        $source = $this->getSource($holdDetails['id'] ? $holdDetails['id'] : $holdDetails['item_id']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holdDetails = $this->stripIdPrefixes($holdDetails, $source);
            
            $cancelHoldDetails = $driver->getCancelHoldDetails($holdDetails);
            
            // Since addIdPrefixes is unable to ammend source to string & we
            // don't know whether there is a source already, we have to do that this way
            $hasSource = count(explode('.', $cancelHoldDetails)) > 1;
            
            if ($cancelHoldDetails !== null && ! $hasSource) {
                return "$source.$cancelHoldDetails";
            }
            
            return $cancelHoldDetails;
        }
        throw new ILSException('No suitable backend driver found');
    }

    protected function getEmptyStatuses($ids)
    {
        $emptyStatuses = [];
        
        foreach ($ids as $id)
            $emptyStatuses[]['id'] = $id;
        
        return $emptyStatuses;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron
     *            The patron array from patronLogin
     *            
     * @return mixed Array of the patron's holds
     */
    public function getMyHolds($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holds = $driver->getMyHolds($this->stripIdPrefixes($patron, $source));
            
            $this->idResolver->resolveIds($holds, $source, $this->getDriverConfig($source));
            
            return $this->addIdPrefixes($holds, $source, [
                'id',
                'item_id',
                'cat_username'
            ]);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron
     *            The patron array from patronLogin
     *            
     * @return mixed Array of the patron's transactions
     */
    public function getMyTransactions($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $transactions = $driver->getMyTransactions($this->stripIdPrefixes($patron, $source));
            
            $this->idResolver->resolveIds($transactions, $source, $this->getDriverConfig($source));
            
            foreach ($transactions as &$transaction) {
                
                if (isset($transaction['loan_id']) && strpos($transaction['loan_id'], '.') === false) {
                    // Prepend source to loan_id if not there already ..
                    $transaction['loan_id'] = $source . '.' . $transaction['loan_id'];
                }
            }
            
            return $this->addIdPrefixes($transactions, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    public function getPaymentURL($patron, $fine)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if (! $driver || ! $this->methodSupported($driver, 'getPaymentURL', compact('patron', 'fine'))) {
            return null;
        }
        $patron = $this->stripIdPrefixes($patron, $source);
        return $driver->getPaymentURL($patron, $fine);
    }

    public function getProlongRegistrationUrl($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if (! $driver || ! $this->methodSupported($driver, 'getProlongRegistrationUrl', compact('patron'))) {
            return null;
        }
        $patron = $this->stripIdPrefixes($patron, $source);
        return $driver->getProlongRegistrationUrl($patron);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids
     *            The array of record ids to retrieve the status for
     *            
     * @throws ILSException
     * @return array An array of getStatus() return values on success.
     */
    public function getStatuses($ids, $bibId = null, $filter = [], $nextItemToken = null, $user = null)
    {
        // We assume all the ids passed here are being processed by only one ILS/Driver
        if ($bibId === null)
            return $this->getEmptyStatuses($ids);
        
        $source = $this->getSource($bibId);
        $driver = $this->getDriver($source);
        
        $profile = null;
        if ($user != null) {
            $identities = $user->getLibraryCards();
            foreach ($identities as $identity) {
                $profile = $user->libCardToPatronArray($identity);
                $agency = $this->getSource($profile['cat_username']);
                if ($agency === $source) {
                    $profile = $this->stripIdPrefixes($profile, $source);
                    break;
                } else
                    $profile = null;
            }
        }
        
        if ($driver === null)
            throw new ILSException("Driver is undefined!");
        
        if ($driver instanceof XCNCIP2 || $driver instanceof Aleph) {
            
            foreach ($ids as &$id) {
                $id = $this->stripIdPrefixes($id, $source);
            }
            
            $bibId = $this->stripIdPrefixes($bibId, $source);
            
            $statuses = $driver->getStatuses($ids, $profile, $filter, $bibId, $nextItemToken);
            if (($driver instanceof Aleph) && (! empty($statuses)))
                $statuses[0]['usedAleph'] = true;
            return $this->addIdPrefixes($statuses, $source);
        } else
            return parent::getStatuses($ids);
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items. The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails
     *            An array of data required for renewing items
     *            including the Patron ID and an array of renewal IDS
     *            
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $patronSource = $this->getSource($renewDetails['patron']['cat_username']);
        
        $renewDetails['details'] = $this->getDetailsFromCurrentSource($patronSource, $renewDetails['details']);
        
        if (count($renewDetails['details']) > 0) {
            $driver = $this->getDriver($patronSource);
            if ($driver) {
                foreach ($renewDetails['details'] as $key => $detail) {
                    // stripIdPrefixed does not work correctly here ..
                    $renewDetails['details'][$key] = preg_replace("/$patronSource\./", '', $detail);
                }
                
                return $driver->renewMyItems($this->stripIdPrefixes($renewDetails, $patronSource));
            }
            throw new ILSException('No suitable backend driver found');
        } else
            return [
                'count' => 0
            ];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.
     * Required method for any smart drivers.
     *
     * @param string $method
     *            The name of the called method.
     * @param array $params
     *            Array of passed parameters.
     *            
     * @return bool True if the method can be called with the given parameters,
     *         false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'getProlongRegistrationUrl' || $method == 'getPaymentURL') {
            return true;
        }
        return parent::supportsMethod($method, $params);
    }

    protected function getDetailsFromCurrentSource($source, $details)
    {
        $detailsForCurrentSource = [];
        
        foreach ($details as $detail) {
            $detailSource = $this->getSource($detail);
            
            if ($detailSource === $source) {
                array_push($detailsForCurrentSource, $detail);
            }
        }
        
        return $detailsForCurrentSource;
    }

    public function getItemStatus($id, $bibId, $patronId)
    {
        if ($bibId === null)
            return $this->getEmptyStatuses($ids);
        
        $source = $this->getSource($bibId);
        $driver = $this->getDriver($source);
        
        if ($driver === null)
            throw new ILSException("Driver is undefined!");
        
        $id = $this->stripIdPrefixes($id, $source);
        $bibId = $this->stripIdPrefixes($bibId, $source);
        $patronId = $this->stripIdPrefixes($patronId, $source);
        
        $status = $driver->getItemStatus($id, $bibId, $patronId);
        return $status;
    }
}
