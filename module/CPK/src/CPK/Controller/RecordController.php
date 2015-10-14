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
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($parentRecordID);
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
    	$recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
    	$recordDriver = $recordLoader->load($parentRecordID);
    	$links = $recordDriver->get866Data();
    	return $links;
    }

    /**
     * Get default tab for a given driver
     *
     * @return string
     */
    protected function getDefaultTab()
    {
        // Load default tab if not already retrieved:
        if (null === $this->defaultTab) {
            // Load record driver tab configuration:
            $driver = $this->loadRecord();
            $this->defaultTab = $this->getDefaultTabForRecord($driver);

            $linksFrom856 = $this->get856Links();
            $field866 = $this->get866Data();
            $noLinksFrom856 = $linksFrom856 === false ? 0 : count($linksFrom856);
            $noLinksFrom866 = $field866 === false ? 0 : count($field866);
            $linksCount = $noLinksFrom856 + $noLinksFrom866;
            if ($linksCount > 0) {
                if (empty($holdings = $this->driver->getRealTimeHoldings())) $this->defaultTab = 'EVersion';
            }

            // Missing/invalid record driver configuration? Fall back to configured
            // default:
            $tabs = $this->getAllTabs();
            if (empty($this->defaultTab) || !isset($tabs[$this->defaultTab])) {
                $this->defaultTab = $this->fallbackDefaultTab;
            }

            // Is configured tab also invalid? If so, pick first existing tab:
            if (empty($this->defaultTab) || !isset($tabs[$this->defaultTab])) {
                $keys = array_keys($tabs);
                $this->defaultTab = isset($keys[0]) ? $keys[0] : '';
            }
        }

        return $this->defaultTab;
    }
}
