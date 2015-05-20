<?php

namespace MZKCommon\Statistics;

use Piwik\API\Request;
use MZKCommon\Statistics\PiwikStatisticsInterface;

/**
 * PiwikStatistics Model
 * @author Martin Kravec <kravec@mzk.cz>
 */
class PiwikStatistics implements PiwikStatisticsInterface
{
	protected $siteId;
	
	/**
	 * @inheritDoc
	 */
	public function __construct($config)
	{
		$this->siteId = isset($config->PiwikStatistics->site_id) ? $config->PiwikStatistics->site_id : 1;
	}
	
}