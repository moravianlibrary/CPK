<?php
/**
 * Factory for ServiceManager
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\WantIt;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for ServiceManager
 *
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class Factory
{
    /**
     * Construct the BuyChoiceHandler.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BuyChoiceHandler
     */
    public static function getBuyChoiceHandler(ServiceManager $sm)
    {
        return new \CPK\WantIt\BuyChoiceHandler(
        	// $sm->get('CPK\RecordDriver\SolrMarc') @FIXME Encapsulate CPK\RecordDriver\SolrMarc into \CPK\WantIt\BuyChoiceHandler
        );
    }

/**
     * Factory for SolrMarc record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMarc
     */
    public static function getSolrMarc(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarc(
            $sm->get('VuFind\Config')->get('config'),
            null,
            $sm->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\ILSHoldLogic'),
            $sm->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }
}