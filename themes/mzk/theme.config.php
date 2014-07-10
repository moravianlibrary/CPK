<?php
return array(
    'extends' => 'common-bootstrap',
    'css' => array(
        'mzk.css'
    ),
    'favicon' => 'vufind-favicon.ico',
	'helpers' => array(
			'factories' => array(
					'record' => function ($sm) {
						return new \MZKCatalog\View\Helper\MZKCatalog\Record(
								$sm->getServiceLocator()->get('VuFind\Config')->get('config')
						);
					},
			),
	)
);