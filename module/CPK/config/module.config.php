<?php
namespace CPK\Module\Configuration;

$config = array(
    'router' => [
        'routes' => [
            'default' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/[:controller[/[:action[/[:subaction[/[:param[/[:param2]]]]]]]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'subaction'  => '[a-zA-Z][a-zA-Z0-9_-]*'
                     ],
                    'defaults' => [
                        'controller' => 'index',
                        'action'     => 'Home',
                        'subaction'  => 'default',
                        'param'      => 'default',
                        'param2'     => 'default'
                    ],
                ],
            ],
        ],
    ],
    'vufind' => array(
        'plugin_managers' => array(
            'recorddriver' => array(
                'factories' => array(
                    'solrmarc' => 'CPK\RecordDriver\Factory::getSolrMarc',
                    'solrcpk_mzk' => 'CPK\RecordDriver\Factory::getSolrMarcMZK',
                    'solrcpk_vkol' => 'CPK\RecordDriver\Factory::getSolrMarcVKOL',
                    'solrcpk_nlk' => 'CPK\RecordDriver\Factory::getSolrMarcNLK',
                    'solrlocal' => 'CPK\RecordDriver\Factory::getSolrMarcLocal',
                    'solrdublincore' => 'CPK\RecordDriver\Factory::getSolrDublinCore'
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'invokables' => array(
                    'userCommentsObalkyKnih' => 'CPK\RecordTab\UserCommentsObalkyKnih',
                    'eVersion' => 'CPK\RecordTab\EVersion',
                    'buy' => 'CPK\RecordTab\Buy',
                    'StaffViewDublinCore' => 'CPK\RecordTab\StaffViewDublinCore'
                ), /* invokables */
            ), /* recordtab */
            'recommend' => [
                'factories' => [
                    'sidefacets' => 'CPK\Recommend\Factory::getSideFacets'
                ], /* factories */
            ], /* recommend */
            'auth' => array(
                'factories' => array(
                    'perunShibboleth' => 'CPK\Auth\Factory::getPerunShibboleth',
                    'shibbolethIdentityManager' => 'CPK\Auth\Factory::getShibbolethIdentityManager'
                ), /* factories */
            ), /* auth */
            'db_table' => [
                'factories' => [
                    'user' => 'CPK\Db\Table\Factory::getUser',
                    'citationstyle' => 'CPK\Db\Table\Factory::getCitationStyle',
                    'usersettings' => 'CPK\Db\Table\Factory::getUserSettings',
                    'portalpages' => 'CPK\Db\Table\Factory::getPortalPages'
                ], /* factories */
                'invokables' => [
                    'session' => 'VuFind\Db\Table\Session'
                ]
            ], /* db_table */
            'ils_driver' => [
                'invokables' => [
                    'dummy' => 'CPK\ILS\Driver\Dummy',
                    'xcncip2' => 'CPK\ILS\Driver\XCNCIP2'
                ],
                'factories' => array(
                    'multibackend' => 'CPK\ILS\Driver\Factory::getMultiBackend',
                    'aleph' => 'CPK\ILS\Driver\Factory::getAleph'
                ), /* factories */
            ], /* ils_driver */
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
                    'TOC' => 'TOC',
                    'UserCommentsObalkyKnih' => 'UserCommentsObalkyKnih',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                    'DedupedRecords' => 'DedupedRecords'
                ],
                'defaultTab' => null
            ],
            'CPK\RecordDriver\SolrDublinCore' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS',
                    'EVersion' => 'EVersion',
                    'Buy' => 'Buy',
                    'TOC' => 'TOC',
                    'UserCommentsObalkyKnih' => 'UserCommentsObalkyKnih',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'StaffViewDublinCore',
                    'DedupedRecords' => 'DedupedRecords'
                ],
                'defaultTab' => 'EVersion'
            ]
        ]
    ), /* vufind */
    'controllers' => array(
        'factories' => array(
            'record' => 'CPK\Controller\Factory::getRecordController',
            'portal' => 'CPK\Controller\Factory::getPortalController'
        ),
        'invokables' => array(
            'my-research' => 'CPK\Controller\MyResearchController',
            'librarycards' => 'CPK\Controller\LibraryCardsController',
            'search' => 'CPK\Controller\SearchController',
            'ajax' => 'CPK\Controller\AjaxController',
            'status' => 'CPK\Controller\StatusController',
            'admin' => 'CPK\Controller\AdminController'
        ), /* invokables */
    ), /* controllers */
    'controller_plugins' => [
        'factories' => [
            'holds' => 'CPK\Controller\Plugin\Factory::getHolds',
        ],
    ],
    'service_manager' => array(
        'factories' => array(
            'VuFind\AuthManager' => 'CPK\Auth\Factory::getAuthManager',
            'VuFind\ILSAuthenticator' => 'CPK\Auth\Factory::getILSAuthenticator',
            'CPK\AutocompletePluginManager' => 'CPK\Service\Factory::getAutocompletePluginManager'
        ), // Exceptions throwing system

        'invokables' => array(
            'wantitfactory' => 'CPK\WantIt\Factory'
        )
    )
);

$staticRoutes = array(
    'Statistics/Dashboard',
    'Statistics/Visits',
    'Statistics/Circulations',
    'Statistics/Payments',
    'Statistics/Searches',
    'MyResearch/UserConnect',
    'MyResearch/Settings'
);

foreach ($staticRoutes as $route) {
    list ($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route' => '/' . $route,
            'defaults' => array(
                'controller' => $controller,
                'action' => (! empty($action)) ? $action : 'default'
            )
        )
    );
}

return $config;
