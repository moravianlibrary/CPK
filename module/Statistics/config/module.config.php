<?php
namespace Statistics\Module\Configuration;

$config = array(
    'service_manager' => array(
        'factories' => array(
            'Statistics\PiwikPiwikStatistics' => 'Statistics\Piwik\PiwikStatisticsFactory::getPiwikStatistics'
        )

    ),
    'controllers' => array(
        'invokables' => array(
            'statistics' => 'Statistics\Controller\StatisticsController'
        )
    )
);

return $config;
