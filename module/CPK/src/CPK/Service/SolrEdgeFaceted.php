<?php
/**
 * Solr Module
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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
 * @package  Service
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\Service;

use \VuFind\Autocomplete\SolrEdgeFaceted as ParentSolrEdgeFaceted;

/**
 * Solr Edge Faceted Module
 *
 * This class provides easy object-based access to the local Solr index.
 *
 * @category VuFind2
 * @package Service
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class SolrEdgeFaceted extends ParentSolrEdgeFaceted
{

    /**
     * getSuggestions
     *
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query
     *            The user query
     * @param array $facetFilters
     *            User defined facets
     *
     * @return array The suggestions for the provided query
     */
    public function getRecordIdFromAvailabilityIdAndLocalId($source, $id)
    {
        $this->initSearchObject();

        $result = null;
        try {
            $this->searchObject->getParams()->setBasicSearch('availability_id_str_mv:' . $id . '');
            $params = $this->searchObject->getParams();
            $options = $this->searchObject->getOptions();

            $params->addFacet('local_ids_str_mv');
            $params->setLimit(0);
            $params->setFacetLimit(6000);
            $facets = $this->searchObject->getFacetList();

            foreach($facets['local_ids_str_mv']['list'] as $match) {
                if (strpos($match['value'], $source) !== false)
                    return $match['value'];
            }

        } catch (\Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return $result;
    }

    /**
     * initSearchObject
     *
     * Initialize the search object used for finding recommendations.
     *
     * @return void
     */
    protected function initSearchObject()
    {
        // Build a new search object:
        $this->searchObject = $this->resultsManager->get($this->searchClassId);
        $this->searchObject->getOptions()->spellcheckEnabled(false);
        $this->searchObject->getOptions()->disableHighlighting();
    }
}
