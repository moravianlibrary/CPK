<?php
/**
 * Search Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKCommon\Controller;

use VuFind\Controller\SearchController as SearchControllerBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SearchController extends SearchControllerBase
{

    const MOST_SEARCHED_CACHE_NAME         = 'mostSearched';

    protected $conspectusField     = 'category_txtF';
    protected $conspectusFieldName = 'Conspectus';

    /**
     * Conspectus Action
     *
     * @return mixed
     */
    public function conspectusAction()
    {
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->addFacet($this->conspectusField, $this->conspectusFieldName);
        $params->setLimit(0);
        $results->getResults();
        $facets = $results->getFacetList();
        return $this->createViewModel(
            array('results' => $results, 'facets' => $facets, 'field' => $this->conspectusField)
        );
    }

    public function mostSearchedAction()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('searches');
        $numberOfQueries = 20;
        $cacheAliveTime = 60 * 60;
        $overLastTimePeriod = 24 * 60 * 60;
        if (isset($config->MostSearched)) {
            $conf = $config->MostSearched;
            if (isset($conf->numberOfQueries)) {
                $numberOfQueries = $conf->numberOfQueries;
            }
            if (isset($conf->cacheAliveTime)) {
                $cacheAliveTime = $conf->cacheAliveTime;
            }
            if (isset($conf->overLastTimePeriod)) {
                $overLastTimePeriod = $conf->overLastTimePeriod;
            }
        }
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('object');
        $result = $cache->getItem(self::MOST_SEARCHED_CACHE_NAME);
        $now = time();
        if (!$result || ($now - $result['time']) > $cacheAliveTime) {
            $from = $now - $overLastTimePeriod;
            $userStats = $this->getTable('UserStatsFields');
            $queries = array();
            foreach ($userStats->getMostSearchedQueries($from, $now, $numberOfQueries) as $row) {
                $queries[] = $row;
            }
            $result = array();
            $result['queries'] = $queries;
            $result['time'] = $now;
            $cache->setItem(self::MOST_SEARCHED_CACHE_NAME, $result);
        }
        return $this->createViewModel(
            array('queries' => $result['queries'])
        );
    }

}
