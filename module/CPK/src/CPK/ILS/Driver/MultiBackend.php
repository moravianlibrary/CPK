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

use VuFind\Exception\ILS as ILSException, Zend\ServiceManager\ServiceLocatorAwareInterface, Zend\ServiceManager\ServiceLocatorInterface, VuFind\ILS\Driver\MultiBackend as MultiBackendBase;

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
    public function getStatuses($ids)
    {
        // We assume all the ids passed here are being processed by only one ILS/Driver
        $source = $this->getSource(reset($ids));
        $driver = $this->getDriver($source);

        if ($driver === null)
            return $this->getEmptyStatuses($ids);

        if ($driver instanceof XCNCIP2 || $driver instanceof Aleph) {
            foreach ($ids as &$id) {
                $id = $this->stripIdPrefixes($id, $source);
            }

            $statuses = $driver->getStatuses($ids);
            return $this->addIdPrefixes($statuses, $source);
        } else
            return parent::getStatuses($ids);
    }

    protected function getEmptyStatuses($ids)
    {
        $emptyStatuses = [];

        foreach ($ids as $id)
            $emptyStatuses[]['id'] = $id;

        return $emptyStatuses;

    }

    public function getProlongRegistrationUrl($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if (!$driver || !$this->methodSupported($driver, 'getProlongRegistrationUrl', compact('patron'))) {
            return null;
        }
        $patron = $this->stripIdPrefixes($patron, $source);
        return $driver->getProlongRegistrationUrl($patron);
    }

    public function getPaymentURL($patron, $fine)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if (!$driver || !$this->methodSupported($driver, 'getPaymentURL', compact('patron', 'fine'))) {
            return null;
        }
        $patron = $this->stripIdPrefixes($patron, $source);
        return $driver->getPaymentURL($patron, $fine);
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters.
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'getProlongRegistrationUrl' || $method == 'getPaymentURL') {
            return true;
        }
        return parent::supportsMethod($method, $params);
    }
}
