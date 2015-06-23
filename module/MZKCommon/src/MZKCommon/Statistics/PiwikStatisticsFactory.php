<?php
namespace MZKCommon\Statistics;

use Zend\ServiceManager\ServiceManager;
use MZKCommon\Statistics\PiwikStatistics;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for PiwikStatistics
 * 
 * @author   Martin Kravec	<kravec@mzk.cz>
 */
class PiwikStatisticsFactory
{
	/**
	 * Construct the Statistics
	 *
	 * @param ServiceManager $sm Service manager.
	 *
	 * @return MZKCommon\Statistics\PiwikStatistics
	 */
	public static function getPiwikStatistics(ServiceManager $sm)
	{
		$config = $sm->get('VuFind\Config')->get('config');
		$multibackend = $sm->get('VuFind\Config')->get('MultiBackend');
		$drivers = $multibackend->Login->drivers->toArray();

		return new PiwikStatistics($config, $drivers);
	}
}