<?php
namespace CPK\RecordDriver;
use Zend\ServiceManager\ServiceManager;

class Factory
{

    /**
     * Factory for SolrMarc record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMarc
     */
    public static function getSolrMarc(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarc(
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
    }

    public static function getSolrMarcMZK(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarcMZK(
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
    }

    public static function getSolrMarcVKOL(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarcVKOL(
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
    }

    public static function getSolrMarcNLK(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarcNLK(
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
    }

    public static function getSolrMarcLocal(ServiceManager $sm)
    {
        $driver = new \CPK\RecordDriver\SolrMarcLocal(
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
    }

}
