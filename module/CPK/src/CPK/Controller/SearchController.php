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
use VuFind\Exception\Mail as MailException;
use VuFind\Exception\RecordMissing as RecordMissingException;

/**
 * SearchController
 *
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class SearchController extends SearchControllerBase
{
    use LoginTrait;

    protected $conspectusField     = 'conspectus_str_mv';
    protected $conspectusFieldName = 'Conspectus';

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
            if (! empty($preferredRecordsPerPage)) {
                $view->preferredRecordsPerPage = $preferredRecordsPerPage;
            }
	    }

	    return $view;
	}

	/**
	 * Email action - Allows the email form to appear.
	 *
	 * @return mixed
	 */
	public function emailAction()
	{
	    // If a URL was explicitly passed in, use that; otherwise, try to
	    // find the HTTP referrer.
	    $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
	    $view = $this->createEmailViewModel(null, $mailer->getDefaultLinkSubject());
	    $mailer->setMaxRecipients($view->maxRecipients);
	    // Set up reCaptcha
	    $view->useRecaptcha = $this->recaptcha()->active('email');
	    $view->url = $this->params()->fromPost(
	        'url', $this->params()->fromQuery(
	            'url',
	            $this->getRequest()->getServer()->get('HTTP_REFERER')
	            )
	        );

	    // Force login if necessary:
	    $config = $this->getConfig();
	    if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
	        && !$this->getUser()
	        ) {
	            return $this->forceLogin(null, ['emailurl' => $view->url]);
	        }

	        // Check if we have a URL in login followup data -- this should override
	        // any existing referer to avoid emailing a login-related URL!
	        $followupUrl = $this->followup()->retrieveAndClear('emailurl');
	        if (!empty($followupUrl)) {
	            $view->url = $followupUrl;
	        }

	        // Fail if we can't figure out a URL to share:
	        if (empty($view->url)) {
	            throw new \Exception('Cannot determine URL to share.');
	        }

	        // Process form submission:
	        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
	            // Attempt to send the email and show an appropriate flash message:
	            try {
	                // If we got this far, we're ready to send the email:
	                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
	                ? $view->from : null;
	                $sender = new \Zend\Mail\Address(
	                    $view->from,
	                    $this->translate('Central Library Portal')
	                );
	                $mailer->sendLink(
	                    $view->to, $sender, $view->message,
	                    $view->url, $this->getViewRenderer(), $view->subject, $cc,
	                    $this->translate('Central Library Portal')
	                    );
	                $this->flashMessenger()->addMessage('email_success', 'success');
	                return $this->redirect()->toUrl($view->url);
	            } catch (MailException $e) {
	                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
	            }
	        }
	        return $view;
	}

	/**
	 * Get the possible legal values for the illustration limit radio buttons.
	 *
	 * @param object $savedSearch Saved search object (false if none)
	 *
	 * @return array              Legal options, with selected value flagged.
	 */
	protected function getIllustrationSettings($savedSearch = false)
	{
	    $illYes = [
	                    'text' => 'Has Illustrations', 'value' => 1, 'selected' => false
	    ];
	    $illNo = [
	                    'text' => 'Not Illustrated', 'value' => 0, 'selected' => false
	    ];
	    $illAny = [
	                    'text' => 'No Preference', 'value' => -1, 'selected' => false
	    ];

	    // Find the selected value by analyzing facets -- if we find match, remove
	    // the offending facet to avoid inappropriate items appearing in the
	    // "applied filters" sidebar!
	    if ($savedSearch
	        && $savedSearch->getParams()->hasFilter('illustrated:Illustrated')
	        ) {
	            $illYes['selected'] = true;
	            $savedSearch->getParams()->removeFilter('illustrated:Illustrated');
	        } else if ($savedSearch
	            && $savedSearch->getParams()->hasFilter('illustrated:"Not Illustrated"')
	            ) {
	                $illNo['selected'] = true;
	                $savedSearch->getParams()->removeFilter('illustrated:"Not Illustrated"');
	            } else {
	                $illAny['selected'] = true;
	            }
	            return [$illYes, $illNo, $illAny];
	}

	/**
	 * Process the facets to be used as limits on the Advanced Search screen.
	 *
	 * @param array  $facetList    The advanced facet values
	 * @param object $searchObject Saved search object (false if none)
	 *
	 * @return array               Sorted facets, with selected values flagged.
	 */
	protected function processAdvancedFacets($facetList, $searchObject = false)
	{
	    // Process the facets
	    $hierarchicalFacets = $this->getHierarchicalFacets();
	    $facetHelper = null;
	    if (!empty($hierarchicalFacets)) {
	        $facetHelper = $this->getServiceLocator()
	        ->get('VuFind\HierarchicalFacetHelper');
	    }
	    foreach ($facetList as $facet => &$list) {
	        // Hierarchical facets: format display texts and sort facets
	        // to a flat array according to the hierarchy
	        if (in_array($facet, $hierarchicalFacets)) {
	            $tmpList = $list['list'];
	            $facetHelper->sortFacetList($tmpList, true);
	            $tmpList = $facetHelper->buildFacetArray(
	                $facet,
	                $tmpList
	                );
	            $list['list'] = $facetHelper->flattenFacetHierarchy($tmpList);
	        }

	        foreach ($list['list'] as $key => $value) {
	            // Build the filter string for the URL:
	            $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
	            . $facet . ':"' . $value['value'] . '"';

	            // If we haven't already found a selected facet and the current
	            // facet has been applied to the search, we should store it as
	            // the selected facet for the current control.
	            if ($searchObject
	                && $searchObject->getParams()->hasFilter($fullFilter)
	                ) {
	                    $list['list'][$key]['selected'] = true;
	                    // Remove the filter from the search object -- we don't want
	                    // it to show up in the "applied filters" sidebar since it
	                    // will already be accounted for by being selected in the
	                    // filter select list!
	                    $searchObject->getParams()->removeFilter($fullFilter);
	                }
	        }
	    }
	    return $facetList;
	}

	/**
	 * Handle search history display && purge
	 *
	 * @return mixed
	 */
	public function historyAction()
	{
	    // Force login if necessary
	    $user = $this->getUser();
	    if ($this->params()->fromQuery('require_login', 'no') !== 'no' && !$user) {
	        return $this->forceLogin();
	    }

	    // Retrieve search history
	    $search = $this->getTable('Search');
	    $searchHistory = $search->getSearches(
	        $this->getServiceLocator()->get('VuFind\SessionManager')->getId(),
	        is_object($user) ? $user->id : null
	        );

	    // Build arrays of history entries
	    $saved = $unsaved = $titles = [];

	    // Loop through the history
	    foreach ($searchHistory as $current) {
	        $minSO = $current->getSearchObject();

	        // Saved searches
	        if ($current->saved == 1) {
	            $id = $current->toArray()['id'];
	            $saved[$id] = $minSO->deminify($this->getResultsManager());
	            $titles[$id] = $current->toArray();
	        } else {
	            // All the others...

	            // If this was a purge request we don't need this
	            if ($this->params()->fromQuery('purge') == 'true') {
	                $current->delete();

	                // We don't want to remember the last search after a purge:
	                $this->getSearchMemory()->forgetSearch();
	            } else {
	                // Otherwise add to the list
	                $id = $current->toArray()['id'];
	                $unsaved[$id] = $minSO->deminify($this->getResultsManager());
	                $titles[$id] = $current->toArray();
	            }
	        }
	    }

        $this->layout()->history = true;
        return $this->createViewModel(
	        ['saved' => $saved, 'unsaved' => $unsaved, 'titles' => $titles]
	        );
	}

	/**
	 * Home action
	 *
	 * @return mixed
	 */
	public function homeAction()
	{
	    $view = $this->createViewModel();

	    /* Handle view template */
	    if (! empty($this->params()->fromQuery('searchTypeTemplate')) ){
	        $view->searchTypeTemplate = $this->params()->fromQuery('searchTypeTemplate');
	    } else {
	        $view->searchTypeTemplate = 'basic';
	    }
	    
        $view->results = $this->getHomePageFacets();
        $view->hierarchicalFacets = $this->getHierarchicalFacets();
        $view->hierarchicalFacetSortOptions = $this->getHierarchicalFacetSortSettings();

        $condition = "`key`='DOC_COUNT'";
        $gw = new \VuFind\Db\Table\Gateway('system');
        $gw->setAdapter($this->getServiceLocator()->get('VuFind\DbAdapter'));
        $gw->initialize();
        $results = $gw->select($condition);
        $results = $results->toArray();
        $this->layout()->docCount = (! empty($results[0]['value'])) ? $results[0]['value'] : $view->results->getResultTotal();

        $frontendTable = $this->getTable('frontend');
        $widgetNames = $frontendTable->getHomepageWidgets();

        $widgetTable = $this->getTable('widget');
        $widgets = [];
        foreach ($widgetNames as $key => $widgetName) {
            $widget = $widgetTable->getWidgetByName($widgetName);
            if ($widgetName == 'infobox') {
                $infoboxTable = $this->getTable("infobox");
                $infoboxItems = $infoboxTable->getActualItems($widget->getShownRecordsNumber());
                $widget->setContents($infoboxItems);
            } else if ($widgetName == 'conspectus') {
                // do nothing, there is view prepared for it.
                $widget = new \CPK\Widgets\Widget();
                $widget->setName($widgetName);
            } else {
                $widget->setContents($this->getWidgetContent($widgetName, $widget->getShownRecordsNumber()));
            }
            $widgets[][$widgetName] = $widget;
        }

        $view->widgets = $widgets;

        if (! empty($this->params()->fromPost('mylang'))) {
            $languageCode = $this->params()->fromPost('mylang');
        } else if (! empty($_COOKIE['language'])) {
            $languageCode = $_COOKIE['language'];
        } else {
            $config = $this->getConfig();
            $languageCode = $config->Site->language;
        }

        $view->language = explode("-", $languageCode)[0];

        $searchesConfig = $this->getConfig('searches');
        // If user have preferred limit and sort settings
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $userSettingsTable = $this->getTable("usersettings");

            $userSettingsTable = $this->getTable("usersettings");
            $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
            $preferredSorting = $userSettingsTable->getSorting($user);

            if ($preferredRecordsPerPage) {
                $this->layout()->limit = $preferredRecordsPerPage;
            } else {
                $this->layout()->limit = $searchesConfig->General->default_limit;
            }

            if ($preferredSorting) {
                $this->layout()->sort = $preferredSorting;
            } else {
                $this->layout()->sort = $searchesConfig->General->default_sort;
            }
        } else {
            $this->layout()->limit = $searchesConfig->General->default_limit;
            $this->layout()->sort = $searchesConfig->General->default_sort;
            
            if (! empty($this->params()->fromQuery('loggedOut')) ){
                $view->loggedOut = $this->params()->fromQuery('loggedOut');
            } else if(! empty($_GET['loggedOut'])) {
                $view->loggedOut = htmlspecialchars($_GET['loggedOut']);
            }
        }

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit;
        $_SESSION['VuFind\Search\Solr\Options']['lastSort']  = $this->layout()->sort;

	    return $view;
	}

	/**
	 * New item search form
	 *
	 * @return mixed
	 */
	public function newitemAction()
	{
	    // Search parameters set?  Process results.
	    if ($this->params()->fromQuery('range') !== null) {
	        return $this->forwardTo('Search', 'NewItemResults');
	    }

	    return $this->createViewModel(
	        [
	                        'fundList' => $this->newItems()->getFundList(),
	                        'ranges' => $this->newItems()->getRanges()
	        ]
	        );
	}

	/**
	 * New item result list
	 *
	 * @return mixed
	 */
	public function newitemresultsAction()
	{
	    // Retrieve new item list:
	    $range = $this->params()->fromQuery('range');
	    $dept = $this->params()->fromQuery('department');

	    // Validate the range parameter -- it should not exceed the greatest
	    // configured value:
	    $maxAge = $this->newItems()->getMaxAge();
	    if ($maxAge > 0 && $range > $maxAge) {
	        $range = $maxAge;
	    }

	    // Are there "new item" filter queries specified in the config file?
	    // If so, load them now; we may add more values. These will be applied
	    // later after the whole list is collected.
	    $hiddenFilters = $this->newItems()->getHiddenFilters();

	    // Depending on whether we're in ILS or Solr mode, we need to do some
	    // different processing here to retrieve the correct items:
	    if ($this->newItems()->getMethod() == 'ils') {
	        // Use standard search action with override parameter to show results:
	        $bibIDs = $this->newItems()->getBibIDsFromCatalog(
	            $this->getILS(),
	            $this->getResultsManager()->get('Solr')->getParams(),
	            $range, $dept, $this->flashMessenger()
	            );
	        $this->getRequest()->getQuery()->set('overrideIds', $bibIDs);
	    } else {
	        // Use a Solr filter to show results:
	        $hiddenFilters[] = $this->newItems()->getSolrFilter($range);
	    }

	    // If we found hidden filters above, apply them now:
	    if (!empty($hiddenFilters)) {
	        $this->getRequest()->getQuery()->set('hiddenFilters', $hiddenFilters);
	    }

	    // Don't save to history -- history page doesn't handle correctly:
	    $this->saveToHistory = false;

	    // Call rather than forward, so we can use custom template
	    $view = $this->resultsAction();

	    // Customize the URL helper to make sure it builds proper new item URLs
	    // (check it's set first -- RSS feed will return a response model rather
	    // than a view model):
	    if (isset($view->results)) {
	        $url = $view->results->getUrlQuery();
	        $url->setDefaultParameter('range', $range);
	        $url->setDefaultParameter('department', $dept);
	        $url->setSuppressQuery(true);
	    }

	    return $view;
	}

	/**
	 * Course reserves
	 *
	 * @return mixed
	 */
	public function reservesAction()
	{
	    // Search parameters set?  Process results.
	    if ($this->params()->fromQuery('inst') !== null
	        || $this->params()->fromQuery('course') !== null
	        || $this->params()->fromQuery('dept') !== null
	        ) {
	            return $this->forwardTo('Search', 'ReservesResults');
	        }

	        // No params?  Show appropriate form (varies depending on whether we're
	        // using driver-based or Solr-based reserves searching).
	        if ($this->reserves()->useIndex()) {
	            return $this->forwardTo('Search', 'ReservesSearch');
	        }

	        // If we got this far, we're using driver-based searching and need to
	        // send options to the view:
	        $catalog = $this->getILS();
	        return $this->createViewModel(
	            [
	                            'deptList' => $catalog->getDepartments(),
	                            'instList' => $catalog->getInstructors(),
	                            'courseList' =>  $catalog->getCourses()
	            ]
	            );
	}

	/**
	 * Show search form for Solr-driven reserves.
	 *
	 * @return mixed
	 */
	public function reservessearchAction()
	{
	    $request = new \Zend\Stdlib\Parameters(
	        $this->getRequest()->getQuery()->toArray()
	        + $this->getRequest()->getPost()->toArray()
	        );
	    $view = $this->createViewModel();
	    $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');
	    $view->results = $runner->run(
	        $request, 'SolrReserves', $this->getSearchSetupCallback()
	        );
	    $view->params = $view->results->getParams();
	    return $view;
	}

	/**
	 * Show results of reserves search.
	 *
	 * @return mixed
	 */
	public function reservesresultsAction()
	{
	    // Retrieve course reserves item list:
	    $course = $this->params()->fromQuery('course');
	    $inst = $this->params()->fromQuery('inst');
	    $dept = $this->params()->fromQuery('dept');
	    $result = $this->reserves()->findReserves($course, $inst, $dept);

	    // Build a list of unique IDs
	    $callback = function ($i) {
	        return $i['BIB_ID'];
	    };
	    $bibIDs = array_unique(array_map($callback, $result));

	    // Truncate the list if it is too long:
	    $limit = $this->getResultsManager()->get('Solr')->getParams()
	    ->getQueryIDLimit();
	    if (count($bibIDs) > $limit) {
	        $bibIDs = array_slice($bibIDs, 0, $limit);
	        $this->flashMessenger()->addMessage('too_many_reserves', 'info');
	    }

	    // Use standard search action with override parameter to show results:
	    $this->getRequest()->getQuery()->set('overrideIds', $bibIDs);

	    // Don't save to history -- history page doesn't handle correctly:
	    $this->saveToHistory = false;

	    // Set up RSS feed title just in case:
	    $this->getViewRenderer()->plugin('resultfeed')
	    ->setOverrideTitle('Reserves Search Results');

	    // Call rather than forward, so we can use custom template
	    $view = $this->resultsAction();

	    // Pass some key values to the view, if found:
	    if (isset($result[0]['instructor']) && !empty($result[0]['instructor'])) {
	        $view->instructor = $result[0]['instructor'];
	    }
	    if (isset($result[0]['course']) && !empty($result[0]['course'])) {
	        $view->course = $result[0]['course'];
	    }

	    // Customize the URL helper to make sure it builds proper reserves URLs
	    // (but only do this if we have access to a results object, which we
	    // won't in RSS mode):
	    if (isset($view->results)) {
	        $url = $view->results->getUrlQuery();
	        $url->setDefaultParameter('course', $course);
	        $url->setDefaultParameter('inst', $inst);
	        $url->setDefaultParameter('dept', $dept);
	        $url->setSuppressQuery(true);
	    }
	    return $view;
	}

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

	    $view = $this->createViewModel();

	    // Handle saved search requests:
	    $savedId = $this->params()->fromQuery('saved', false);
	    if ($savedId !== false) {
	        return $this->redirectToSavedSearch($savedId);
	    }

        $view->librarySearch = false;
        $searchType = $this->params()->fromQuery('type0')[0];
        if ($searchType == 'Libraries') {
            $view->librarySearch = true;
            $view->apikey = (isset($this->getConfig()->GoogleMaps->apikey) && ! empty($this->getConfig()->GoogleMaps->apikey)) ? $this->getConfig()->GoogleMaps->apikey : null;
            $view->query = $this->params()->fromQuery('lookfor0')[0];
        }

        // Send both GET and POST variables to search class:
	    $request = $this->getRequest()->getQuery()->toArray()
	    + $this->getRequest()->getPost()->toArray();
	    
	    // EDS can't search with emtpy lookfor string,
	    /* so we set it to 'FT Y OR FT N' for all results
	    if($request['database'] == 'EDS'){
		    if (empty($request['lookfor0'][0])){
			$request['lookfor0'][0]='FT Y OR FT N';		
		    }
	    }*/

	    if (! empty($request['filter'])) {
	        $decompressedFilters = \LZCompressor\LZString::decompressFromBase64(specialUrlDecode($request['filter']));
	        $request['filter'] = explode("|", $decompressedFilters);
	    }

	    /* Set limit and sort */
	    $searchesConfig = $this->getConfig('searches');
	    if (! empty($request['limit'])) {
	       $view->limit = $request['limit'];
	    } else {
	       $view->limit = $searchesConfig->General->default_limit;
	       $request['limit'] = $searchesConfig->General->default_limit;
	    }


	    if (! empty($request['sort'])) {
	       $view->sort = $request['sort'];
	    } else {
	       $view->sort = $searchesConfig->General->default_sort;
	       $request['sort'] = $searchesConfig->General->default_sort;
	    }

        $edsConfig = $this->getConfig('EDS');
        $edsDefaultLimit = $edsConfig['General']['default_limit'];
        $solrDefaultLimit = $searchesConfig['General']['default_limit'];
        $view->edsDefaultLimit = $edsDefaultLimit;
        $view->solrDefaultLimit = $solrDefaultLimit;

	    if (! empty($request['limit'])) {
	        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $request['limit'];
	    }

	    if (! empty($request['sort'])) {
	        $_SESSION['VuFind\Search\Solr\Options']['lastSort'] = $request['sort'];
	    }

	    // If user have preferred limit and sort settings
	    if ($user = $this->getAuthManager()->isLoggedIn()) {
	        $userSettingsTable = $this->getTable("usersettings");

	        if (isset($_SESSION['VuFind\Search\Solr\Options']['lastLimit'])) {
	            $request['limit'] = $_SESSION['VuFind\Search\Solr\Options']['lastLimit'];
	        } else {
	            if (! empty($preferredRecordsPerPage)) {
	                $request['limit'] = $userSettingsTable->getRecordsPerPage($user);
	            } else {
	                $request['limit'] = $searchesConfig->General->default_limit;
	            }
	        }
	        $view->limit = $request['limit'];

	        if (isset($_SESSION['VuFind\Search\Solr\Options']['lastSort'])) {
	            $request['sort'] = $_SESSION['VuFind\Search\Solr\Options']['lastSort'];
	        } else {
	            if (! empty($preferredSorting)) {
	                $request['sort'] = $userSettingsTable->getSorting($user);
	            } else {
	                $request['sort'] = $searchesConfig->General->default_sort;
	            }
	        }
	        $view->sort = $request['sort'];
	    }

	    $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit = $view->limit;
	    $_SESSION['VuFind\Search\Solr\Options']['lastSort']  = $this->layout()->sort = $view->sort;
	    /**/

	    $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

	    $database = ! empty($request['database']) ? $request['database'] : $this->searchClassId;

	    $view->results = $results = $runner->run(
	        $request, $database, $this->getSearchSetupCallback()
	        );
	    $view->params = $results->getParams();

	    // If we received an EmptySet back, that indicates that the real search
	    // failed due to some kind of syntax error, and we should display a
	    // warning to the user; otherwise, we should proceed with normal post-search
	    // processing.
	    if ($results instanceof \VuFind\Search\EmptySet\Results) {
	        $view->parseError = true;
	    } else {
	        // If a "jumpto" parameter is set, deal with that now:
	        if ($jump = $this->processJumpTo($results)) {
	            return $jump;
	        }

	        // Remember the current URL as the last search.
	        $this->rememberSearch($results);

	        // Add to search history:
	        if ($this->saveToHistory) {
	            $user = $this->getUser();
	            $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')
	            ->getId();
	            $history = $this->getTable('Search');
	            $history->saveSearch(
	                $this->getResultsManager(), $results, $sessId,
	                $history->getSearches(
	                    $sessId, isset($user->id) ? $user->id : null
	                    )
	                );
	        }

	        // Set up results scroller:
	        if ($this->resultScrollerActive()) {
	            $this->resultScroller()->init($results);
	        }
	    }

	    // Save statistics:
	    if ($this->logStatistics) {
	        $this->getServiceLocator()->get('VuFind\SearchStats')
	        ->log($results, $this->getRequest());
	    }

	    // Special case: If we're in RSS view, we need to render differently:
	    if (isset($view->params) && $view->params->getView() == 'rss') {
	        $response = $this->getResponse();
	        $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');
	        $feed = $this->getViewRenderer()->plugin('resultfeed');
	        $response->setContent($feed($view->results)->export('rss'));
	        return $response;
	    }

	    // Search toolbar
	    $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
	    $view->showBulkOptions = isset($config->Site->showBulkOptions)
	    && $config->Site->showBulkOptions;

	    $view->myLibs = $this->getUsersHomeLibraries();
	    $view->config = $this->getConfig();
	    if (! isset($view->saved)) $view->saved = null;

		// Set up facet information:
		$view->facetList = $this->processAdvancedFacets(
			$this->getAdvancedFacets()->getFacetList(), $view->saved
		);

	    $facetConfig = $this->getConfig('facets');
	    $institutionsMappings = $facetConfig->InstitutionsMappings->toArray();
	    $view->institutionsMappings = $institutionsMappings;

	    /* Handle view template */
	    if (! empty($this->params()->fromQuery('searchTypeTemplate')) ){
	        $view->searchTypeTemplate = $this->params()->fromQuery('searchTypeTemplate');
	    } else {
	        $view->searchTypeTemplate = 'basic';
	    }

	    $view->searchResults = true;

	    $referer = $this->base64url_encode(
	        'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' .
	        "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
	    );

	    $view->referer = $referer;

	    /* Get more results for swithing to next/previous record of search results */
	    $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

	    $extraRequest = $this->getRequest()->getQuery()->toArray()
	    + $this->getRequest()->getPost()->toArray();

	    $searchesConfig = $this->getConfig('searches');
	    $extraRequest['limit'] = $searchesConfig->General->records_switching_limit;
	    $extraRequest['page'] = 1;
        $database = ! empty($request['database']) ? $request['database'] : $this->searchClassId;
	    $extraResultsForSwitching = $runner->run(
	        $extraRequest, $database, $this->getSearchSetupCallback()
	    );
	    $extraResults = [];
	    foreach($extraResultsForSwitching->getResults() as $record) {
	        $extraResults[] = $record->getUniqueId();
	    }
	    $view->extraResults = $extraResults;
	    $view->referer = $referer;
	    /**/

	    $user = $this->getAuthManager()->isLoggedIn();

	    $view->isLoggedIn = $user;
	    $view->offlineFavoritesEnabled = false;

	    if ($this->getConfig()->Site['offlineFavoritesEnabled'] !== null) {
	        $view->offlineFavoritesEnabled = (bool) $this->getConfig()->Site['offlineFavoritesEnabled'];
	    }

	    $facetConfig = $this->getConfig('facets');
        $view->sfxesForLibraries = isset($facetConfig->SFXesForLibraries)
    	    ? $facetConfig->SFXesForLibraries->toArray()
    	    : [];

	    $view->digitalLibrarieForLibraries = isset($facetConfig->DigitalLibrarieForLibraries)
    	    ? $facetConfig->DigitalLibrarieForLibraries->toArray()
    	    : [];

	    return $view;
	}

	/**
	 * Harvest 100 most wanted records from SOLR.
	 * This method serves only for harvesting.
	 *
	 * @return array
	 */
	public function harvestMostWantedRecordsAction()
	{
        $mainParams = [];
        $mainParams['limit'] = '100';
        $mainParams['join'] = 'AND';
        $mainParams['bool0'] = [];
        $mainParams['bool0'][] = 'AND';
        $mainParams['type0'] = [];
        $mainParams['type0'][] = 'AllFields';
        $mainParams['lookfor0'] = [];
        $mainParams['lookfor0'][] = '';
        $mainParams['filter'] = [];
        $mainParams['filter'][] = 'inspiration:"top_results"';

        $request = $this->getRequest()->getQuery()->toArray()
        + $mainParams;

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        $records = $runner->run(
            $request, $this->searchClassId, $this->getSearchSetupCallback()
        );

	    return $records->getResults();
	}

	/**
	 * Harvest 100 favorite authors from SOLR.
	 * This method serves only for harvesting.
	 *
	 * @return array
	 */
	public function harvestFavoriteAuthorsAction()
	{
	    $mainParams = [];
	    $mainParams['limit'] = '100';
	    $mainParams['join'] = 'AND';
	    $mainParams['bool0'] = [];
	    $mainParams['bool0'][] = 'AND';
	    $mainParams['type0'] = [];
	    $mainParams['type0'][] = 'AllFields';
	    $mainParams['lookfor0'] = [];
	    $mainParams['lookfor0'][] = '';
	    $mainParams['filter'] = [];
	    $mainParams['filter'][] = 'inspiration:"favorite_authors"';

	    $request = $this->getRequest()->getQuery()->toArray()
	    + $mainParams;

	    $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

	    $records = $runner->run(
	        $request, $this->searchClassId, $this->getSearchSetupCallback()
	        );

	    return $records->getResults();
	}

	/**
	 * Harvest actual count of all records and save it into db.
	 *
	 * @return void
	 */
	public function harvestDocCountAction()
	{
	    $results = $this->getResultsManager()->get('Solr');
	    $params = $results->getParams();
	    $params->setBasicSearch("");
	    $params->setLimit(0);
	    $results->getResults();

	    $update = new \Zend\Db\Sql\Update('system');
	    $condition = "`key`='DOC_COUNT'";
	    $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
	    $update->where($predicate);
	    $gw = new \VuFind\Db\Table\Gateway('system');
	    $gw->setAdapter($this->getServiceLocator()->get('VuFind\DbAdapter'));
	    $gw->initialize();
	    $gw->update(array('value' => $results->getResultTotal()), $condition);
	    exit();
	}

	/**
	 * Retrieves the record from SOLR by availability_id_str_mv field
	 *
	 * @return array
	 */
	public function getRecordByAvailabilityIdStrMvAction($sourcedZ30docNumber)
	{
	    list ($source, $z30docnumber) = explode('.', $sourcedZ30docNumber);

        $mainParams = [];
        $mainParams['limit'] = '1';
        $mainParams['join'] = 'AND';
        $mainParams['bool0'] = [];
        $mainParams['bool0'][] = 'AND';
        $mainParams['type0'] = [];
        $mainParams['type0'][] = 'availability_id_str_mv:"' . $z30docnumber . '"';
        $mainParams['lookfor0'] = [];
        $mainParams['lookfor0'][] = 'local_ids_str_mv:"' . $source . '*"';

        $request = $this->getRequest()->getQuery()->toArray()
        + $mainParams;

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        $records = $runner->run(
            $request, $this->searchClassId, $this->getSearchSetupCallback()
        );

	    return $records->getResults();
	}

	/**
	 * Return a Search Results object containing requested facet information.  This
	 * data may come from the cache.
	 *
	 * @param string $initMethod Name of params method to use to request facets
	 * @param string $cacheName  Cache key for facet data
	 *
	 * @return \VuFind\Search\Solr\Results
	 */
	protected function getFacetResults($initMethod, $cacheName)
	{
	    // Check if we have facet results cached, and build them if we don't.
	    $cache = $this->getServiceLocator()->get('VuFind\CacheManager')
	    ->getCache('object');
	    if (!($results = $cache->getItem($cacheName))) {
	        // Use advanced facet settings to get summary facets on the front page;
	        // we may want to make this more flexible later.  Also keep in mind that
	        // the template is currently looking for certain hard-coded fields; this
	        // should also be made smarter.
	        $results = $this->getResultsManager()->get('Solr');
	        $params = $results->getParams();
	        $params->$initMethod();

	        // We only care about facet lists, so don't get any results (this helps
	        // prevent problems with serialized File_MARC objects in the cache):
	        $params->setLimit(0);

	        $results->getResults();                     // force processing for cache

	        $cache->setItem($cacheName, $results);
	    }

	    // Restore the real service locator to the object (it was lost during
	    // serialization):
	    $results->restoreServiceLocator($this->getServiceLocator());
	    return $results;
	}

	/**
	 * Return a Search Results object containing advanced facet information.  This
	 * data may come from the cache.
	 *
	 * @return \VuFind\Search\Solr\Results
	 */
	protected function getAdvancedFacets()
	{
	    return $this->getFacetResults(
	        'initAdvancedFacets', 'solrSearchAdvancedFacets'
	        );
	}

	/**
	 * Return a Search Results object containing homepage facet information.  This
	 * data may come from the cache.
	 *
	 * @return \VuFind\Search\Solr\Results
	 */
	protected function getHomePageFacets()
	{
	    return $this->getFacetResults('initHomePageFacets', 'solrSearchHomeFacets');
	}

	/**
	 * Handle OpenSearch.
	 *
	 * @return \Zend\Http\Response
	 */
	public function opensearchAction()
	{
	    switch ($this->params()->fromQuery('method')) {
	        case 'describe':
	            $config = $this->getConfig();
	            $xml = $this->getViewRenderer()->render(
	                'search/opensearch-describe.phtml', ['site' => $config->Site]
	                );
	            break;
	        default:
	            $xml = $this->getViewRenderer()->render('search/opensearch-error.phtml');
	            break;
	    }

	    $response = $this->getResponse();
	    $headers = $response->getHeaders();
	    $headers->addHeaderLine('Content-type', 'text/xml');
	    $response->setContent($xml);
	    return $response;
	}

	/**
	 * Provide OpenSearch suggestions as specified at
	 * http://www.opensearch.org/Specifications/OpenSearch/Extensions/Suggestions/1.0
	 *
	 * @return \Zend\Http\Response
	 */
	public function suggestAction()
	{
	    // Always use 'AllFields' as our autosuggest type:
	    $query = $this->getRequest()->getQuery();
	    $query->set('type', 'AllFields');

	    // Get suggestions and make sure they are an array (we don't want to JSON
	    // encode them into an object):
	    $autocompleteManager = $this->getServiceLocator()
	    ->get('VuFind\AutocompletePluginManager');
	    $suggestions = $autocompleteManager->getSuggestions(
	        $query, 'type', 'lookfor'
	        );

	    // Send the JSON response:
	    $response = $this->getResponse();
	    $headers = $response->getHeaders();
	    $headers->addHeaderLine('Content-type', 'application/javascript');
	    $response->setContent(
	        json_encode([$query->get('lookfor', ''), $suggestions])
	        );
	    return $response;
	}

	/**
	 * Is the result scroller active?
	 *
	 * @return bool
	 */
	protected function resultScrollerActive()
	{
	    $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
	    return (isset($config->Record->next_prev_navigation)
	        && $config->Record->next_prev_navigation);
	}

	/**
	 * Get an array of hierarchical facets
	 *
	 * @return array Facets
	 */
	protected function getHierarchicalFacets()
	{
	    $facetConfig = $this->getConfig('facets');
	    return isset($facetConfig->SpecialFacets->hierarchical)
	    ? $facetConfig->SpecialFacets->hierarchical->toArray()
	    : [];
	}

	/**
	 * Get hierarchical facet sort settings
	 *
	 * @return array Array of sort settings keyed by facet
	 */
	protected function getHierarchicalFacetSortSettings()
	{
	    $facetConfig = $this->getConfig('facets');
	    return isset($facetConfig->SpecialFacets->hierarchicalFacetSortOptions)
	    ? $facetConfig->SpecialFacets->hierarchicalFacetSortOptions->toArray()
	    : [];
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

    /**
     * Results action
     *
     * @param array $postParams
     *
     * @return array
     */
    public function ajaxResultsAction(array $postParams)
    {
        $viewData = [];
        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
        + $this->getRequest()->getPost()->toArray()
        + $postParams;

        if (! empty($request['filter'])) {
            $decompressedFilters = \LZCompressor\LZString::decompressFromBase64(specialUrlDecode($request['filter']));
            $request['filter'] = explode("|", $decompressedFilters);
        }

        /* Prepare referer */
        $viewData['referer'] = $this->base64url_encode($postParams['searchResultsUrl']);

        $viewData['librarySearch'] = false;
        $searchType = $request['type0'][0];
        if ($searchType == 'Libraries') {
            $viewData['librarySearch']  = true;
            $viewData['apikey'] = (isset($this->getConfig()->GoogleMaps->apikey) && ! empty($this->getConfig()->GoogleMaps->apikey)) ? $this->getConfig()->GoogleMaps->apikey : null;
            $viewData['query'] = $this->params()->fromQuery('lookfor0')[0];
        }

        /* Set limit and sort */
        $searchesConfig = $this->getConfig('searches');
        $viewData['limit'] = (! empty($request['limit']))
            ? $request['limit']
            : $searchesConfig->General->default_limit;
        $viewData['sort']  = (! empty($request['sort']))
            ? $request['sort']
            : $searchesConfig->General->default_sort;

        if (! empty($request['limit'])) {
            $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $request['limit'];
        }

        if (! empty($request['sort'])) {
            $_SESSION['VuFind\Search\Solr\Options']['lastSort'] = $request['sort'];
        }

        // If user have preferred limit and sort settings
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $userSettingsTable = $this->getTable("usersettings");

            if (isset($_SESSION['VuFind\Search\Solr\Options']['lastLimit'])) {
                $request['limit'] = $_SESSION['VuFind\Search\Solr\Options']['lastLimit'];
            } else {
                if (! empty($preferredRecordsPerPage)) {
                    $request['limit'] = $userSettingsTable->getRecordsPerPage($user);
                } else {
                    $request['limit'] = $searchesConfig->General->default_limit;
                }
            }
            $viewData['limit'] = $request['limit'];

            if (isset($_SESSION['VuFind\Search\Solr\Options']['lastSort'])) {
                $request['sort'] = $_SESSION['VuFind\Search\Solr\Options']['lastSort'];
            } else {
                if (! empty($preferredSorting)) {
                    $request['sort'] = $userSettingsTable->getSorting($user);
                } else {
                    $request['sort'] = $searchesConfig->General->default_sort;
                }
            }
            $viewData['sort'] = $request['sort'];
        }

        $edsConfig = $this->getConfig('EDS');
        $edsLimits = explode(",", $edsConfig['General']['limit_options']);
        $edsMaxLimit = max($edsLimits);
        $edsDefaultSorts = $this->getSortOptions($edsConfig['Sorting']);
        $edsDefaultLimit = $edsConfig['General']['default_limit'];

        $solrLimits = explode(",", $searchesConfig['General']['limit_options']);
        $solrMaxLimit = max($solrLimits);
        $solrDefaultSorts = $this->getSortOptions($searchesConfig['Sorting']);
        $solrDefaultLimit = $searchesConfig['General']['default_limit'];

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit = $viewData['limit'];
        $_SESSION['VuFind\Search\Solr\Options']['lastSort']  = $this->layout()->sort = $viewData['sort'];
        /**/

        $database = ! empty($request['database']) ? $request['database'] : $this->searchClassId;
        $viewData['results'] = $results = $runner->run(
            $request, $database, $this->getSearchSetupCallback()
        );
        $viewData['params'] = $results->getParams();

        /* Get more results for swithing to next/previous record of search results */
        $extraRequest = $request;
        $extraRequest['limit'] = $searchesConfig->General->records_switching_limit;
        $extraRequest['page'] = 1;
        $extraResultsForSwitching = $runner->run(
            $extraRequest, $this->searchClassId, $this->getSearchSetupCallback()
        );
        $extraResults = [];
        foreach($extraResultsForSwitching->getResults() as $record) {
            $extraResults[] = $record->getUniqueId();
        }
        $viewData['extraResults'] = $extraResults;

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $viewData['parseError'] = true;
        } else {
            // If a "jumpto" parameter is set, deal with that now:
            if ($jump = $this->processJumpTo($results)) {
                return $jump;
            }

            // Remember the current URL as the last search.
            try {
                $this->rememberSearch($results);
            } catch (\Exception $e) {
                // ignore this Zend\Mvc\Exception\DomainException exception
            }

            // Add to search history:
            $user = $this->getUser();
            $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')
            ->getId();
            $history = $this->getTable('Search');
            $history->saveSearch(
                $this->getResultsManager(), $results, $sessId,
                $history->getSearches(
                    $sessId, isset($user->id) ? $user->id : null
                    )
                );

            $searchId = $history->getLastInsertValue();


            // Set up results scroller:
            if ($this->resultScrollerActive()) {
                $this->resultScroller()->init($results);
            }
        }

        // Search toolbar
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $viewData['showBulkOptions'] = isset($config->Site->showBulkOptions)
        && $config->Site->showBulkOptions;

	    $viewData['myLibs'] = $this->getUsersHomeLibraries();
	    $viewData['config'] = $this->getConfig();
	    $viewData['facetConfig'] = $this->getConfig('facets');

	    //
	    $facetConfig = $this->getConfig('facets');
	    $viewData['sfxesForLibraries'] = isset($facetConfig->SFXesForLibraries)
    	    ? $facetConfig->SFXesForLibraries->toArray()
    	    : [];

	    $viewData['digitalLibrarieForLibraries'] = isset($facetConfig->DigitalLibrarieForLibraries)
    	    ? $facetConfig->DigitalLibrarieForLibraries->toArray()
    	    : [];

	    //
	    $user = $this->getAuthManager()->isLoggedIn();

	    $viewData['isLoggedIn'] = $user;
	    $viewData['offlineFavoritesEnabled'] = false;

	    if ($this->getConfig()->Site['offlineFavoritesEnabled'] !== null) {
	        $viewData['offlineFavoritesEnabled'] = (bool) $this->getConfig()->Site['offlineFavoritesEnabled'];
	    }

		// Set up facet information:
		$viewData['facetList'] = $this->processAdvancedFacets(
			$this->getAdvancedFacets()->getFacetList(), false
		);

	    $facetConfig = $this->getConfig('facets');
	    $institutionsMappings = $facetConfig->InstitutionsMappings->toArray();
	    $viewData['institutionsMappings'] = $institutionsMappings;

	    $resultsHtml = $this->getResultListHtml($viewData);
	    $sanitizedResultsHtml = htmlentities($resultsHtml, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");

	    $paginationHtml = $this->getPaginationHtml($viewData);

	    $resultsAmountInfoHtml = $this->getResultsAmountInfoHtml($viewData);

	    $sideFacets = $this->getSideFacetsHtml($viewData);

	    $recordTotal = $viewData['results']->getResultTotal();

        $lookfor = $results->getUrlQuery()->isQuerySuppressed() ? '' : $results->getParams()->getDisplayQuery();
        $viewRender = $this->getServiceLocator()->get('ViewRenderer');
        $title = $viewRender->translate('Search Results') . (empty($lookfor) ? '' : " - {$lookfor}");

	    $data = [
            'title' => $title,
            'viewData' => $viewData,
	        'resultsHtml' => json_encode(['html' => $sanitizedResultsHtml]),
            'paginationHtml' => json_encode(['html' => $paginationHtml]),
            'resultsAmountInfoHtml' => json_encode(['html' => $resultsAmountInfoHtml]),
            'searchId' => $searchId,
	        'sideFacets' => json_encode(['html' => $sideFacets]),
	        'recordTotal' => $recordTotal,
            'edsLimits' => json_encode(['data' => $edsLimits]),
            'edsMaxLimit' => json_encode(['data' => $edsMaxLimit]),
            'edsDefaultSorts' => json_encode(['data' => $edsDefaultSorts]),
            'solrLimits' => json_encode(['data' => $solrLimits]),
            'solrMaxLimit' => json_encode(['data' => $solrMaxLimit]),
            'solrDefaultSorts' => json_encode(['data' => $solrDefaultSorts])
	    ];

	    return $data;
    }

    /**
     * Return a list of urls for sorting, along with which option
     *    should be currently selected.
     *
     * @return array Sort urls, descriptions and selected flags
     */
    public function getSortOptions($config)
    {
        $renderer = $this->getViewRenderer();
        $list = [];
        foreach ($config as $sort => $desc) {
            $list[] = [
                'key' => $sort,
                'value' => $renderer->translate($desc)
            ];
        }
        return $list;
    }

    /**
     * Get search results list
     *
     * @param array $viewData
     *
     * @return string
     */
    public function getResultListHtml(array $viewData)
    {
        $viewModel = $this->createViewModel();
        $viewModel->setTemplate('search/list-list');

        foreach($viewData as $key => $data) {
            $viewModel->$key = $data;
        }

        $viewRender = $this->getServiceLocator()->get('ViewRenderer');
        $html = $viewRender->render($viewModel);
        return $html;
    }

    /**
     * Get pagination
     *
     * @param array $viewData
     *
     * @return string
     */
    public function getPaginationHtml(array $viewData)
    {
        $viewModel = $this->createViewModel();
        $viewModel->setTemplate('search/ajax/pagination');

        foreach($viewData as $key => $data) {
            $viewModel->$key = $data;
        }

        $viewRender = $this->getServiceLocator()->get('ViewRenderer');
        $html = $viewRender->render($viewModel);
        return $html;
    }

    /**
     * Get results amount info
     *
     * @param array $viewData
     *
     * @return string
     */
    public function getResultsAmountInfoHtml(array $viewData)
    {
        $viewModel = $this->createViewModel();
        $viewModel->setTemplate('search/ajax/resultsAmountInfo');

        foreach($viewData as $key => $data) {
            $viewModel->$key = $data;
        }

        $viewRender = $this->getServiceLocator()->get('ViewRenderer');
        $html = $viewRender->render($viewModel);
        return $html;
    }

    /**
     * Get side facets
     *
     * @param array $viewData
     *
     * @return string
     */
    public function getSideFacetsHtml(array $viewData)
    {
        $viewModel = $this->createViewModel();
        $viewModel->setTemplate('search/ajax/facets');

        foreach($viewData as $key => $data) {
            $viewModel->$key = $data;
        }

        $viewRender = $this->getServiceLocator()->get('ViewRenderer');
        $html = $viewRender->render($viewModel);
        return $html;
    }

    protected function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Inspiration action
     *
     * @return mixed
     */
    public function inspirationAction()
    {
        $view = $this->createViewModel();

        $widgetTable = $this->getTable('widget');

        $sortedInspirationsWidgets = $widgetTable->getWidgets(true, true, true);
        $lastInspirationsWidgetsPosition = $widgetTable->getLastInspirationsWidgetsPosition();

        foreach ($sortedInspirationsWidgets as $inspirationsWidget) {
            if ($inspirationsWidget->getName() == 'infobox') {
                $infoboxTable = $this->getTable("infobox");
                $infoboxItems = $infoboxTable->getActualItems($inspirationsWidget->getShownRecordsNumber());
                $inspirationsWidget->setContents($infoboxItems);
            } else {
                $inspirationsWidget->setContents($this->getWidgetContent($inspirationsWidget->getName(), $inspirationsWidget->getShownRecordsNumber()));
            }
        }

        $view->lastInspirationsWidgetsPosition = $lastInspirationsWidgetsPosition;
        $view->sortedInspirationsWidgets = $sortedInspirationsWidgets;

        if (! empty($this->params()->fromPost('mylang'))) {
            $languageCode = $this->params()->fromPost('mylang');
        } else if (! empty($_COOKIE['language'])) {
            $languageCode = $_COOKIE['language'];
        } else {
            $config = $this->getConfig();
            $languageCode = $config->Site->language;
        }

        $view->language = explode("-", $languageCode)[0];

        /* Conspectus */
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->addFacet($this->conspectusField, $this->conspectusFieldName);
        $params->setLimit(0);
        $params->setFacetLimit(10000);
        $results->getResults();
        $facets = $results->getFacetList();

        $conspectus = [];
        foreach ($facets[$this->conspectusField]['list'] as $facet) {
            $explodedFacet = explode("/", $facet['value']);
            $category = $explodedFacet[1];
            $subCategory = $explodedFacet[2];

            if (array_key_exists($category, $conspectus)  && ! empty($category)) {
                if (! array_key_exists($subCategory, $conspectus[$category]) && ! empty($subCategory)) {
                    $conspectus[$category][] = $subCategory;
                }
            } else {
                $conspectus[$category] = [];
                $conspectus[$category][] = $subCategory;
            }
        }

        $view->conspectus = $conspectus;

        $searchesConfig = $this->getConfig('searches');
        // If user have preferred limit and sort settings
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $userSettingsTable = $this->getTable("usersettings");
            $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
            $preferredSorting = $userSettingsTable->getSorting($user);

            if ($preferredRecordsPerPage) {
                $this->layout()->limit = $preferredRecordsPerPage;
            } else {
                $this->layout()->limit = $searchesConfig->General->default_limit;
            }

            if ($preferredSorting) {
                $this->layout()->sort = $preferredSorting;
            } else {
                $this->layout()->sort = $searchesConfig->General->default_sort;
            }
        } else {
            $this->layout()->limit = $searchesConfig->General->default_limit;
            $this->layout()->sort = $searchesConfig->General->default_sort;
        }

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit;
        $_SESSION['VuFind\Search\Solr\Options']['lastSort']  = $this->layout()->sort;


        return $view;
    }

    /* Get content for widget
     *
     * @param string $widgetName
     * @param int    $limit
     *
     * @return  CPK\Widgets\WidgetContent
     * */
    protected function getWidgetContent($widgetName, $limit) {
        $widgetContentTable = $this->getTable("widgetcontent");
        $widgetContens = $widgetContentTable->getContentsByName($widgetName, $limit);

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        foreach ($widgetContens as $key => $record) {
            try {
                $widgetContens[$key]->setRecordDriver($recordLoader->load($record->getValue()));
            } catch (RecordMissingException $e) {
                // ignore this widget content item and just don't show not found record
                unset($widgetContens[$key]);
            }
        }

        return $widgetContens;
    }

    public function getSearchSetupCallback() {
        return parent::getSearchSetupCallback();
    }

    /**
     * Given a saved search ID, get search keywords.
     *
     * @param int $id ID from search history
     *
     * @return array
     */
    public function getSearchTermsFromSearch($searchId)
    {
        $table = $this->getTable('Search');
        $search = $table->getRowById($searchId);

        // Found, make sure the user has the rights to view this search
        $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')->getId();
        $user = $this->getUser();
        $userId = $user ? $user->id : false;
        if ($search->session_id == $sessId || $search->user_id === $userId) {
            // They do, deminify it to a new object.
            $minSO = $search->getSearchObject();
            $savedSearch = $minSO->deminify($this->getResultsManager());

            $searchTerms = [];
            foreach($savedSearch->searchTerms as $searchGroup) {
                foreach($searchGroup['g'] as $searchQuery) {
                    $searchTerms[] = $searchQuery['l'];
                }
            }

            return $searchTerms;
        } else {
            // They don't
            // TODO : Error handling -
            //    User is trying to view a saved search from another session
            //    (deliberate or expired) or associated with another user.
            throw new \Exception("Attempt to access invalid search ID");
        }
    }
}
