<?php
return array(
    'extends' => 'bootstrap3',
    'js' => array(
        'obalkyknih.js'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'record'     => 'ObalkyKnihV3\View\Helper\ObalkyKnih\Factory::getRecord',
            'obalkyknih' => 'ObalkyKnihV3\View\Helper\ObalkyKnih\Factory::getObalkyKnih',
        ),
    ),
);