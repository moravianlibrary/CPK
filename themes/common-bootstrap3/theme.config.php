<?php
return array(
    'extends' => 'obalkyknih-api-v3-bootstrap3',
    'css' => array(
        'common.css',
        'datepicker3.css',
    ),
    'js' => array(
        'bootstrap-datepicker.js',
        'bootstrap-datepicker.cs.js'
    ),
    'helpers' => array(
        'factories' => array(
            'record' => function ($sm) {
                return new \MZKCommon\View\Helper\MZKCommon\Record(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
        ),
    )
);