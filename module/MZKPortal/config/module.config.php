<?php
namespace MZKPortal\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmuni' => function ($sm) {
                        $driver = new \MZKPortal\RecordDriver\SolrMarcMuni(
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
                    'solrmzk' => function ($sm) {
                        $driver = new \MZKPortal\RecordDriver\SolrMarcMzk(
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
                    'solrkjm' => function ($sm) {
                        $driver = new \MZKPortal\RecordDriver\SolrMarcKjm(
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
                    'solrmerged' => function ($sm) {
                        $driver = new \MZKPortal\RecordDriver\SolrMarcMerged(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        return $driver;
                    },
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'libraries' => 'MZKPortal\RecordTab\Libraries',
                ),
            ) /* recordtab */
        ), /* plugin_managers */
        'recorddriver_tabs' => array(
            'MZKPortal\RecordDriver\SolrMarcMerged' => array(
                'tabs' => array (
                    'Libraries' => 'Libraries',
                    'Description' => 'Description',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ),
            )
        ) /* recorddriver_tabs */
    ) /* vufind */
);

return $config;
