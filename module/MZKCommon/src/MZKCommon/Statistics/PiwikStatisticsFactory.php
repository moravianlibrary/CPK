<?php

namespace MZKCommon\Statistics;

use Zend\ServiceManager\ServiceManager;
use MZKCommon\Statistics\PiwikStatistics;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for PiwikStatistics
 * @author   Martin Kravec	<kravec@mzk.cz>
 */
class PiwikStatisticsFactory //implements FactoryInterface
{
	
	/**
	 * Construct the Statistics
	 *
	 * @param ServiceManager $sm Service manager.
	 *
	 * @return PiwikStatistics
	 */
	public static function getPiwikStatistics(ServiceManager $sm)
	{
		$config = $sm->get('VuFind\Config')->get('config');

		return new PiwikStatistics($config);
	}
	
	/**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    /*public function createService(ServiceLocatorInterface $serviceLocator)
    {
    	return new \stdClass();
    }*/
	
}