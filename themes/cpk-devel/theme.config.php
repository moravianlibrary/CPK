<?php
return array(
    'extends' => 'common-bootstrap3', 
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
        'vendor/rc4.js',
        'vendor/localforage-bundle.min.js',
        'vendor/js.cookie.js',
        'vendor/angular.min.js',
        'vendor/bootstrap-datepicker.js',
        'vendor/bootstrap-datepicker.cs.js',
        'favorites/module.js',
        'favorites/translate.filter.js',
        'favorites/favsNotifications.service.js',
        'favorites/favorite.class.js',
        'favorites/favorites.factory.js',
        'favorites/storage.service.js',
        'favorites/broadcaster.service.js',
        'favorites/list.controller.js',
        'favorites/record.controller.js',
        'cpk.ng-app.js',
        'common.js',
        'lightbox.js',
        'eu-cookies.js',
    ),
    'less' => array(
        'active' => false,
        'compiled.less'
    ),
    'favicon' => 'favicon.ico',
    'helpers' => array(
        'factories' => array(
            'record' => 'CPK\View\Helper\CPK\Factory::getRecord',
            'flashmessages' => 'CPK\View\Helper\CPK\Factory::getFlashmessages',
            'logos' => 'CPK\View\Helper\CPK\Factory::getLogos',
            'globalNotifications' => 'CPK\View\Helper\CPK\Factory::getGlobalNotifications',
            'portalpages' => 'CPK\View\Helper\CPK\Factory::getPortalPages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap3\Factory::getLayoutClass',
            'piwik' => 'Statistics\View\Helper\Root\Factory::getPiwik'
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap3\VuDL',
            'parseFilterOptions' => 'CPK\View\Helper\CPK\ParseFilterOptions',
            'renderarray' => 'CPK\View\Helper\CPK\RenderArray',
            'currenturl' => 'CPK\View\Helper\CPK\CurrentURL'
        )
    )
);
