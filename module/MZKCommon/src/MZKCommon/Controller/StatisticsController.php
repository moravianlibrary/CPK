<?php
namespace MZKCommon\Controller;

use VuFind\Controller\AbstractBase;
use Zend\View\Model\ViewModel;

/**
 * StatisticsController
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
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
		
		$view = $this->createViewModel(
			array(
				'statistics'  => 'dashboard',
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
		
		if ($isLibrary) {
		
			$dateFrom 	= '2015-01-01';
			$dateTo 	= '2015-12-31';
			$date 		= $dateFrom.','.$dateTo;
			
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
			
			$topSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10);
			$topFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10);
			
			$nbFoundKeywords	 = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date);
			$nbNoResultKeywords  = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date);
			
			//
			$nbSuccessedSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date);
			$nbFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date);
			
			$view = $this->createViewModel(
				array(
					'topSearches'  		 => $topSearches,
					'topFailedSearches'  => $topFailedSearches,
					'nbFoundKeywords'  	 => $nbFoundKeywords,
					'nbNoResultKeywords' => $nbNoResultKeywords,
					'nbSuccessedSearches'=> $nbSuccessedSearches,
					'nbFailedSearches'   => $nbFailedSearches,
					'nbViewedItems'		 => $PiwikStatistics->getNbViewedRecords('range', $date),
					'nbItemViews'		 => $PiwikStatistics->getNbRecordVisits('range', $date),
					'catalogAccessCount' => $PiwikStatistics->getCatalogAccessCount('range', $date),
					'foundKeywordsUrl'   => $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", null, 1),
					'noResultKeywordsUrl'=> $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", null, 1),
				)
			);
			
		} else { // is Admin
			$dateFrom 	= '2015-01-01';
			$dateTo 	= '2015-12-31';
			$date 		= $dateFrom.','.$dateTo;
			
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
			
			$topSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10);
			$topFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10);
			
			$nbFoundKeywords	 = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date);
			$nbNoResultKeywords  = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date);
			
			//
			$nbSuccessedSearches 	   = $PiwikStatistics->getFoundSearchKeywords('range', $date);
			$nbFailedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date);
			
			$view = $this->createViewModel(
				array(
					'topSearches'  		 => $topSearches,
					'topFailedSearches'  => $topFailedSearches,
					'nbFoundKeywords'  	 => $nbFoundKeywords,
					'nbNoResultKeywords' => $nbNoResultKeywords,
					'nbSuccessedSearches'=> $nbSuccessedSearches,
					'nbFailedSearches'   => $nbFailedSearches,
					'nbViewedItems'		 => $PiwikStatistics->getNbViewedRecords('range', $date),
					'nbItemViews'		 => $PiwikStatistics->getNbRecordVisits('range', $date),
					'catalogAccessCount' => $PiwikStatistics->getCatalogAccessCount('range', $date),
					'foundKeywordsUrl'   => $PiwikStatistics->getFoundSearchKeywords('range', $date, "-1", null, 1),
					'noResultKeywordsUrl'=> $PiwikStatistics->getNoResultSearchKeywords('range', $date, "-1", null, 1),
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
		
		if ($isLibrary) {
		
			$view = $this->createViewModel(
				array(
					'statistics'  => 'circulations',
				)
			);
			
		} else { // isAdmin
			$view = $this->createViewModel(
					array(
							'statistics'  => 'circulations',
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
		
		if ($isLibrary) {
		
			$view = $this->createViewModel(
				array(
					'statistics'  => 'payments',
				)
			);
			
		} else { // isAdmin
			$view = $this->createViewModel(
					array(
							'statistics'  => 'payments',
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
		
		if ($isLibrary) {
		
			$dateFrom 	= '2015-01-01';
			$dateTo 	= '2015-12-31';
			$date 		= $dateFrom.','.$dateTo;
			
			$PiwikStatistics = $this->getServiceLocator()
									->get('MZKCommon\StatisticsPiwikStatistics');
			
			// visits in time
			$returningVisitsInTime = $PiwikStatistics->getVisitsCount(
					'month',
					$date,
					'all',
					array('segment' => 'visitorType==returning')
			);
			
			$newVisitsInTime = $PiwikStatistics->getVisitsCount(
					'month',
					$date,
					'all',
					array('segment' => 'visitorType==new')
			);
			
			$visitsInTime = array();
			foreach ($returningVisitsInTime as $key => $value) {
				$visitsInTime[$key] = array('returningVisits' => $value, 'newVisits' => $newVisitsInTime[$key]);
			}
			//
			
			// Total visits
			$totalVisitsArray = $PiwikStatistics->getVisitsCount('range', $date, 'all');
			$totalVisits = $totalVisitsArray['value'];
			
			// New visitors count
			$newVisitorsCount = $PiwikStatistics->getNewVisitorsCount('range', $date);
			
			// Returning visitors count
			$returningVisitorsCount = $PiwikStatistics->getReturningVisitorsCount('range', $date);
			
			// Visits info [Dashboard]
			$visitsInfoArray  = $PiwikStatistics->getVisitsInfo('range', $date, 'all');
			$nbActionPerVisit = $visitsInfoArray['nb_actions_per_visit'];
			$nbActions 		  = $visitsInfoArray['nb_actions'];
			$avgTimeOnSite    = date('i:s', $visitsInfoArray['avg_time_on_site']);
			$totalTimeOnSite  = date('j G:i:s', $visitsInfoArray['sum_visit_length']);
			$nbOnlineUsers	  = $PiwikStatistics->getOnlineUsers();
			
			$view = $this->createViewModel(	
				array(
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
			
		} else { // isAdmin
			$dateFrom 	= '2015-01-01';
			$dateTo 	= '2015-12-31';
			$date 		= $dateFrom.','.$dateTo;
				
			$PiwikStatistics = $this->getServiceLocator()
			->get('MZKCommon\StatisticsPiwikStatistics');
				
			// visits in time
			$returningVisitsInTime = $PiwikStatistics->getVisitsCount(
					'month',
					$date,
					'all',
					array('segment' => 'visitorType==returning')
			);
				
			$newVisitsInTime = $PiwikStatistics->getVisitsCount(
					'month',
					$date,
					'all',
					array('segment' => 'visitorType==new')
			);
				
			$visitsInTime = array();
			foreach ($returningVisitsInTime as $key => $value) {
				$visitsInTime[$key] = array('returningVisits' => $value, 'newVisits' => $newVisitsInTime[$key]);
			}
			//
				
			// Total visits
			$totalVisitsArray = $PiwikStatistics->getVisitsCount('range', $date, 'all');
			$totalVisits = $totalVisitsArray['value'];
				
			// New visitors count
			$newVisitorsCount = $PiwikStatistics->getNewVisitorsCount('range', $date);
				
			// Returning visitors count
			$returningVisitorsCount = $PiwikStatistics->getReturningVisitorsCount('range', $date);
				
			// Visits info [Dashboard]
			$visitsInfoArray  = $PiwikStatistics->getVisitsInfo('range', $date, 'all');
			$nbActionPerVisit = $visitsInfoArray['nb_actions_per_visit'];
			$nbActions 		  = $visitsInfoArray['nb_actions'];
			$avgTimeOnSite    = date('i:s', $visitsInfoArray['avg_time_on_site']);
			$totalTimeOnSite  = date('j G:i:s', $visitsInfoArray['sum_visit_length']);
			$nbOnlineUsers	  = $PiwikStatistics->getOnlineUsers();
				
			$view = $this->createViewModel(
					array(
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
		}
		
		$view->setTemplate('statistics/visits');
		
		return $view;
	}	
}