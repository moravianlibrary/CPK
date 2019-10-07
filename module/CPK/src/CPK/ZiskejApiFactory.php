<?php declare(strict_types = 1);

namespace CPK;

use Http\Message\Authentication\Bearer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Mzk\ZiskejApi\Api;
use Mzk\ZiskejApi\ApiClient;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ZiskejApiFactory implements FactoryInterface
{

    /**
     * Create Ziskej Api service
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     *
     * @return mixed|\Mzk\ZiskejApi\Api
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiException
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $cookieManager = $serviceLocator->get('VuFind\CookieManager');
        $cookieZiskejMode = $cookieManager->get('ziskej');

        $config = $serviceLocator->get('VuFind\Config')->get('config');
        $apiBaseUrl = $config['Ziskej'][$cookieZiskejMode];

        $logger = new Logger('ZiskejApi');
        $logger->pushHandler(new StreamHandler('log/ziskej-api.log', $logger::DEBUG));

        $api = new Api(new ApiClient($apiBaseUrl, null, $logger));

        $token = $api->login($config['SensitiveZiskej']['username'], $config['SensitiveZiskej']['password']);

        //@todo store token

        return new Api(new ApiClient($apiBaseUrl, new Bearer($token), $logger));
    }

}
