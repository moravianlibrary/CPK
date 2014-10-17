<?php
namespace MZKCatalog\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmzk'   => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                    'solrmzk04' => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                    'solrebsco' => 'MZKCatalog\RecordDriver\Factory::getEbscoSolrMarc',
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'holdingsils' => 'MZKCommon\RecordTab\HoldingsILS',
                    'citation' => 'MZKCatalog\RecordTab\Citation',
                ), /* invokables */
            ), /* recordtab */
        ), /* plugin_managers */
        'recorddriver_tabs' => array(
            'MZKCatalog\RecordDriver\SolrMarc' => array(
                'tabs' => array(
                    'Holdings' => 'HoldingsILS',
                    'Description' => 'Description',
                    'Citation' => 'Citation',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
        ) /* recorddriver_tabs */
    ), /* vufind */
    'service_manager' => array(
        'factories' => array(
            'MZKCatalog\AlephBrowse\Connector' => 'MZKCatalog\AlephBrowse\Factory::getAlephBrowseConnector',
            'VuFind\Translator' => 'MZKCatalog\Service\Factory::getTranslator',
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => 'MZKCatalog\Controller\Factory::getRecordController',
            'alephbrowse' => 'MZKCatalog\Controller\Factory::getAlephBrowseController',
        ),
        'invokables' => array(
            'myresearch' => 'MZKCatalog\Controller\MyResearchController',
        ),
    ),
);

$staticRoutes = array(
    'AlephBrowse/Home',
);

foreach ($staticRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/' . $route,
            'defaults' => array(
                'controller' => $controller,
                'action'     => $action,
            )
        )
    );
}

$nonTabRecordActions = array('DigiRequest');

foreach ($nonTabRecordActions as $action) {
    $config['router']['routes']['record' . '-' . strtolower($action)] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/' . 'Record' . '/[:id]/' . $action,
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => 'Record',
                'action'     => $action,
            )
        )
    );
}

return $config;
