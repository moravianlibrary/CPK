<?php
namespace MZKCatalog\Module\Configuration;

$config = array(
    'service_manager' => array(
        'factories' => array( 
            'VuFind\ILSHoldLogic' => function ($sm) {
                return new \MZKCatalog\ILS\Logic\Holds(
                    $sm->get('VuFind\AuthManager'), $sm->get('VuFind\ILSConnection'),
                    $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
                );
            },
        ),
    ),
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmzk' => function ($sm) {
                        $driver = new \MZKCatalog\RecordDriver\SolrMarc(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        $driver->attachILS(
                            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
                            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
                            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
                        );
                        return $driver;
                    },
                ) /* factories */
            ), /* recorddriver */
        ), /* plugin_managers */
    ), /* vufind */
);

return $config;