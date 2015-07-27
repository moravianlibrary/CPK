<?php
namespace CPK\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'invokables' => array(
                    'dummy' => 'CPK\ILS\Driver\Dummy',
                ), /* invokables */
            ), /* ils_drivers */
            'recorddriver' => array (
                'factories' => array(
                    'solrmarc'     => 'CPK\RecordDriver\Factory::getSolrMarc',
                    'solrcpk_mzk'  => 'CPK\RecordDriver\Factory::getSolrMarcMZK',
                    'solrcpk_vkol' => 'CPK\RecordDriver\Factory::getSolrMarcVKOL',
                    'solrcpk_nlk'  => 'CPK\RecordDriver\Factory::getSolrMarcNLK',
                    'solrlocal'    => 'CPK\RecordDriver\Factory::getSolrMarcLocal',
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'invokables' => array(
                    'userCommentsObalkyKnih' => 'CPK\RecordTab\UserCommentsObalkyKnih',
                    'eVersion' => 'CPK\RecordTab\EVersion',
                    'buy' => 'CPK\RecordTab\Buy',
                ), /* invokables */
            ), /* recordtab */
            'auth' => array(
                'factories' => array(
                    'perunShibboleth' => 'CPK\Auth\Factory::getPerunShibboleth',
                    'shibbolethIdentityManager' => 'CPK\Auth\Factory::getShibbolethIdentityManager',
                ), /* factories */
            ), /* auth */
            'db_table' => [
                'factories' => [
                    'user' => 'CPK\Db\Table\Factory::getUser',
                ], /* factories */
                'invokables' => [
                    'session' => 'VuFind\Db\Table\Session',
                ],
            ], /* db_table */
        ), /* plugin_managers */

        // This section controls which tabs are used for which record driver classes.
        // Each sub-array is a map from a tab name (as used in a record URL) to a tab
        // service (found in recordtab_plugin_manager, below).  If a particular record
        // driver is not defined here, it will inherit configuration from a configured
        // parent class.  The defaultTab setting may be used to specify the default
        // active tab; if null, the value from the relevant .ini file will be used.
        'recorddriver_tabs' => [
            'CPK\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS',
                    'EVersion' => 'EVersion',
                    'Buy' => 'Buy',
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'UserCommentsObalkyKnih' => 'UserCommentsObalkyKnih',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                    'DedupedRecords' => 'DedupedRecords',
                ],
                'defaultTab' => null,
            ],
        ],
    ), /* vufind */
    'controllers' => array(
    	'factories' => array(
    		'record' => 'CPK\Controller\Factory::getRecordController',
    	),
        'invokables' => array(
            'my-research' => 'CPK\Controller\MyResearchController',
            'librarycards' => 'CPK\Controller\LibraryCardsController',
            'search' => 'CPK\Controller\SearchController',
        ), /* invokables */
    ), /* controllers */
    'service_manager' => array(
        'factories' => array(
            'VuFind\AuthManager' => 'CPK\Auth\Factory::getAuthManager',
            'Perun\IdentityResolver' => 'CPK\Perun\Factory::getIdentityResolver',
        	'WantIt\BuyChoiceHandler' => 'CPK\WantIt\Factory::getBuyChoiceHandler',
        ),
    ),
	'view_manager' => array(
	    'strategies' => array(
	        'ViewJsonStrategy',
	    ),
	),
);

$staticRoutes = array(
    'Statistics/Dashboard', 'Statistics/Visits', 'Statistics/Circulations',
    'Statistics/Payments', 'Statistics/Searches', 'Statistics', 'Statistics/',
    'MyResearch/UserConnect', 'Record/getMarc996ArrayViaAjax', 'Record/getAntikvariatyLinkViaAjax'
);

foreach ($staticRoutes as $route) {
	list($controller, $action) = explode('/', $route);
	$routeName = str_replace('/', '-', strtolower($route));
	$config['router']['routes'][$routeName] = array(
		'type' => 'Zend\Mvc\Router\Http\Literal',
		'options' => array(
			'route' => '/' . $route,
			'defaults' => array(
				'controller' => $controller,
				'action' => (! empty($action)) ? $action : 'default',
				)
			)
	);
}

return $config;
