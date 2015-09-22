<?php
return array(
    'extends' => 'obalkyknih-api-v3-bootstrap3',
    'css' => array(
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'vendor/font-awesome.min.css',
        'vendor/bootstrap-slider.css',
        'print.css:print',
        'style.css'
    ),
    'js' => array(
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        'vendor/typeahead.js',
        'vendor/rc4.js',
        'common.js',
        'lightbox.js',
    	'morris-0.4.1.min.js',
    	'raphael.min.js',
    	'jquery-ui.min.js',
    	'ajax-record-tabs.js',
        'eu-cookies.js',
    ),
    'less' => array(
        'active' => false,
        'compiled.less'
    ),
    'favicon' => 'favicon.ico',
    'helpers' => array(
        'factories' => array(
            'record'     => 'CPK\View\Helper\CPK\Factory::getRecord',
            'flashmessages' => 'CPK\View\Helper\CPK\Factory::getFlashmessages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap3\Factory::getLayoutClass',
            'piwik' => 'Statistics\View\Helper\Root\Factory::getPiwik',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap3\VuDL',
            'renderarray' => 'CPK\View\Helper\CPK\RenderArray',
            'currenturl' => 'CPK\View\Helper\CPK\CurrentURL',
        )
    )
);
