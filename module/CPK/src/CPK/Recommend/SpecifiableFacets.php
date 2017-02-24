<?php
/**
 * SpecifiableFacets Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace CPK\Recommend;
use VuFind\Recommend\RecommendInterface;

/**
 * Recommendation class for specifiable facets
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class SpecifiableFacets implements RecommendInterface
{

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    protected $results;

    protected $facetsWithSelect = array();

    protected $config = array();

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
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'SpecifiableFacets':$settings[0];
        $iniName = isset($settings[1]) ? $settings[1] : 'searches';
        $config = $this->configLoader->get($iniName);
        $specFacets = isset($config->$mainSection) ? $config->$mainSection : array();
        foreach ($specFacets as $label => $expand) {
            list($baseFacet, $expFacetField, $type) = explode(':', $expand);
            if ($type == 'select') {
                $this->facetsWithSelect[] = $expFacetField;
            }
            $this->config[$baseFacet] = array(
                'label' => $label,
                'field' => $expFacetField
            );
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
            if (isset($this->config[$key])) {
                $expFacet = $this->config[$key];
                $params->addFacet($expFacet['field'], $expFacet['label']);
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
        $this->results = $results;
        $filter = array();
        foreach ($this->config as $baseFacet => $exp) {
            $filter[$exp['field']] = $exp['label'];
        }
        $facetsToFilter = $results->getFacetList($filter);
        $filteredFacets = array();
        foreach ($facetsToFilter as $field => $cluster) {
            if (count($cluster['list']) > 1) {
                $filteredFacets[$field] = $cluster;
            }
        }
        $this->facets = $filteredFacets;
    }

    public function getFacets()
    {
        return $this->facets;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getFacetsWithSelect()
    {
        return $this->facetsWithSelect;
    }

    public function getViewSettings()
    {
        return array ('cols' => 3);
    }

}
