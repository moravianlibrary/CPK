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
     * @var \CPK\Ziskej\Ziskej
     */
    private $cpkZiskej;

    /**
     * Create Ziskej Api service
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     * @return \Mzk\ZiskejApi\Api
     * @throws \Exception
     */
    public function createService(ServiceLocatorInterface $serviceLocator): Api
    {
        $this->cpkZiskej = $serviceLocator->get('CPK\Ziskej');

        $logger = new Logger('ZiskejApi');

        $handlerStream = new StreamHandler('log/ziskej-api.log', $logger::DEBUG);
        $logger->pushHandler($handlerStream);

        $guzzleClient = \Http\Adapter\Guzzle6\Client::createWithConfig([
            'connect_timeout' => 10,
        ]);

        // generate token
        $time = time();
        $token = (new Builder())
            ->issuedBy('cpk')
            ->issuedAt($time)
            ->expiresAt($time + 3600)
            ->withClaim('app', 'cpk')
            ->getToken(
                new Sha512(),
                new Key('file://' . $this->cpkZiskej->getPrivateKeyFileLocation())
            );

        //@todo store token

        return new Api(new ApiClient($guzzleClient, $this->cpkZiskej->getCurrentUrl(), new Bearer($token), $logger));
    }

}
