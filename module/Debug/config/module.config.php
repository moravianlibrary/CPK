<?php
namespace Debug\Module\Configuration;

$config = array(
    'service_manager' => array(
        'factories' => array(
            'VuFind\Translator' => 'Debug\Service\Factory::getTranslator',
        ),
    ), /* service_manager */
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => [
                'invokables' => [
                    'xcncip2' => 'Debug\ILS\Driver\XCNCIP2'
                ],
            ], /* ils_driver */
        ), /* plugin_managers */
    ), /* vufind */
    'controllers' => array(
        'invokables' => array(
            'ajax' => 'Debug\Controller\AjaxController',
        ), /* invokables */
    ), /* controllers */
);

return $config;