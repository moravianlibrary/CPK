<?php
namespace Statistics\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'factories' => array(
                    'aleph' => 'MZKCommon\ILS\Driver\Factory::getAleph',
                ), /* factories */
                'invokables' => array(
                    'dummy' => 'MZKCommon\ILS\Driver\Dummy',
                ), /* invokables */
            ), /* ils_drivers */
            'recommend' => array(
                'factories' => array(
                    'specifiablefacets' => 'MZKCommon\Recommend\Factory::getSpecifiableFacets',
                ), /* factories */
            ), /* recommend */
            'recorddriver' => array (
                'factories' => array(
                    'solrdefault' => 'MZKCommon\RecordDriver\Factory::getSolrMarc',
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'holdingsils'     => 'MZKCommon\RecordTab\HoldingsILS',
                    'tagsandcomments' => 'MZKCommon\RecordTab\TagsAndComments',
                ), /* invokables */
            ), /* recordtab */
            'db_table' => array(
                'invokables' => array(
                    'recordstatus' => 'MZKCommon\Db\Table\RecordStatus',
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'VuFind\ILSHoldLogic' => 'MZKCommon\ILS\Logic\Factory::getFlatHolds',
        	'Statistics\PiwikPiwikStatistics' => 'Statistics\Piwik\PiwikStatisticsFactory::getPiwikStatistics',

        ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => 'MZKCommon\Controller\Factory::getRecordController',
        ),
        'invokables' => array(
            'search'     => 'MZKCommon\Controller\SearchController',
            'ajax'       => 'MZKCommon\Controller\AjaxController',
            'myresearch' => 'MZKCommon\Controller\MyResearchController',
        	'statistics' => 'Statistics\Controller\StatisticsController',
        ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'shortLoanRequests'   => 'MZKCommon\Controller\Plugin\Factory::getShortLoanRequests',
        ),
    ),
);

return $config;
