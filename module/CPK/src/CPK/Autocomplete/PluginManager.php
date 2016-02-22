<?php
/**
 * Autocomplete handler plugin manager
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 *@category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace CPK\Autocomplete;

/**
 * Autocomplete handler plugin manager
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Autocomplete\AutocompleteInterface';
    }
    
    /**
     * This returns an array of suggestions based on current request parameters.
     * This logic is present in the factory class so that it can be easily shared
     * by multiple AJAX handlers.
     *
     * @param \Zend\Stdlib\Parameters $request    The user request
     * @param string                  $typeParam  Request parameter containing search
     * type
     * @param string                  $queryParam Request parameter containing query
     * string
     * @param array                   $facetFilters
     *
     * @return array
     */
    public function getSuggestions(
        $request, 
        $typeParam = 'type', 
        $queryParam = 'q',
        $facetFilters = []
    ) 
    {
        // Process incoming parameters:
        $type = $request->get($typeParam, '');
        $query = $request->get($queryParam, '');
        $searcher = $request->get('searcher', 'Solr');
        // If we're using a combined search box, we need to override the searcher
        // and type settings.
        if (substr($type, 0, 7) == 'VuFind:') {
            list(, $tmp) = explode(':', $type, 2);
            list($searcher, $type) = explode('|', $tmp, 2);
        }

        // get Autocomplete_Type config
        $options = $this->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager')->get($searcher);
        $config = $this->getServiceLocator()->get('VuFind\Config')
            ->get($options->getSearchIni());
        $types = isset($config->Autocomplete_Types) ?
            $config->Autocomplete_Types->toArray() : [];
            
        // Figure out which handler to use:
        // Handler
        // solr field with "text_autocomplete" type
        // solr field with "string" type that is exactly equal to previous one
        $titleModule   = "SolrEdgeFaceted:title_autocomplete:title_auto_str";
        $authorModule  = "SolrEdgeFaceted:author_autocomplete:author_str_mv";
        $subjectModule = "SolrEdgeFaceted:subject_autocomplete:subject_str_mv";

        // Get suggestions:
        if ($titleModule) {
            if (strpos($titleModule, ':') === false) {
                $titleModule .= ':'; // force colon to avoid warning in explode below
            }
            list($titleName, $titleParams) = explode(':', $titleModule, 2);
            $titleHandler = $this->get($titleName);
            $titleHandler->setConfig($titleParams);
        }
        
        if ($authorModule) {
            if (strpos($authorModule, ':') === false) {
                $authorModule .= ':'; // force colon to avoid warning in explode below
            }
            list($authorName, $authorParams) = explode(':', $authorModule, 2);
            
            /* $authorHandler Needs to be cloned, becouse $authorHandler is
            the same object as $titleHandler, so the $titleHandler
            would have overwritten params! */
            $authorHandler = clone $this->get($authorName); 
            $authorHandler->setConfig($authorParams);
        }
        
        if ($subjectModule) {
            if (strpos($subjectModule, ':') === false) {
                $subjectModule .= ':'; // force colon to avoid warning in explode below
            }
            list($subjectName, $subjectParams) = explode(':', $subjectModule, 2);
            $subjectHandler = clone $this->get($subjectName);
            $subjectHandler->setConfig($subjectParams);
        }
        
        $titleSuggestions = (isset($titleHandler) && is_object($titleHandler))
            ? array_values($titleHandler->getSuggestionsWithFilters($query, $facetFilters)) : [];
        
        $authorSuggestions = (isset($authorHandler) && is_object($authorHandler))
            ? array_values($authorHandler->getSuggestionsWithFilters($query, $facetFilters)) : [];
        
        $subjectSuggestions = (isset($subjectHandler) && is_object($subjectHandler))
            ? array_values($subjectHandler->getSuggestionsWithFilters($query, $facetFilters)) : [];
        
        $suggestions['byTitle'] = $titleSuggestions;
        $suggestions['byAuthor'] = $authorSuggestions;
        $suggestions['bySubject'] = $subjectSuggestions;
            
        return $suggestions;
    }
}