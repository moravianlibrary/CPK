<?php
namespace Debug\Module\Configuration;

$config = array(
    'service_manager' => array(
        'factories' => array(
            'VuFind\Translator' => 'Debug\Service\Factory::getTranslator',
        ),
    ),
);

return $config;