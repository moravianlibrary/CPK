<?php
/**
 * MapSelection Recommendations Module
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
 * MapSelection Recommendations Module
 *
 * This class provides geospatial search
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class MapSelection implements RecommendInterface
{
   
    protected $defaultCoordinates = array(11.20, 48.30, 19.40, 51.30);
    
    protected $geoField = 'bbox_geo';
    
    protected $height = 480;
    
    protected $selectedCoordinates = null;
    
    protected $searchParams = null;
    
    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;
    
    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader) {
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
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'MapSelection':$settings[0];
        $iniName = isset($settings[1]) ? $settings[1] : 'searches';
        $config = $this->configLoader->get($iniName);
        if (isset($config->$mainSection)) {
            $entries = $config->$mainSection;
            if (isset($entries->default_coordinates)) {
                $this->defaultCoordinates = explode(',', $entries->default_coordinates);
            }
            if (isset($entries->geo_field)) {
                $this->geoField = $entries->geo_field;
            }
            if (isset($entries->height)) {
                $this->height = $entries->height;
            }
        }
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
            if ($key == $this->geoField) {
                $match = array();
                if (preg_match('/Intersects\(([0-9 \\-\\.]+)\)/', $value[0], $match)) {
                    $coords = $match[1];
                    $params->addBoostFunction("geo_overlap('$coords', bbox_geo_str)");
                }
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
        $filters = $results->getParams()->getFilters();
        foreach ($filters as $key => $value) {
            if ($key == $this->geoField) {
                $match = array();
                if (preg_match( '/Intersects\(([0-9 \\-\\.]+)\)/', $value[0], $match)) {
                    $this->selectedCoordinates = explode(' ', $match[1]);
                }
                $this->searchParams = $results->getUrlQuery()->removeFacet($this->geoField, $value[0], false);
            }
        }
        if ($this->searchParams == null) {
            $this->searchParams = $results->getUrlQuery()->getParams(false);
        }
    }
    
    /**
     * getSelectedCoordinates
     * 
     * Return coordinates selected by user
     * 
     * @return array of floats
     */
    public function getSelectedCoordinates()
    {
        return $this->selectedCoordinates;
    }
    
    /**
     * getDefaultCoordinates
     *
     * Return default coordinates from configuration
     *
     * @return array of floats
     */
    public function getDefaultCoordinates()
    {
        return $this->defaultCoordinates;
    }
    
    /** 
     * getHeight
     * 
     * Return height of map in pixels
     * 
     * @return number
     */
    public function getHeight() {
        return $this->height;
    }
    
    /**
     * getSearchParams
     * 
     * Return search params without filter for geographic search
     * 
     */
    public function getSearchParams()
    {
        return $this->searchParams;
    }
    
    /**
     * getGeoField
     * 
     * Return Solr field to use for geographic search
     * 
     * @return string
     */
    public function getGeoField()
    {
        return $this->geoField;
    }
    
}
