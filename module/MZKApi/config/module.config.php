<?php
namespace MZKApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'api' => 'MZKApi\Controller\Factory::getApiController',
            'itemapi' => 'MZKApi\Controller\Factory::getItemApiController',
        ]
    ],
    'router' => [
        'routes' => [
            'itemApiv1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/item',
                    'defaults' => [
                        'controller' => 'ItemApi',
                        'action'     => 'item',
                    ]
                ]
            ]
        ],
    ],
];

return $config;
