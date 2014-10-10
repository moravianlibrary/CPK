<?php
return array(
    'extends' => 'common-bootstrap3',
    'css' => array(
        'mzk.css'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'layoutclass' => 'MZKCatalog\View\Helper\mzk3\Factory::getLayoutClass',
        )
    )
);