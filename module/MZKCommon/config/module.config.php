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
            'db_table' => array(
                'invokables' => array(
                    'recordstatus' => 'MZKCommon\Db\Table\RecordStatus',
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'search' => 'MZKCommon\Controller\SearchController',
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
