<?php
/**
 * Record Controller
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
namespace CPK\Controller;

use VuFind\Controller\RecordController as RecordControllerBase;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use VuFind\XSLT\Import\VuFind;

/**
 * RecordController
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class RecordController extends RecordControllerBase
{
    
    /**
     * Returns subfileds of MARC 996 field for specific recordID
     *
     * @param	string	$_POST['record']
     * @param	string	$_POST['field']
     * @param	string	$_POST['subfields'] Comma-separated subfileds
     * 
     * @return	array	$subfieldsValues	space-separated subfields values
     */
	public function getMarc996ArrayViaAjaxAction()
	{
		$recordID = $this->params()->fromPost('recordID');
		$field = $this->params()->fromPost('field');
		$subfieldsArray = explode(",", $this->params()->fromPost('subfields'));
		
		$recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
			
		$recordDriver = $recordLoader->load($recordID);
		$arr = $recordDriver->get996($subfieldsArray);
		
		$result = new JsonModel(array(
			'arr' => $arr,
		));
	
		return $result;
	}
	
	public function getAntikvariatyLinkViaAjaxAction()
	{
		$parentRecordID = $this->params()->fromPost('parentRecordID');

		$recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
			
		$recordDriver = $recordLoader->load($parentRecordID);
		$link = $recordDriver->getAntikvariatyLink();
	
		$result = new JsonModel(array(
			'link' => $link[0],
		));
	
		return $result;
	}
	
	/**
	 * Display a particular tab.
	 *
	 * @param string $tab  Name of tab to display
	 * @param bool   $ajax Are we in AJAX mode?
	 *
	 * @return mixed
	 */
	protected function showTab($tab, $ajax = false)
	{
		// Special case -- handle login request (currently needed for holdings
		// tab when driver-based holds mode is enabled, but may also be useful
		// in other circumstances):
		if ($this->params()->fromQuery('login', 'false') == 'true'
				&& !$this->getUser()
		) {
			return $this->forceLogin(null);
		} else if ($this->params()->fromQuery('catalogLogin', 'false') == 'true'
				&& !is_array($patron = $this->catalogLogin())
		) {
			return $patron;
		}
	
		$view = $this->createViewModel();
		$view->tabs = $this->getAllTabs();
		$view->activeTab = strtolower($tab);
		$view->defaultTab = strtolower($this->getDefaultTab());
		
		$buyChoiceHandler = $this->getServiceLocator()->get('WantIt\BuyChoiceHandler');
		$isbn = $this->driver->getISBNs();
		$lccn = $this->driver->getLCCN();
		$oclc = $this->driver->getCleanOCLCNum();
		
		if (isset($isbn[0])) {
			$gBooks = $buyChoiceHandler->getGoogleBooksItemAsArrayByISBN($isbn[0]);
		} elseif ($lccn) {
			$gBooks = $buyChoiceHandler->getGoogleBooksItemAsArrayByLCCN($lccn);
		} elseif ($oclc) {
			$gBooks = $buyChoiceHandler->getGoogleBooksItemAsArrayByOCLC($oclc);
		} else {
			$gBooks = null;
		}
		
		if ($gBooks) {
			$gBooksLink = $gBooks['items'][0]['volumeInfo']['canonicalVolumeLink'];
			$view->gBooksLink = $gBooksLink;
		}
		
		if (isset($isbn[0])) {
			$zboziLink = $buyChoiceHandler->getZboziLinkByISBN($isbn[0]);
			$view->zboziLink = $zboziLink;
		}
	
		// Set up next/previous record links (if appropriate)
		if ($this->resultScrollerActive()) {
			$driver = $this->loadRecord();
			$view->scrollData = $this->resultScroller()->getScrollData($driver);
		}
		
		$jibOutput = $this->callSfxJib();
		$view->jib = $jibOutput;
	
		$view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');
		return $view;
	}
	
	/**
	 * Downloads SFX JIB content for current record.
	 * @param	string	$institute	Institute shortcut
	 * 
	 * @return	array
	 */
	public function callSfxJib($institute = 'ANY')
	{
		$openUrl = $this->driver->getOpenURL();
		
		$electronicChoiceHandler = new \CPK\WantIt\ElectronicChoiceHandler();
		
		$jibArrayResult = $electronicChoiceHandler->downloadSfxJibResult($openUrl, $institute);
		
		return $jibArrayResult;
	}
}
