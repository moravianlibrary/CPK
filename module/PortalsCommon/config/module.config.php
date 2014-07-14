<?php
namespace PortalsCommon\Module\Configuration;

$config = array(
    'controllers' => array(
        'factories' => array(
            'record' => 'PortalsCommon\Controller\Factory::getRecordController',
        ),
        'invokables' => array(
            'search'     => 'PortalsCommon\Controller\SearchController',
            'cart' => 'PortalsCommon\Controller\CartController',
        ),
    ),
);

return $config;
