<?php
/**
 * Record Driver Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VNF\RecordDriver;
use Zend\ServiceManager\ServiceManager;

/**
 * Record Driver Factory Class
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{

    /**
     * Factory for Mkp (Municipal Library of Prague) record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMkp
     */
    public function getSolrMkp(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrMkp(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }

    /**
     * Factory for default record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMkp
     */
    public function getSolrMarc(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrMarc(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }

    /**
     * Factory for default record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMkp
     */
    public function getSolrMarcMerged(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrMarcMerged(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        return $driver;
    }
    
    /**
     * Factory for Supraphon record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrSup
     */    
    public function getSolrSup(ServiceManager $sm)
    {
    	$driver = new \VNF\RecordDriver\SolrSup(
    			$sm->getServiceLocator()->get('VuFind\Config')->get('config'),
    			null,
    			$sm->getServiceLocator()->get('VuFind\Config')->get('searches')
    	);
    	$driver->attachILS(
    			$sm->getServiceLocator()->get('VuFind\ILSConnection'),
    			$sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
    			$sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
    	);
    	return $driver;
    }

    /**
     * Factory for Kkfb record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrKkfb
     */
    public function getSolrKkfb(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrKkfb(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }
    
    /**
     * Factory for Ktn record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrKtn
     */
    public function getSolrKtn(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrKtn(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }
    
    /**
     * Factory for KJM record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrKjm
     */
    public function getSolrKjm(ServiceManager $sm)
    {
        $driver = new \VNF\RecordDriver\SolrKjm(
                $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                null,
                $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
                $sm->getServiceLocator()->get('VuFind\ILSConnection'),
                $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
                $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        return $driver;
    }

}
