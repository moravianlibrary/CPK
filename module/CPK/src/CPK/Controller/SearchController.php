<?php
/**
 * Search Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
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
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use VuFind\Controller\SearchController as SearchControllerBase;

/**
 * SearchController
 *
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class SearchController extends SearchControllerBase
{
	/**
	 * Results action.
	 *
	 * @return mixed
	 */
	public function resultsAction()
	{
		// Special case -- redirect tag searches.
		$tag = $this->params()->fromQuery('tag');
		if (!empty($tag)) {
			$query = $this->getRequest()->getQuery();
			$query->set('lookfor', $tag);
			$query->set('type', 'tag');
		}
		if ($this->params()->fromQuery('type') == 'tag') {
			return $this->forwardTo('Tag', 'Home');
		}

		// Default case -- standard behavior.
		$view = parent::resultsAction();

		$view->myLibs = $this->getUsersHomeLibraries();
		$view->config = $this->getConfig();
		
		$facetConfig = $this->getConfig('facets');
		$institutionsMappings = $facetConfig->InstitutionsMappings->toArray();
		$view->institutionsMappings = $institutionsMappings;

		return $view;
	}
	
	/**
	 * Handle an advanced search
	 *
	 * @return mixed
	 */
	public function advancedAction()
	{
	    // Standard setup from base class:
	    $view = parent::advancedAction();
	
	    // Set up facet information:
	    $view->facetList = $this->processAdvancedFacets(
	        $this->getAdvancedFacets()->getFacetList(), $view->saved
	        );
	
	    $prefferedFacets = array();
	    $config = $this->getServiceLocator()->get('VuFind\Config')->get('facets');
	
	    if (count($config->PreferredFacets)) {
	        foreach ($config->PreferredFacets as $field => $values) {
	            $vals = array();
	            $i = 0;
	            foreach ($values as $val) {
	                $i++;
	                $vals[$val] = $i;
	            }
	            $prefferedFacets[$field] = $vals;
	        }
	    }
	
	    $view->preferredFacets = $prefferedFacets;
	
	    $specialFacets = $this->parseSpecialFacetsSetting(
	        $view->options->getSpecialAdvancedFacets()
	        );
	    if (isset($specialFacets['illustrated'])) {
	        $view->illustratedLimit
	        = $this->getIllustrationSettings($view->saved);
	    }
	    if (isset($specialFacets['checkboxes'])) {
	        $view->checkboxFacets = $this->processAdvancedCheckboxes(
	            $specialFacets['checkboxes'], $view->saved
	            );
	    }
	    $view->ranges = $this->getAllRangeSettings($specialFacets, $view->saved);
	    $view->hierarchicalFacets = $this->getHierarchicalFacets();
	    
	    // Has user preferred user settings?
	    if ($user = $this->getAuthManager()->isLoggedIn()) {
	        $userSettingsTable = $this->getTable("usersettings");
	    
	        $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
	        if (! empty($preferredRecordsPerPage))
	            $view->preferredRecordsPerPage = $preferredRecordsPerPage;
	    }
	
	    return $view;
	}

    /**
     * User's Library cards (home_library values)
     *
     * @return array
     */
    public function getUsersHomeLibraries()
    {
        $account = $this->getAuthManager();
        if ($account->isLoggedIn()) { // is loggedIn

            $user = $this->getUser();

            if ($user instanceof \CPK\Db\Row\User)
                return $user->getNonDummyInstitutions();
        } else
            return [];
    }
}