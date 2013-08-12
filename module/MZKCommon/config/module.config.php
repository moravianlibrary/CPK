<?php
namespace MZKCommon\Module\Configuration;

/*
$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmarc' => function ($sm) {
                        $driver = new \MZKCommon\RecordDriver\SolrMarc(
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
                ) // factories
            ), // recorddriver
        ), // plugin_managers
    ) // vufind
);
*/
$config = array();

return $config;
