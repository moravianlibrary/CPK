<?php

namespace CPK\Ziskej;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for instantiating objects
 */
class ZiskejMvsFactory implements FactoryInterface
{
    /**
     * Create service
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sm
     * @return \CPK\Ziskej\ZiskejMvs
     */
    public function createService(ServiceLocatorInterface $sm): ZiskejMvs
    {
        /** @var \Zend\Config\Config $config */
        $config = $sm->get('VuFind\Config')->get('config');

        /** @var \VuFind\Cookie\CookieManager $cookieManager */
        $cookieManager = $sm->get('VuFind\CookieManager');

        return new \CPK\Ziskej\ZiskejMvs($config, $cookieManager);
    }
}
