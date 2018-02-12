<?php
/**
 * Service patter for authentication with OAUTH2 in Koha ILS Driver
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\Auth;

use CPK\Db\Table\KohaTokens;
use VuFind\Exception\ILS as ILSException;
use \VuFindHttp\HttpServiceAwareInterface;
use VuFindHttp\HttpServiceAwareTrait;
use \Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

class KohaRestService implements HttpServiceAwareInterface, LoggerAwareInterface
{
    use HttpServiceAwareTrait;

    /**
     * Institution configuration.
     *
     * @var
     */
    protected $config;

    /**
     * Koha tokens database storage.
     *
     * @var KohaTokens
     */
    protected $tokensTable;

    /**
     * Institution source
     *
     * @var
     */
    protected $source;

    protected $logger;

    /**
     * Max time different between real expiration and saved expiration in seconds
     *
     * @var
     */
    protected $tokenExpirationDiff = 30;

    public function __construct(KohaTokens $kohaTokens)
    {
        $this->tokensTable = $kohaTokens;
    }

    public function createHttpClient($url, $basicAuth = false)
    {
        $client = $this->httpService->createClient($url);

        if (isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Zend\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context, 'ssl', 'verify_peer_name', false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Zend\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(
            ['timeout' => $timeout, 'useragent' => 'VuFind', 'keepalive' => true]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );

        if($basicAuth) {
            $client->setAuth($basicAuth['username'], $basicAuth['password']);
        }

        return $client;
    }

    public function requestNewOAUTH2Token()
    {
        $tokenEndpoint = $this->config['Catalog']['tokenEndpoint'];
        $client = $this->createHttpClient($tokenEndpoint);

        $adapter = new \Zend\Http\Client\Adapter\Curl();
        $client->setAdapter($adapter);
        $adapter->setOptions([
            'curloptions' => [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => [
                    'client_id' => $this->config['Catalog']['clientId'],
                    'client_secret' => $this->config['Catalog']['clientSecret'],
                    'grant_type' => isset($this->config['Catalog']['grantType'])
                        ? $this->config['Catalog']['grantType']
                        : 'client_credentials'
                ]
            ]
        ]);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logger->err(
                "POST request for '$tokenEndpoint' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with getting OAUTH2 access token.');
        }

        return json_decode($response->getContent(), true);
    }

    /**
     * Checks if is token in session. Gets token from DB and validates it
     *
     * @return array|bool
     */
    public function handleAccessToken()
    {
        //Store tokens in session so we dont need to request from database
        if (!($tokenData = $_SESSION['CPK\ILS\Driver\KohaRest'][$this->source]['accessToken'])) {
            $tokenData = $this->tokensTable->getAccessToken($this->source);

            if (!$tokenData) {
                $tokenData = $this->tokensTable->createAccessToken(
                    $this->source,
                    $this->requestNewOAUTH2Token()
                );
            } elseif ($this->isExpired($tokenData['timestamp_expiration'])) {
                $tokenData = $this->tokensTable->renewAccessToken(
                    $this->source,
                    $this->requestNewOAUTH2Token()
                );
            }
            $_SESSION['CPK\ILS\Driver\KohaRest'][$this->source]['accessToken'] = $tokenData;
        } elseif($this->isExpired($tokenData['timestamp_expiration'])) {
            $tokenData = $this->tokensTable->renewAccessToken(
                $this->source,
                $this->requestNewOAUTH2Token()
            );
            $_SESSION['CPK\ILS\Driver\KohaRest'][$this->source]['accessToken'] = $tokenData;
        }

        return $tokenData;
    }

    public function createOAUTH2Client($url)
    {
        $tokenData = $this->handleAccessToken();

        $client = $this->createHttpClient($url);

        $client->getRequest()->getHeaders()->addHeaderLine(
            'Authorization', $tokenData['token_type'] . ' ' . $tokenData['access_token']
        );

        return $client;
    }

    public function isExpired($expiration)
    {
        return strtotime(date('Y-m-d H:i:s')) >= strtotime($expiration) - $this->tokenExpirationDiff;
    }

    public function renewToken()
    {
        $tokenData = $this->tokensTable->createAccessToken(
            $this->source,
            $this->requestNewOAUTH2Token()
        );
        $_SESSION['CPK\ILS\Driver\KohaRest'][$this->source]['accessToken'] = $tokenData;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}