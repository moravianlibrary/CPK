<?php
namespace CPK\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'invokables' => array(
                    'dummy' => 'CPK\ILS\Driver\Dummy',
                ), /* invokables */
            ), /* ils_drivers */
            'recorddriver' => array (
                'factories' => array(
                    'solrmarc'     => 'CPK\RecordDriver\Factory::getSolrMarc',
                    'solrcpk_mzk'  => 'CPK\RecordDriver\Factory::getSolrMarcMZK',
                    'solrcpk_vkol' => 'CPK\RecordDriver\Factory::getSolrMarcVKOL',
                    'solrcpk_nlk'  => 'CPK\RecordDriver\Factory::getSolrMarcNLK',
                ) /* factories */
            ), /* recorddriver */
            'auth' => array(
                'factories' => array(
                    'perunShibboleth' => 'CPK\Auth\Factory::getPerunShibboleth',
                ), /* factories */
            ), /* auth */
        ), /* plugin_managers */
    ), /* vufind */
    'controllers' => array(
        'invokables' => array(
            'my-research' => 'CPK\Controller\MyResearchController',
            'librarycards' => 'CPK\Controller\LibraryCardsController',
        ), /* invokables */
    ), /* controllers */
    'service_manager' => array(
        'factories' => array(
            'VuFind\AuthManager' => 'CPK\Auth\Factory::getAuthManager',
        ),
        'invokables' => [
            'identity-resolver' => 'CPK\Perun\IdentityResolver',
        ],
    ),
);

return $config;
