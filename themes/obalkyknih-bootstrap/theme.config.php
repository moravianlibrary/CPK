<?php
return array(
    'extends' => 'bootstrap',
    'css' => array(
        'obalkyknih.css'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'record' => function ($sm) {
                return new \ObalkyKnih\View\Helper\ObalkyKnih\Record(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
        ),
    )
);