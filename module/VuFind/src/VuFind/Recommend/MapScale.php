<?php
/**
* MapScale Recommendations Module
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
* @author   Erich Duda <dudaerich@gmail.com>
* @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
* @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
*/
namespace Vufind\Recommend;

/**
 * MapScale Recommendations Module
 *
 * This class recommends scale filter for maps.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Erich Duda <dudaerich@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class MapScale implements RecommendInterface
{
    /**
     * Store information if it is set a filter for the maps.
     * 
     * @var boolean
     */
    protected $issetFormat = false;
    
    /**
     * Store information if it is set a dateRange in the facets.ini,
     * which enables this module.
     * 
     * @var boolean
     */
    protected $issetDateRange = false;
    
    /**
     * Determines which filter has to be set for displaying
     * this module.
     * 
     * @var string
     */
    protected $filter;
    
    /**
     * Store name of filter.
     * 
     * @var string
     */
    protected $filterName;
    
    /**
     * Store module configuration
     * 
     * @var array
     */
    protected $settings;
    
    /**
     * Store GET params
     * 
     * @var array
     */
    protected $params;
    
    /**
     * Store 'from' range getting from GET params.
     * 
     * @var number
     */
    protected $fromValue = null;
    
    /**
     * Store 'to' range getting from GET params.
     *
     * @var number
     */
    protected $toValue = null;
    
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
        // Parse settings
        list($dateRange,
             $filterType,
             $filterValue,
             $moduleSettings) = explode(':', $settings);
        // Load facets.ini
        $config = $this->configLoader->get('facets');
        // Check if a dateRange from settings
        // is enabled in facets.ini
        $dateRangeConf =
            isset($config->SpecialFacets) &&
            isset($config->SpecialFacets->dateRange)
            ? $config->SpecialFacets->dateRange->toArray()
            : null;
        $this->issetDateRange = is_array($dateRangeConf) && in_array($dateRange, $dateRangeConf);
        // store filter setting
        $this->filter = "$filterType:$filterValue";
        // store name of filter
        $this->filterName = $dateRange;
        // store module settings
        $this->settings = explode(',', $moduleSettings);
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
	    $this->issetFormat = $params->hasFilter($this->filter);
	    $this->params = $request->toArray();
	    // process params
	    if (isset($this->params['daterange']) && is_array($this->params['daterange'])) {
	        for($i = 0; $i < count($this->params['daterange']); ++$i) {
	            if ($this->params['daterange'][$i] == $this->filterName) {
	                unset($this->params['daterange'][$i]);
	            }
	        }
	    }
	    // remove daterange if it is empty
	    if (empty($this->params['daterange'])) {
	        unset($this->params['daterange']);
	    }
	    // save values
	    $this->fromValue = $this->params[$this->filterName . 'from'];
	    $this->toValue = $this->params[$this->filterName . 'to'];
	    // remove values from params
	    unset($this->params[$this->filterName . 'from']);
	    unset($this->params[$this->filterName . 'to']);
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
	}
	
	/**
	 * Determine if this recommendation should be displayed.
	 * @return boolean
	 */
	public function isActive()
	{
	    return $this->issetDateRange && $this->issetFormat;
	}
	
	/**
	 * Returns module settings.
	 * 
	 * @return array
	 */
	public function getSettings()
	{
	    return $this->settings;
	}
	
	/**
	 * Returns actual GET params.
	 * 
	 * @return array
	 */
	public function getParams()
	{
	    return $this->params;
	}
	
	/**
	 * Returns name of filter.
	 * 
	 * @return string
	 */
	public function getFilterName()
	{
		return $this->filterName;
	}
	
	/**
	 * Returns fromValue.
	 * 
	 * @return number
	 */
	public function getFromValue()
	{
	    return $this->fromValue;
	}

	/**
	 * Returns toValue.
	 * 
	 * @return number
	 */
	public function getToValue()
	{
	    return $this->toValue;
	}
}