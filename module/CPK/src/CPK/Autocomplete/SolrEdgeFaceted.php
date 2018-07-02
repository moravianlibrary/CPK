<?php
/**
 * Solr Autocomplete Module
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
namespace CPK\Autocomplete;

use \VuFind\Autocomplete\SolrEdgeFaceted as ParentSolrEdgeFaceted;

/**
 * Solr Edge Faceted Autocomplete Module
 *
 * This class provides suggestions by using the local Solr index.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
class SolrEdgeFaceted extends ParentSolrEdgeFaceted
{
    /**
     * getSuggestions
     *
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     * @param array  $facetFilters User defined facets
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestionsWithFilters($query, $facetFilters = null)
    {
        if (! is_object($this->searchObject)) {
            throw new \Exception('Please set configuration first.');
        }
        $results = array();
        try {
            $this->searchObject->getParams()->setBasicSearch(
                $this->autocompleteField.':('.$this->mungeQuery($query).')'
            );
            $params = $this->searchObject->getParams();
            if ($facetFilters != 'null') {
                if (is_array($facetFilters)) {
                    foreach ($facetFilters as $facetFilter) {
                        $this->searchObject->getParams()->addFilter($facetFilter);
                    }
                } else {
                    $this->searchObject->getParams()->addFilter($facetFilters);
                }
            }
            $params->addFacet($this->facetField);
            $params->setLimit(0);
            $params->setFacetLimit(30);
            $this->searchObject->getParams()->setSort($this->facetField);
            $results = $this->searchObject->getResults();
            $facets  = $this->searchObject->getFacetList();

            if (isset($facets[$this->facetField]['list'])) {
                $queryWithoutDiacritic = $this->removeDiacritic($query);
                $queryParts            = preg_split('/\s+/', $queryWithoutDiacritic);
                $queryPartsCount       = count($queryParts);

                foreach ($facets[$this->facetField]['list'] as $filter) {
                    $matchedQueryParts = 0;

                    foreach ($queryParts as $queryPart) {
                        $foundItems = preg_split('/\s+/', $this->removeDiacritic($filter['value']));

                        foreach($foundItems as $foundItem) {
                            if (stripos($foundItem, $queryPart) !== false) {
                                $matchedQueryParts++;
                            }
                        }
                    }

                    if ($matchedQueryParts == $queryPartsCount) {
                        array_push($results, $filter['value']);
                    }
                }
            }

        } catch (\Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return array_unique($results);
    }

    /**
     * Experimental autocomplete that returns also asociative results.
     *
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     * @param array  $facetFilters User defined facets
     *
     * @return array        The suggestions for the provided query
     */
    public function getSomeSuggestionsWithFilters($query, array $facetFilters)
    {
        if (!is_object($this->searchObject)) {
            throw new \Exception('Please set configuration first.');
        }

        $results = array();
        try {
            $this->searchObject->getParams()->setBasicSearch(
                $this->mungeQuery($query), $this->facetField
                );
            $params = $this->searchObject->getParams();
            $options = $this->searchObject->getOptions();
            if ($facetFilters != 'null') {
                if (is_array($facetFilters)) {
                    foreach ($facetFilters as $facetFilter) {
                        $this->searchObject->getParams()->addFilter($facetFilter);
                    }
                } else {
                    $this->searchObject->getParams()->addFilter($facetFilters);
                }
            }
            $params->addFacet($this->facetField);
            $params->setLimit(0);
            $params->setFacetLimit(25);
            $this->searchObject->getParams()->setSort($this->facetField);
            $results = $this->searchObject->getResults();
            $facets = $this->searchObject->getFacetList();
            if (isset($facets[$this->facetField]['list'])) {
                foreach ($facets[$this->facetField]['list'] as $filter) {
                    $results[] = $filter['value'];
                }
            }
        } catch (\Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return array_unique($results);
    }

    private function removeDiacritic($str) {
        $conversionTable = [
            'ä'=>'a',
            'Ä'=>'A',
            'á'=>'a',
            'Á'=>'A',
            'à'=>'a',
            'À'=>'A',
            'ã'=>'a',
            'Ã'=>'A',
            'â'=>'a',
            'Â'=>'A',
            'č'=>'c',
            'Č'=>'C',
            'ć'=>'c',
            'Ć'=>'C',
            'ď'=>'d',
            'Ď'=>'D',
            'ě'=>'e',
            'Ě'=>'E',
            'é'=>'e',
            'É'=>'E',
            'ë'=>'e',
            'Ë'=>'E',
            'è'=>'e',
            'È'=>'E',
            'ê'=>'e',
            'Ê'=>'E',
            'í'=>'i',
            'Í'=>'I',
            'ï'=>'i',
            'Ï'=>'I',
            'ì'=>'i',
            'Ì'=>'I',
            'î'=>'i',
            'Î'=>'I',
            'ľ'=>'l',
            'Ľ'=>'L',
            'ĺ'=>'l',
            'Ĺ'=>'L',
            'ń'=>'n',
            'Ń'=>'N',
            'ň'=>'n',
            'Ň'=>'N',
            'ñ'=>'n',
            'Ñ'=>'N',
            'ó'=>'o',
            'Ó'=>'O',
            'ö'=>'o',
            'Ö'=>'O',
            'ô'=>'o',
            'Ô'=>'O',
            'ò'=>'o',
            'Ò'=>'O',
            'õ'=>'o',
            'Õ'=>'O',
            'ő'=>'o',
            'Ő'=>'O',
            'ř'=>'r',
            'Ř'=>'R',
            'ŕ'=>'r',
            'Ŕ'=>'R',
            'š'=>'s',
            'Š'=>'S',
            'ś'=>'s',
            'Ś'=>'S',
            'ť'=>'t',
            'Ť'=>'T',
            'ú'=>'u',
            'Ú'=>'U',
            'ů'=>'u',
            'Ů'=>'U',
            'ü'=>'u',
            'Ü'=>'U',
            'ù'=>'u',
            'Ù'=>'U',
            'ũ'=>'u',
            'Ũ'=>'U',
            'û'=>'u',
            'Û'=>'U',
            'ý'=>'y',
            'Ý'=>'Y',
            'ž'=>'z',
            'Ž'=>'Z',
            'ź'=>'z',
            'Ź'=>'Z'
       ];

       return strtr($str, $conversionTable);
    }
}
