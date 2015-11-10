<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Martin Kravec <Martin.Kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

use MZKCommon\Controller\RecordController as RecordControllerBase, VuFind\Controller\HoldsTrait as HoldsTraitBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class RecordController extends RecordControllerBase
{

    use HoldsTrait, HoldsTraitBase {
        HoldsTrait::holdAction insteadof HoldsTraitBase;
    }

    protected $recordLoader = null;

    /**
     * Display a particular tab.
     *
     * @param string $tab
     *            Name of tab to display
     * @param bool $ajax
     *            Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        if ($this->params()->fromQuery('getXml') 
            && ($this->params()->fromQuery('getXml') === true)
        ) {
            return getXml();
        }
        
        // Special case -- handle login request (currently needed for holdings
        // tab when driver-based holds mode is enabled, but may also be useful
        // in other circumstances):
        if ($this->params()->fromQuery('login', 'false') == 'true' &&
             ! $this->getUser()) {
            return $this->forceLogin(null);
        } else
            if ($this->params()->fromQuery('catalogLogin', 'false') == 'true' &&
                 ! is_array($patron = $this->catalogLogin())) {
                return $patron;
            }

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        // get 856links
        $linksFrom856 = $this->get856Links();
        if ($linksFrom856 !== false)

            $view->linksFrom856 = $linksFrom856;


        // get number of links
        $field866 = $this->get866Data();
        $noLinksFrom856 = $linksFrom856 === false ? 0 : count($linksFrom856);
        $noLinksFrom866 = $field866 === false ? 0 : count($field866);
        $view->eVersionLinksCount = $noLinksFrom856 + $noLinksFrom866;

        $fieldsOf7xx = explode(",", $this->getConfig()->Record->fields_in_core);
        $subfieldsOf733 = [
            't',
            'd',
            'x',
            'g',
            'q',
            '9',
            'z'
        ];
        foreach ($fieldsOf7xx as $field) {
            $field7xx = $this->driver->get7xxField($field, $subfieldsOf733);
            if ($field7xx !== false) {
                $varName = 'field' . $field;
                $view->$varName = $field7xx;
            }
        }
        
        // getCitation
        $citationId = $this->getCitationId();
        if ($citationId !== false)
            $view->citationId = $citationId;

        //
        $view->config = $this->getConfig();

        $view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');
        return $view;
    }

    /**
     * Returns links from SOLR indexed from 856
     *
     * @return  string
     */
    protected function get856Links()
    {
        $parentRecordID = $this->driver->getParentRecordID();

        if ($this->recordLoader === null)
            $this->recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');

        $recordDriver = $this->recordLoader->load($parentRecordID);
        $links = $recordDriver->get856Links();
        return $links;
    }


    /**
     * Returns data from SOLR representing links and metadata to access SFX
     *
     * @return  array
     */
    protected function get866Data()
    {
    	$parentRecordID = $this->driver->getParentRecordID();

    	if ($this->recordLoader === null)
    	    $this->recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');

    	$recordDriver = $this->recordLoader->load($parentRecordID);
    	$links = $recordDriver->get866Data();
    	return $links;
    }

    /**
     * Support method to load tab information from the RecordTabPluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        parent::loadTabDetails();

        if (empty($this->driver->getRealTimeHoldings())) {

            // If there is no real holding to display, than show EVersion tab if
            // there is something ..
            if (count($this->get856Links()) || count($this->get866Data()))
                $this->defaultTab = 'EVersion';
        }
    }
    
    public function getCitationId()
    {
        $recordID = $this->getUniqueID();
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);
    
        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);
    
        $citationServerUrl = "http://www.citacepro.com/api/cpk/citace/"
                             .$parentRecordID;
    
        $soap = curl_init();
        curl_setopt($soap, CURLOPT_URL, $citationServerUrl);
        curl_setopt($soap, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($soap, CURLOPT_TIMEOUT, 10);
        curl_setopt($soap, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap, CURLOPT_SSL_VERIFYHOST, false);
    
        $citation = curl_exec($soap);
        curl_close($soap);
    
        if ($citation === false)
            return false;
    
        return $parentRecordID;
    }
    
    protected function getXml()
    {
        $recordID = $this->getUniqueID();
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);
        
        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);
        
        $format = $parentRecordDriver->getRecordType();
        if ($format === 'marc')
            $format .= '21';
        $xml = $parentRecordDriver->getXml($format);
        
        $this->_helper->layout->disableLayout();
        $content = "<?xml version='1.0'?>".$xml;
        $this->getResponse()->clearHeaders();
        $this->getResponse()->setheader('Content-Type', 'text/xml');
        $this->getResponse()->setBody($content);
        $this->getResponse()->sendResponse();
    }
}
