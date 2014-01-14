<?php
/**
 * NewItems Recommendations Module
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
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * NewItems Recommendations Module
 *
 * This class provides support for new items search
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class NewItems implements RecommendInterface
{

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    protected $dateField = 'acq_int';

    protected $selectedDateRange = null;

    protected $searchParams = null;

    protected $dateRanges = array();

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * setConfig
     *
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {

    }

    /**
     * init
     *
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $filters = $params->getFilters();
        foreach ($filters as $key => $value) {
            if ($key == $this->dateField) {
                $this->selectedDateRange = $value[0];
            }
        }
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        if ($this->selectedDateRange == null) {
            return;
        }
        $this->searchParams = $results->getUrlQuery()->removeFacet($this->geoField, $this->selectedDateRange, false);
        $curr_date = date('Ym', strtotime('now'));
        $s1 = date('Ym', strtotime('last year'));
        $e1 = date('Ym', strtotime('last year december'));
        $s2 = date('Ym', strtotime('this year january'));
        $e2 = $curr_date;
        $ranges = array_merge(range($e2, $s2), range($e1, $s1));
        foreach ($ranges as $date) {
            $range = $this->createRange($date . '01', $e2 . '31');
            $label = $this->createLabel($date);
            $this->dateRanges[$label] = array(
                'filter' => $this->createFilter($range),
                'selected' => ($this->selectedDateRange == $range)
            );
        }
    }

    public function getSelectedDateRange()
    {
        return $this->selectedDateRange;
    }

    public function getDateRanges()
    {
        return $this->dateRanges;
    }

    public function getDateField()
    {
        return $this->dateField;
    }

    private function createFilter($range)
    {
        return $this->dateField . ':' . $range;
    }

    private function createRange($begin, $end) {
        return "[$begin TO $end]";
    }

    private function createLabel($date) {
        $date = substr($date, 0, 4) . '-' . substr($date, 4, 6);
        return strftime('%B %Y', strtotime($date));
    }

}
