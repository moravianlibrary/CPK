<?php
/**
 * Record Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

use MZKCommon\Controller\RecordController as RecordControllerBase, 
VuFind\Controller\HoldsTrait as HoldsTraitBase;
use Zend\Mail\Address;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
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
        if ($this->params()->fromQuery('getXml')) {
            return $this->getXml();
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
        
        $user = $this->getAuthManager()->isLoggedIn();
        
        $view->isLoggedIn = $user;
        $view->offlineFavoritesEnabled = false;
        
        if ($this->getConfig()->Site['offlineFavoritesEnabled'] !== null) {
            $view->offlineFavoritesEnabled = (bool) $this->getConfig()->Site['offlineFavoritesEnabled'];
        }
        
        /* Citation style fieldset */
        $citationStyleTable = $this->getTable('citationstyle');
        $availableCitationStyles = $citationStyleTable->getAllStyles();
        
        $defaultCitationStyleValue = $this->getConfig()->Record->default_citation_style;
        
        foreach ($availableCitationStyles as $style) {
            if ($style['value'] === $defaultCitationStyleValue) {
                $defaultCitationStyle = $style['value'];
                break;
            }
        }
        
        $userSettingsTable = $this->getTable("usersettings");
        
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $preferedCitationStyle = $userSettingsTable->getUserCitationStyle($user);
        }
        
        $selectedCitationStyle = (! empty($preferedCitationStyle))
        ? $preferedCitationStyle
        : $defaultCitationStyle;
        
        $view->selectedCitationStyle = $selectedCitationStyle;

        $view->availableCitationStyles = $availableCitationStyles;
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
            $this->recordLoader = $this->getServiceLocator()
                ->get('VuFind\RecordLoader');

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
    	    $this->recordLoader = $this->getServiceLocator()
    	       ->get('VuFind\RecordLoader');

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

    protected function getXml()
    {
        $recordID = $this->driver->getUniqueID();
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);
        
        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);
        
        $format = $parentRecordDriver->getRecordType();
        if ($format === 'marc')
            $format .= '21';
        $recordXml = $parentRecordDriver->getXml($format);
        
        session_regenerate_id();
        $sessionId = session_id();
        
        $hasControlfield002 = strpos($recordXml, 'controlfield tag="002"');
        if ($hasControlfield002 === false) { // there is no controlfield 002
            $afterTag = '</leader>';
            $pos = strpos($recordXml, $afterTag);
            $format = $parentRecordDriver->getCitationRecordType();
            $newElement = "\n  <controlfield tag=\"002\">"
                .$format
                ."</controlfield>";
            $recordXml = substr_replace(
                $recordXml,
                $newElement,
                $pos+strlen($afterTag),
                0
            );
        }
        
        $xml = '<?xml version = "1.0" encoding = "UTF-8"?>
<publish-avail>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ 
 http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
<ListRecords>
<record>
<header>
<identifier>cpk-record-id:'.$recordID.'</identifier>
</header>
<metadata>'.$recordXml.'</metadata>
</record>
</ListRecords>
</OAI-PMH>
<session-id>'.$sessionId.'</session-id>
</publish-avail>';
        
        $response = new \Zend\Http\Response();
        $response->getHeaders()->addHeaderLine(
            'Content-Type',
            'text/xml; charset=utf-8'
        );
        $response->setContent($xml);
        return $response;
    }
    
    /**
     * Email action - Allows the email form to appear.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function emailAction()
    {
        // Force login if necessary:
        $config = $this->getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
            && !$this->getUser()
        ) {
            return $this->forceLogin();
        }
    
        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Create view
        $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
        $view = $this->createEmailViewModel(
            null, $mailer->getDefaultRecordSubject($driver)
            );
        $mailer->setMaxRecipients($view->maxRecipients);

        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('email');
        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $subject = $driver->getTitle();
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                ? $view->from : null;
                $sender = new \Zend\Mail\Address(
                    $view->from, 
                    $this->translate('Central Library Portal')
                );
                $mailer->sendRecord(
                    $view->to, 
                    $sender, 
                    $view->message, 
                    $driver,
                    $this->getViewRenderer(),
                    $subject,
                    $cc
                );
                $this->flashMessenger()->addMessage('email_success', 'success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }
        return $this->redirectToRecord();
    }
}
