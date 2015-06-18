<?php
/**
 * PiwikStatistics Controller
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
namespace MZKCommon\Controller;

use VuFind\Controller\AbstractBase;
use Zend\View\Model\ViewModel;

/**
 * StatisticsController
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class StatisticsController extends AbstractBase
{
	public function defaultAction()
	{
		return $this->dashboardAction();
	}
	
	public function dashboardAction()
	{
		// Log In Start 
		if (! is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		$user 		= $this->getUser();
		$isLibrary  = false;
		$isAdmin 	= false;
		
		$config = $this->getServiceLocator()->get('config');
		$adminLibCard = isset($config->PiwikStatistics->major_adm) ? $config->PiwikStatistics->major_adm : 'CPK';
		
		if (empty($user['major']))
			return $this->redirect()->toRoute('myresearch-home');
		
		if ($user['major'] === $adminLibCard) {
			$isAdmin = true;
		} else {
			$isLibrary = true;
		}
		// Log in End
		
		// Get params inspection Start (preventing user Injection)
		$urlGetParams = $this->params()->fromQuery();
		$this->checkGetParams($urlGetParams);
		// Get params inspections End
		
		// All cards
		$cards = array ('blah', 'MZKLIB1', 'KOHALIB1');
		
		$view = $this->createViewModel(
			array(
				'statistics'   => 'dashboard',
				'urlGetParams' => $urlGetParams,
				'isAdmin'	   => $isAdmin,
				'cards'		   => $cards,
			)
		);
		
		$view->setTemplate('statistics/dashboard');
		
		return $view;
	}
	
	public function searchesAction()
	{
		// Log In Start 
		if (! is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		$user 		= $this->getUser();
		$isLibrary  = false;
		$isAdmin 	= false;
		
		$config = $this->getServiceLocator()->get('config');
		$adminLibCard = isset($config->PiwikStatistics->major_adm) ? $config->PiwikStatistics->major_adm : 'CPK';
		
		if (empty($user['major']))
			return $this->redirect()->toRoute('myresearch-home');
		
		if ($user['major'] === $adminLibCard) {
			$isAdmin = true;
		} else {
			$isLibrary = true;
		}
		// Log in End
		
		// Get params inspection Start (preventing user Injection)
		$urlGetParams = $this->params()->fromQuery();
		$this->checkGetParams($urlGetParams);
		// Get params inspections End
		
		$dateFrom = (! empty($urlGetParams['dateFrom'])) ? $urlGetParams['dateFrom'] : date('Y-m-01');
		$dateTo   = (! empty($urlGetParams['dateTo']))   ? $urlGetParams['dateTo'] : date('Y-m-t');
		$date 	  = $dateFrom.','.$dateTo;
		
		if ($isLibrary) {
			
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
			
			$topSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10, $adminLibCard);
			$topFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10, $adminLibCard);
			
			$nbFoundKeywords	 = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date, $adminLibCard);
			$nbNoResultKeywords  = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date, $adminLibCard);
			
			//
			$nbSuccessedSearches = $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", $adminLibCard);
			$nbFailedSearches    = $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", $adminLibCard);
			
			$view = $this->createViewModel(
				array(
					'urlGetParams' => $urlGetParams,
					'topSearches'  		 => $topSearches,
					'topFailedSearches'  => $topFailedSearches,
					'nbFoundKeywords'  	 => $nbFoundKeywords,
					'nbNoResultKeywords' => $nbNoResultKeywords,
					'nbSuccessedSearches'=> $nbSuccessedSearches,
					'nbFailedSearches'   => $nbFailedSearches,
					'nbViewedItems'		 => $PiwikStatistics->getNbViewedRecordsForLibrary('range', $date, $adminLibCard),
					'nbItemViews'		 => $PiwikStatistics->getNbRecordVisitsForLibrary('range', $date, $adminLibCard),
					'catalogAccessCount' => $PiwikStatistics->getCatalogAccessCountForLibrary('range', $date, $adminLibCard),
					'foundKeywordsUrl'   => $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", $adminLibCard, 1),
					'noResultKeywordsUrl'=> $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", $adminLibCard, 1),
				)
			);
			
		} else { // is Admin
			
			$libCard = (! empty($urlGetParams['libCard'])) ? $urlGetParams['libCard'] : 'all';
			
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
			
			if (($libCard) && ($libCard !== 'all')) { // chosen lib
				
				$topSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10, $libCard);
				$topFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10, $libCard);
					
				$nbFoundKeywords	 = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date, $libCard);
				$nbNoResultKeywords  = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date, $libCard);
					
				//
				$nbSuccessedSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, -1, $libCard);
				$nbFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, -1, $libCard);
					
				//
				$nbViewedItems = $PiwikStatistics->getNbViewedRecordsForLibrary('range', $date, $libCard);
				$nbItemViews = $PiwikStatistics->getNbRecordVisitsForLibrary('range', $date, $libCard);
				$catalogAccessCount = $PiwikStatistics->getCatalogAccessCountForLibrary('range', $date, $libCard);
				$foundKeywordsUrl = $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", $libCard, 1);
				$noResultKeywordsUrl = $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", $libCard, 1);
				
			} else { // all libs
			
				$topSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10);
				$topFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10);
				
				$nbFoundKeywords	 = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date);
				$nbNoResultKeywords  = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date);
				
				//
				$nbSuccessedSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date);
				$nbFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date);
				
				//
				$nbViewedItems = $PiwikStatistics->getNbViewedRecords('range', $date);
				$nbItemViews = $PiwikStatistics->getNbRecordVisits('range', $date);
				$catalogAccessCount = $PiwikStatistics->getCatalogAccessCount('range', $date);
				$foundKeywordsUrl = $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", null, 1);
				$noResultKeywordsUrl = $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", null, 1);
				
			}				
			
			// All cards
			$cards = array ('blah', 'MZKLIB1', 'KOHALIB1');
			
			$view = $this->createViewModel(
				array(
					'urlGetParams' 		 => $urlGetParams,
					'topSearches'  		 => $topSearches,
					'topFailedSearches'  => $topFailedSearches,
					'nbFoundKeywords'  	 => $nbFoundKeywords,
					'nbNoResultKeywords' => $nbNoResultKeywords,
					'nbSuccessedSearches'=> $nbSuccessedSearches,
					'nbFailedSearches'   => $nbFailedSearches,
					'nbViewedItems'		 => $nbViewedItems,
					'nbItemViews'		 => $nbItemViews,
					'catalogAccessCount' => $catalogAccessCount,
					'foundKeywordsUrl'   => $foundKeywordsUrl,
					'noResultKeywordsUrl'=> $noResultKeywordsUrl,
					'isAdmin'			 => $isAdmin,
					'cards'		  		 => $cards,
				)
			);
		}
		
		$view->setTemplate('statistics/searches');
		
		return $view;
	}
	
	public function circulationsAction()
	{
		// Log In Start 
		if (! is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		$user 		= $this->getUser();
		$isLibrary  = false;
		$isAdmin 	= false;
		
		$config = $this->getServiceLocator()->get('config');
		$adminLibCard = isset($config->PiwikStatistics->major_adm) ? $config->PiwikStatistics->major_adm : 'CPK';
		
		if (empty($user['major']))
			return $this->redirect()->toRoute('myresearch-home');
		
		if ($user['major'] === $adminLibCard) {
			$isAdmin = true;
		} else {
			$isLibrary = true;
		}
		// Log in End
		
		// Get params inspection Start (preventing user Injection)
		$urlGetParams = $this->params()->fromQuery();
		$this->checkGetParams($urlGetParams);
		// Get params inspections End
		
		$dateFrom = (! empty($urlGetParams['dateFrom'])) ? $urlGetParams['dateFrom'] : date('Y-m-01');
		$dateTo   = (! empty($urlGetParams['dateTo']))   ? $urlGetParams['dateTo'] : date('Y-m-t');
		$date 	  = $dateFrom.','.$dateTo;
		
		// All cards
		$cards = array ('blah', 'MZKLIB1', 'KOHALIB1');
		
		if ($isLibrary) {
		
			$view = $this->createViewModel(
				array(
					'urlGetParams' => $urlGetParams,
					'statistics'  => 'circulations',
				)
			);
			
		} else { // isAdmin
			$view = $this->createViewModel(
					array(
							'urlGetParams' => $urlGetParams,
							'statistics'  => 'circulations',
							'isAdmin'	  => $isAdmin,
							'cards'		  => $cards,
					)
			);
		}
		
		$view->setTemplate('statistics/circulations');
		
		return $view;
	}
	
	public function paymentsAction()
	{
		// Log In Start 
		if (! is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		$user 		= $this->getUser();
		$isLibrary  = false;
		$isAdmin 	= false;
		
		$config = $this->getServiceLocator()->get('config');
		$adminLibCard = isset($config->PiwikStatistics->major_adm) ? $config->PiwikStatistics->major_adm : 'CPK';
		
		if (empty($user['major']))
			return $this->redirect()->toRoute('myresearch-home');
		
		if ($user['major'] === $adminLibCard) {
			$isAdmin = true;
		} else {
			$isLibrary = true;
		}
		// Log in End
		
		$dateFrom = (! empty($urlGetParams['dateFrom'])) ? $urlGetParams['dateFrom'] : date('Y-m-01');
		$dateTo   = (! empty($urlGetParams['dateTo']))   ? $urlGetParams['dateTo'] : date('Y-m-t');
		$date 	  = $dateFrom.','.$dateTo;
		
		// Get params inspection Start (preventing user Injection)
		$urlGetParams = $this->params()->fromQuery();
		$this->checkGetParams($urlGetParams);
		// Get params inspections End
		
		// All cards
		$cards = array ('blah', 'MZKLIB1', 'KOHALIB1');
		
		if ($isLibrary) {
		
			$view = $this->createViewModel(
				array(
					'urlGetParams' => $urlGetParams,
					'statistics'  => 'payments',
				)
			);
			
		} else { // isAdmin
			$view = $this->createViewModel(
					array(
							'urlGetParams' => $urlGetParams,
							'statistics'  => 'payments',
							'isAdmin'	  => $isAdmin,
							'cards'		  => $cards,
					)
			);
		}
		
		$view->setTemplate('statistics/payments');
		
		return $view;
	}
	
	public function visitsAction()
	{
		// Log In Start 
		if (! is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		$user 		= $this->getUser();
		$isLibrary  = false;
		$isAdmin 	= false;
		
		$config = $this->getServiceLocator()->get('config');
		$adminLibCard = isset($config->PiwikStatistics->major_adm) ? $config->PiwikStatistics->major_adm : 'CPK';
		
		if (empty($user['major']))
			return $this->redirect()->toRoute('myresearch-home');
		
		if ($user['major'] === $adminLibCard) {
			$isAdmin = true;
		} else {
			$isLibrary = true;
		}
		// Log in End
		
		// Get params inspection Start (preventing user Injection)
		$urlGetParams = $this->params()->fromQuery();
		$this->checkGetParams($urlGetParams);
		// Get params inspections End
		
		$dateFrom = (! empty($urlGetParams['dateFrom'])) ? $urlGetParams['dateFrom'] : date('Y-m-01');
		$dateTo   = (! empty($urlGetParams['dateTo']))   ? $urlGetParams['dateTo'] : date('Y-m-t');
		$date 	  = $dateFrom.','.$dateTo;
		
		if ($isLibrary) {
			
			$PiwikStatistics = $this->getServiceLocator()
									->get('MZKCommon\StatisticsPiwikStatistics');
			
			// Periodicity
			$ts1 = strtotime($dateFrom);
			$ts2 = strtotime($dateTo);
			
			$year1 = date('Y', $ts1);
			$year2 = date('Y', $ts2);
			
			$month1 = date('m', $ts1);
			$month2 = date('m', $ts2);
			
			$monthsBetweenDates = (($year2 - $year1) * 12) + ($month2 - $month1);
			
			if ($monthsBetweenDates == 0) {
				$periodicity = 'day';
			} else if ($monthsBetweenDates <= 2) {
				$periodicity = 'week';
			} else if ($monthsBetweenDates <= 12) {
				$periodicity = 'month';
			} else {  // 13+ months
				$periodicity = 'year';
			}
			//
			
			// visits in time
			$returningVisitsInTime = $PiwikStatistics->getVisitsCountForLibrary(
					$periodicity,
					$date,
					$adminLibCard,
					array('segment' => 'visitorType==returning')
			);
			
			$newVisitsInTime = $PiwikStatistics->getVisitsCountForLibrary(
					$periodicity,
					$date,
					$adminLibCard,
					array('segment' => 'visitorType==new')
			);
			
			$visitsInTime = array();
			foreach ($returningVisitsInTime as $key => $value) {
				$visitsInTime[$key] = array('returningVisits' => $value, 'newVisits' => $newVisitsInTime[$key]);
			}
			//
			
			// Total visits
			$totalVisitsArray = $PiwikStatistics->getVisitsCountForLibrary('range', $date, $adminLibCard);
			$totalVisits = $totalVisitsArray['value'];
			
			// New visitors count
			$newVisitorsCount = $PiwikStatistics->getNewVisitorsCountForLibrary('range', $date, $adminLibCard);
			
			// Returning visitors count
			$returningVisitorsCount = $PiwikStatistics->getReturningVisitorsCountForLibrary('range', $date, $adminLibCard);
			
			// Visits info [Dashboard]
			$visitsInfoArray  = $PiwikStatistics->getVisitsInfoForLibrary('range', $date, $adminLibCard);
			$nbActionPerVisit = $visitsInfoArray['nb_actions_per_visit'];
			$nbActions 		  = $visitsInfoArray['nb_actions'];
			$avgTimeOnSite    = date('i:s', $visitsInfoArray['avg_time_on_site']);
			$totalTimeOnSite  = date('j G:i:s', $visitsInfoArray['sum_visit_length']);
			$nbOnlineUsers	  = $PiwikStatistics->getOnlineUsers(10, $adminLibCard);
			
			$view = $this->createViewModel(	
				array(
					'urlGetParams'				=> $urlGetParams,
					'newVisitorsCount' 			=> $newVisitorsCount,
					'returningVisitorsCount' 	=> $returningVisitorsCount,
					'visitsInTime'				=> $visitsInTime,
					'totalVisits'				=> $totalVisits,
					'nbActionPerVisit'			=> $nbActionPerVisit,
					'nbActions'					=> $nbActions,
					'avgTimeOnSite'				=> $avgTimeOnSite,
					'totalTimeOnSite'			=> $totalTimeOnSite,
					'nbOnlineUsers'				=> $nbOnlineUsers,
				)
			);
			
			$view->setTemplate('statistics/visits');
			
		} else { // isAdmin
				
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
				
			// Periodicity
			$ts1 = strtotime($dateFrom);
			$ts2 = strtotime($dateTo);
				
			$year1 = date('Y', $ts1);
			$year2 = date('Y', $ts2);
				
			$month1 = date('m', $ts1);
			$month2 = date('m', $ts2);
				
			$monthsBetweenDates = (($year2 - $year1) * 12) + ($month2 - $month1);
				
			if ($monthsBetweenDates == 0) {
				$periodicity = 'day';
			} else if ($monthsBetweenDates <= 2) {
				$periodicity = 'week';
			} else if ($monthsBetweenDates <= 12) {
				$periodicity = 'month';
			} else {  // 13+ months
				$periodicity = 'year';
			}
			//
			
			$libCard = (! empty($urlGetParams['libCard'])) ? $urlGetParams['libCard'] : 'all';
			
			// visits in time
			if (($libCard) && ($libCard !== 'all')) {
				$returningVisitsInTime = $PiwikStatistics->getVisitsCountForLibrary(
						$periodicity,
						$date,
						$libCard,
						array('segment' => 'visitorType==returning')
				);
					
				$newVisitsInTime = $PiwikStatistics->getVisitsCountForLibrary(
						$periodicity,
						$date,
						$libCard,
						array('segment' => 'visitorType==new')
				);
			} else { // all libs
				$returningVisitsInTime = $PiwikStatistics->getVisitsCount(
						$periodicity,
						$date,
						'all',
						array('segment' => 'visitorType==returning')
				);
				
				$newVisitsInTime = $PiwikStatistics->getVisitsCount(
						$periodicity,
						$date,
						'all',
						array('segment' => 'visitorType==new')
				);
			}
			
			$visitsInTime = array();
			foreach ($returningVisitsInTime as $key => $value) {
				$visitsInTime[$key] = array('returningVisits' => $value, 'newVisits' => $newVisitsInTime[$key]);
			}
			//
				
			// Total visits
			if (($libCard) && ($libCard !== 'all')) {
				$totalVisitsArray = $PiwikStatistics->getVisitsCountForLibrary('range', $date, $libCard);
			} else {
				$totalVisitsArray = $PiwikStatistics->getVisitsCount('range', $date, 'all');
			}
			$totalVisits = $totalVisitsArray['value'];
				
			// New and Returning visitors count
			if (($libCard) && ($libCard !== 'all')) { // chosen library
				
				$newVisitorsCount = $PiwikStatistics->getNewVisitorsCountForLibrary('range', $date, $libCard);
				
				$newAnonymeVisitorsCount = null;
				$newAuthenticatedVisitorsCount = null;
				
				$returningVisitorsCount = $PiwikStatistics->getReturningVisitorsCountForLibrary('range', $date, $libCard);
				
				$returningAnonymeVisitorsCount = null;
				$returningAuthenticatedVisitorsCount = null;
				
			} else { // all libs

				$newVisitorsCount = $PiwikStatistics->getNewVisitorsCount('range', $date);
				$newAnonymeVisitorsCount = $PiwikStatistics->getNewVisitorsCount('range', $date, "anonyme");
				$newAuthenticatedVisitorsCount = $PiwikStatistics->getNewVisitorsCount('range', $date, "authenticated");
					
				$returningVisitorsCount = $PiwikStatistics->getReturningVisitorsCount('range', $date);
				$returningAnonymeVisitorsCount = $PiwikStatistics->getReturningVisitorsCount('range', $date, "anonyme");
				$returningAuthenticatedVisitorsCount = $PiwikStatistics->getReturningVisitorsCount('range', $date, "authenticated");
				
			}
			
			// Visits info [Dashboard]
			if (($libCard) && ($libCard !== 'all')) { // chosen lib
			
				$visitsInfoArray  = $PiwikStatistics->getVisitsInfoForLibrary('range', $date, $libCard);
				$nbActionPerVisit = $visitsInfoArray['nb_actions_per_visit'];
				$nbActions 		  = $visitsInfoArray['nb_actions'];
				$avgTimeOnSite    = date('i:s', $visitsInfoArray['avg_time_on_site']);
				$totalTimeOnSite  = date('j G:i:s', $visitsInfoArray['sum_visit_length']);
				$nbOnlineUsers	  = $PiwikStatistics->getOnlineUsers(10, $libCard);
				
			} else { // all libs
				$visitsInfoArray  = $PiwikStatistics->getVisitsInfo('range', $date);
				$nbActionPerVisit = $visitsInfoArray['nb_actions_per_visit'];
				$nbActions 		  = $visitsInfoArray['nb_actions'];
				$avgTimeOnSite    = date('i:s', $visitsInfoArray['avg_time_on_site']);
				$totalTimeOnSite  = date('j G:i:s', $visitsInfoArray['sum_visit_length']);
				$nbOnlineUsers	  = $PiwikStatistics->getOnlineUsers(10);
			}
			
			// All cards
			$cards = array ('blah', 'MZKLIB1', 'KOHALIB1');
				
			$view = $this->createViewModel(
				array(
					'urlGetParams' 						  => $urlGetParams,
					'newVisitorsCount' 					  => $newVisitorsCount,
					'returningVisitorsCount' 			  => $returningVisitorsCount,
					'visitsInTime'						  => $visitsInTime,
					'totalVisits'						  => $totalVisits,
					'nbActionPerVisit'					  => $nbActionPerVisit,
					'nbActions'							  => $nbActions,
					'avgTimeOnSite'						  => $avgTimeOnSite,
					'totalTimeOnSite'					  => $totalTimeOnSite,
					'nbOnlineUsers'						  => $nbOnlineUsers,
					'returningAnonymeVisitorsCount' 	  => $returningAnonymeVisitorsCount,
					'returningAuthenticatedVisitorsCount' => $returningAuthenticatedVisitorsCount,
					'newAnonymeVisitorsCount' 			  => $newAnonymeVisitorsCount,
					'newAuthenticatedVisitorsCount' 	  => $newAuthenticatedVisitorsCount,
					'isAdmin'							  => $isAdmin,
					'cards'								  => $cards,
					)
			);
			
			$view->setTemplate('statistics/visits-admin');
		}

		return $view;
	}	
	
	/**
	 * Chcecks params for SQL Injection
	 * 
	 * @param	array	$urlGetParams
	 * @throws	\Exception	if one of the parametrs is invalid
	 * @return	voic
	 */
	private function checkGetParams(array &$urlGetParams)
	{
		if (isset($urlGetParams['period']))
			$urlGetParams['period'] = htmlspecialchars($urlGetParams['period']);
		
		$datePattern = "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/";
		
		if (isset($urlGetParams['dateFrom']))
			if (! preg_match($datePattern, $urlGetParams['dateFrom']))
				throw new \Exception('Invalid get parameter dateFrom');
		
		if (isset($urlGetParams['dateTo']))
			if (! preg_match($datePattern, $urlGetParams['dateTo']))
				throw new \Exception('Invalid get parameter dateTo');
	}
	
}