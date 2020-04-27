<?php declare(strict_types = 1);

namespace CPK;

use Http\Message\Authentication\Bearer;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Ecdsa\Sha512;
use Lcobucci\JWT\Signer\Key;
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
     * @throws \Exception
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $cookieManager = $serviceLocator->get('VuFind\CookieManager');
        $cookieZiskejMode = $cookieManager->get('ziskej');

        $config = $serviceLocator->get('VuFind\Config')->get('config');
        $apiBaseUrl = $config['Ziskej'][$cookieZiskejMode];

        $logger = new Logger('ZiskejApi');
        $logger->pushHandler(new StreamHandler('log/ziskej-api.log', $logger::DEBUG));

        $guzzleClient = \Http\Adapter\Guzzle6\Client::createWithConfig([
            'connect_timeout' => 10,
        ]);

        // generate token
        $keyFile = $config->Certs->ziskej;
        if(!$keyFile || !is_readable($keyFile)){
            throw new \Exception('Certificate file to generate token not found');
        }

        $time = time();
        $token = (new Builder())
            ->issuedBy('cpk')
            ->issuedAt($time)
            ->expiresAt($time + 3600)
            ->withClaim('app', 'cpk')
            ->getToken(
                new Sha512(),
                new Key('file://' . $keyFile)
            );

        //@todo store token

        return new Api(new ApiClient($guzzleClient, $apiBaseUrl, new Bearer($token), $logger));
    }

}
