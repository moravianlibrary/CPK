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
        'vendor/bootstrap-select.min.js',
        'common.js',
        'lightbox.js',
        'eu-cookies.js',
        'search-results.js',
        'vendor/jsTree/jstree.min.js',
        'facets.js'
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
            'identityProviders' => 'CPK\View\Helper\CPK\Factory::getIdentityProviders',
            'help' => 'CPK\View\Helper\CPK\Factory::getHelp'
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

$angularForbiddenForIE = true;

if (! ($angularForbiddenForIE && USES_IE)) {

    if ($useCompiledAngular) {

        // Add compiled angular apps
        array_push($toRet['js'], 'vendor/angular.min.js', 'ng-cpk/ng-cpk.min.js');
    } else {

        $jsToInclude = [

            'vendor/angular.min.js',

            'ng-cpk/favorites/module.js',
            'ng-cpk/favorites/favsNotifications.service.js',
            'ng-cpk/favorites/favorite.class.js',
            'ng-cpk/favorites/favorites.factory.js',
            'ng-cpk/favorites/storage.service.js',
            'ng-cpk/favorites/broadcaster.service.js',
            'ng-cpk/favorites/list.controller.js',
            'ng-cpk/favorites/record.controller.js',
            'ng-cpk/favorites/search.controller.js',

            'ng-cpk/federative-login/module.js',
            'ng-cpk/federative-login/login.controller.js',

            'ng-cpk/notifications/module.js',
            'ng-cpk/notifications/notif.controller.js',

            'ng-cpk/admin/module.js',
            'ng-cpk/admin/configurations/conf.controller.js',
            'ng-cpk/admin/translations/trans.controller.js',

            'ng-cpk/history/module.js',
            'ng-cpk/history/checkedouthistory.controller.js',

            'ng-cpk/module.js',
            'ng-cpk/global.controller.js',
            'ng-cpk/translate.filter.js'
        ];

        $toRet['js'] = array_merge($toRet['js'], $jsToInclude);
    }
}
return $toRet;
