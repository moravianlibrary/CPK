<?php
namespace CPK\Ziskej;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for instantiating objects
 */
class Factory implements FactoryInterface
{
    /**
     * Create CPK\Ziskej service
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sm
     * @return \CPK\Ziskej\Ziskej
     */
    public function createService(ServiceLocatorInterface $sm): Ziskej
    {
        /** @var \Zend\Config\Config $config */
        $config = $sm->get('VuFind\Config')->get('config');

        /** @var \VuFind\Cookie\CookieManager $cookieManager */
        $cookieManager = $sm->get('VuFind\CookieManager');

        return new \CPK\Ziskej\Ziskej($config, $cookieManager);
    }
}
