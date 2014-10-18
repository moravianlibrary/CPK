<?php
return array(
    'extends' => 'bootstrap3',
    'css' => array(
        'obalkyknih.css'
    ),
    'js' => array(
        'obalkyknih.js'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'record' => function ($sm) {
                return new \ObalkyKnihV3\View\Helper\ObalkyKnih\Record(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
        ),
    )
);