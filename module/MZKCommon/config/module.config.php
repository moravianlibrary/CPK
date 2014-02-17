<?php
namespace MZKCommon\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'factories' => array(
                    'aleph' => function ($sm) {
                        return new \MZKCommon\ILS\Driver\Aleph(
                            $sm->getServiceLocator()->get('VuFind\DateConverter'),
                            $sm->getServiceLocator()->get('VuFind\CacheManager'),
                            $sm->getServiceLocator()->get('VuFind\DbTablePluginManager')->get('recordstatus')
                        );
                    },
                ),
            ),
            'recorddriver' => array (
                'factories' => array(
                    'solrdefault' => function ($sm) {
                        $driver = new \MZKCommon\RecordDriver\SolrMarc(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        $driver->attachILS(
                            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
                            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
                            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
                        );
                        return $driver;
                    },
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'holdingsils' => 'MZKCommon\RecordTab\HoldingsILS',
                ),
            ), /* recordtab */
            'db_table' => array(
                'invokables' => array(
                    'recordstatus' => 'MZKCommon\Db\Table\RecordStatus',
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'VuFind\ILSHoldLogic' => function ($sm) {
                return new \MZKCommon\ILS\Logic\FlatHolds(
                    $sm->get('VuFind\AuthManager'), $sm->get('VuFind\ILSConnection'),
                    $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
                );
            },
        ),
        'invokables' => array(
            'VuFind\Search'         => 'MZKCommon\VuFindSearch\Service',
         ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => function ($sm) {
                return new \VuFind\Controller\RecordController(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
        ),
        'invokables' => array(
            'search' => 'MZKCommon\Controller\SearchController',
            'ajax' => 'MZKCommon\Controller\AjaxController',
        ),
    ),
);

$staticRoutes = array(
    'Search/Conspectus', 'Search/MostSearched'
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

return $config;
