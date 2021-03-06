<?php
/**
 * "Search tabs" view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use VuFind\Search\Base\Results,
    VuFind\Search\Results\PluginManager,
    VuFind\Search\SearchTabsHelper,
    Zend\View\Helper\Url,
    Zend\Http\Request;

/**
 * "Search tabs" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabs extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $results;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Url
     *
     * @var Url
     */
    protected $url;

    /**
     * Search tab helper
     *
     * @var SearchTabsHelper
     */
    protected $helper;

    /**
     * Constructor
     *
     * @param PluginManager    $results Search results plugin manager
     * @param Url              $url     URL helper
     * @param SearchTabsHelper $helper  Search tabs helper
     */
    public function __construct(PluginManager $results, Url $url,
        SearchTabsHelper $helper
    ) {
        $this->results = $results;
        $this->url = $url;
        $this->helper = $helper;
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $hiddenFilters     The current hidden filters
     *
     * @return array
     */
    public function getTabConfig($activeSearchClass, $query, $handler,
        $type = 'basic', $hiddenFilters = []
    ) {
        $retVal = [];
        $matchFound = false;
        $allFilters = $this->helper->getTabFilterConfig();
        foreach ($this->helper->getTabConfig() as $key => $label) {
            $class = $this->helper->extractClassName($key);
            $filters = isset($allFilters[$key]) ? (array)$allFilters[$key] : [];
            if ($class == $activeSearchClass
                && $this->helper->filtersMatch($class, $hiddenFilters, $filters)
            ) {
                $matchFound = true;
                $retVal[] = $this->createSelectedTab($key, $class, $label);
            } else if ($type == 'basic') {
                if (!isset($activeOptions)) {
                    $activeOptions
                        = $this->results->get($activeSearchClass)->getOptions();
                }
                $newUrl = $this->remapBasicSearch(
                    $activeOptions, $class, $query, $handler, $filters
                );
                $retVal[] = $this->createBasicTab($key, $class, $label, $newUrl);
            } else if ($type == 'advanced') {
                $retVal[] = $this->createAdvancedTab($key, $class, $label, $filters);
            } else {
                $retVal[] = $this->createHomeTab($key, $class, $label, $filters);
            }
        }
        if (!$matchFound && !empty($retVal)) {
            // Make the first tab for the given search class selected
            foreach ($retVal as &$tab) {
                if ($tab['class'] == $activeSearchClass) {
                    $tab['selected'] = true;
                    break;
                }
            }
        }

        return $retVal;
    }

    /**
     * Get an array of hidden filters
     *
     * @param string $searchClassId         Active search class
     * @param bool   $returnDefaultsIfEmpty Whether to return default tab filters if
     * no filters are currently active
     *
     * @return array
     */
    public function getHiddenFilters($searchClassId, $returnDefaultsIfEmpty = true)
    {
        return $this->helper
            ->getHiddenFilters($searchClassId, $returnDefaultsIfEmpty);
    }

    /**
     * Create information representing a selected tab.
     *
     * @param string $id    Tab ID
     * @param string $class Search class ID
     * @param string $label Display text for tab
     *
     * @return array
     */
    protected function createSelectedTab($id, $class, $label)
    {
        return [
            'id' => $id,
            'class' => $class,
            'label' => $label,
            'selected' => true
        ];
    }

    /**
     * Map a search query from one class to another.
     *
     * @param \VuFind\Search\Base\Options $activeOptions Search options for source
     * @param string                      $targetClass   Search class ID for target
     * @param string                      $query         Search query to map
     * @param string                      $handler       Search handler to map
     * @param array                       $filters       Tab filters
     *
     * @return string
     */
    protected function remapBasicSearch($activeOptions, $targetClass, $query,
        $handler, $filters
    ) {
        // Set up results object for URL building:
        $results = $this->results->get($targetClass);
        $params = $results->getParams();
        foreach ($filters as $filter) {
            $params->addHiddenFilter($filter);
        }

        // Find matching handler for new query (and use default if no match):
        $options = $results->getOptions();
        $targetHandler = $options->getHandlerForLabel(
            $activeOptions->getLabelForBasicHandler($handler)
        );

        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return $this->url->__invoke($options->getSearchAction())
            . $results->getUrlQuery()->getParams(false);
    }

    /**
     * Create information representing a basic search tab.
     *
     * @param string $id     Tab ID
     * @param string $class  Search class ID
     * @param string $label  Display text for tab
     * @param string $newUrl Target search URL
     *
     * @return array
     */
    protected function createBasicTab($id, $class, $label, $newUrl)
    {
        return [
            'id' => $id,
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $newUrl
        ];
    }

    /**
     * Create information representing a tab linking to "search home."
     *
     * @param string $id      Tab ID
     * @param string $class   Search class ID
     * @param string $label   Display text for tab
     * @param array  $filters Tab filters
     *
     * @return array
     */
    protected function createHomeTab($id, $class, $label, $filters)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $results = $this->results->get($class);
        $urlParams = $results->getUrlQuery()->getParams(false);
        $url = $this->url->__invoke($results->getOptions()->getSearchHomeAction())
            . $this->buildUrlHiddenFilters($results, $filters);
        return [
            'id' => $id,
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $url
        ];
    }

    /**
     * Create information representing an advanced search tab.
     *
     * @param string $id      Tab ID
     * @param string $class   Search class ID
     * @param string $label   Display text for tab
     * @param array  $filters Tab filters
     *
     * @return array
     */
    protected function createAdvancedTab($id, $class, $label, $filters)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $results = $this->results->get($class);
        $options = $results->getOptions();
        $advSearch = $options->getAdvancedSearchAction();
        $url = $this->url
            ->__invoke($advSearch ? $advSearch : $options->getSearchHomeAction())
            . $this->buildUrlHiddenFilters($results, $filters);
        return [
            'id' => $id,
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $url
        ];
    }

    /**
     * Build a hidden filter query fragment from the given filters
     *
     * @param Results $results Search results
     * @param array   $filters Filters
     *
     * @return string Query parameters
     */
    protected function buildUrlHiddenFilters(Results $results, $filters)
    {
        // Set up results object for URL building:
        $params = $results->getParams();
        foreach ($filters as $filter) {
            $params->addHiddenFilter($filter);
        }
        $urlParams = $results->getUrlQuery()->getParams(false);
        return $urlParams !== '?' ? $urlParams : '';
    }
}
