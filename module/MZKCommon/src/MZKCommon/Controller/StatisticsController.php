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
	public function dashboardAction()
	{
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
		$dateFrom 	= '2015-01-01';
		$dateTo 	= '2015-12-31';
		$date 		= $dateFrom.','.$dateTo;
		
		$PiwikStatistics = $this->getServiceLocator()
		->get('MZKCommon\StatisticsPiwikStatistics');
		
		$searches = $PiwikStatistics->getFoundSearchKeywords('range', $date, 10);
		$failedSearches = $PiwikStatistics->getNoResultSearchKeywords('range', $date, 10);
		
		$nbSearches = $PiwikStatistics->getFoundSearchKeywordsCount('range', $date);
		$nbFailedSearches = $PiwikStatistics->getNoResultSearchKeywordsCount('range', $date);
		
		$view = $this->createViewModel(
			array(
				'searches'  		=> $searches,
				'failedSearches'  	=> $failedSearches,
				'nbSearches'  		=> $nbSearches,
				'nbFailedSearches'  => $nbFailedSearches,
				'nbViewedItems'		=> $PiwikStatistics->getViewedRecordsCount('range', $date),
				'nbItemViews'		=> 999,
			)
		);
		
		$view->setTemplate('statistics/searches');
		
		return $view;
	}
	
	public function circulationsAction()
	{
		$view = $this->createViewModel(
			array(
				'statistics'  => 'circulations',
			)
		);
		
		$view->setTemplate('statistics/circulations');
		
		return $view;
	}
	
	public function paymentsAction()
	{
		$view = $this->createViewModel(
			array(
				'statistics'  => 'payments',
			)
		);
		
		$view->setTemplate('statistics/payments');
		
		return $view;
	}
	
	public function visitsAction()
	{
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
		$newVisitorsCountArray = $PiwikStatistics->getNewVisitorsCount('month', $date);
		$newVisitorsCount = array_sum($newVisitorsCountArray);
		
		// Returning visitors count
		$returningVisitorsCountArray = $PiwikStatistics->getReturningVisitorsCount('month', $date);
		$returningVisitorsCount = array_sum($returningVisitorsCountArray);
		
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
		
		$view->setTemplate('statistics/visits');
		
		return $view;
	}
}