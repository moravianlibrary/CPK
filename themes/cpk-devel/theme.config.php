<?php
$toRet = array(
    'extends' => 'common-bootstrap3',
    'css' => array(
        // 'vendor/bootstrap.min.css',
        // 'vendor/bootstrap-accessibility.css',
        // 'bootstrap-custom.css',
        'compiled.css',
        'vendor/font-awesome.min.css',
        'vendor/bootstrap-slider.css',
        'vendor/bootstrap-select.min.css',
        'print.css:print'
    ),
    'js' => array(
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/rc4.js',
        'vendor/js.cookie.js',
        'vendor/bootstrap-datepicker.js',
        'vendor/bootstrap-datepicker.cs.js',
        'vendor/angular.min.js',
        'vendor/bootstrap-select.min.js',
        'common.js',
        'lightbox.js',
        'eu-cookies.js'
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
            'piwik' => 'Statistics\View\Helper\Root\Factory::getPiwik',
            'identityProviders' => 'CPK\View\Helper\CPK\Factory::getIdentityProviders'
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

/**
 * Implementation of easy switching between ng-apps minified & not minified
 *
 * Don't forget to run the cpk-devel/js/compile-ng-apps.sh script after an change is made to non-compiled code if minified version is desired.
 * <b>But don't also forget to update cpk-devel/js/compile-ng-apps.sh's list of files to compile!</b>
 *
 * @var boolean
 */
$useCompiledAngular = false;

if ($useCompiledAngular) {

    // Add compiled angular apps
    array_push($toRet['js'], 'ng-apps.min.js');
} else {

    $jsToInclude = [
        'favorites/module.js',
        'favorites/translate.filter.js',
        'favorites/favsNotifications.service.js',
        'favorites/favorite.class.js',
        'favorites/favorites.factory.js',
        'favorites/storage.service.js',
        'favorites/broadcaster.service.js',
        'favorites/list.controller.js',
        'favorites/record.controller.js',

        'federative-login/module.js',
        'federative-login/login.controller.js',

        'notifications/module.js',
        'notifications/notif.controller.js',

        'cpk.ng-app.js'
    ];

    $toRet['js'] = array_merge($toRet['js'], $jsToInclude);
}

return $toRet;
