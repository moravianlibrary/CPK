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

		$view = $this->createViewModel(	
			array(
				'newVisitorsCount' 			=> $newVisitorsCount,
				'returningVisitorsCount' 	=> $returningVisitorsCount,
				'visitsInTime'				=> $visitsInTime,
				'totalVisits'				=> $totalVisits,
			)
		);
		
		$view->setTemplate('statistics/dashboard');
		
		return $view;
	}
	
	public function searchesAction()
	{
		$view = $this->createViewModel(
			array(
				'statistics'  => 'searches',
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
		$view = $this->createViewModel(
			array(
				'statistics'  => 'visits',
			)
		);
		
		$view->setTemplate('statistics/visits');
		
		return $view;
	}
}