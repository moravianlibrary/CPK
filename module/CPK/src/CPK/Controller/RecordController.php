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
		
		// WantIt electronic choice
		$jibOutput = $this->callSfxJib();
		$view->jib = $jibOutput;
		
		// Set up next/previous record links (if appropriate)
		if ($this->resultScrollerActive()) {
			$driver = $this->loadRecord();
			$view->scrollData = $this->resultScroller()->getScrollData($driver);
		}
	
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
		$wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
		$electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject($this->driver);
		
		$jibArrayResult = $electronicChoiceHandler->downloadSfxJibResult($institute);
		
		return $jibArrayResult;
	}
}
