<?php
return array(
    'extends' => 'obalkyknih-bootstrap',
    'css' => array(
        'common.css'
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