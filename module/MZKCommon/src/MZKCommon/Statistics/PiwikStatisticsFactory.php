<?php

namespace MZKCommon\Statistics;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for PiwikStatistics
 * @author   Martin Kravec	<kravec@mzk.cz>
 */
class PiwikStatisticsFactory
{
	
	/**
	 * Construct the PiwikStatistics
	 *
	 * @param ServiceManager $sm Service manager.
	 *
	 * @return PiwikStatistics
	 */
	public static function getPiwikStatistics(ServiceManager $sm)
	{
		$config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');

		return new PiwikStatistics($config);
	}
	
}