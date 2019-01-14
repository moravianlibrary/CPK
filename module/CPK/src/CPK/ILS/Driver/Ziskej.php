<?php
/**
 * Ziskej driver
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2018.
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
 * @author   Andrii But <xbut@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

namespace CPK\ILS\Driver;

use VuFindHttp\HttpService;
use VuFindHttp\HttpServiceAwareInterface;
use Zend\Json\Json;

class Ziskej implements ZiskejInterface, HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    protected $apiUrl;
    protected $config;

    /**
     * Ziskej constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->apiUrl = $this->config['apiUrl'];
        $this->setHttpService(new HttpService());
    }

    /**
     * @param string api url
     */
    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function getLibraries()
    {
        $client = $this->getClient('libraries', 'GET');

        return $client->send();
    }

    /**
     * @param $path
     * @param $method
     *
     * @return \Zend\Http\Client
     * @throws \Exception
     */
    protected function getClient($path, $method)
    {
//            if (null === $this->httpService) {
//                throw new \Exception('HTTP service missing.');
//            }
        $url    = "$this->apiUrl/$path";
        $client = $this->httpService->createClient($url, $method);

        return $client;
    }

    /**
     * @param       $eppn
     * @param array $params
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getReader($eppn, array $params = [])
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("readers/$eppn", 'GET');
        $client->setParameterGet($params);
        $client->setHeaders(['Authorization' => "bearer $token"]);

//            $client->getRequest()->getHeaders()->addHeaderLine();
        return $client->send();
    }

    /**
     * @return string token for login
     * @throws \Exception
     */
    private function getLoginToken()
    {
        $postParams = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ];
        $client     = $this->getClient('login', 'POST');
        $client->setParameterPost($postParams);
        $response = $client->send();
        $token    = '';
        if ( ! empty($response) && $response->getStatusCode() == 200) {
            $token = json_decode($response->getContent(), true)['token'];
        }

        return $token;
    }

    /**
     * @param array $params eppn required
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getUserTickets(array $params = [])
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient('tickets', 'GET');
        $client->setParameterGet($params);
        $client->setHeaders(['Authorization' => "bearer $token"]);

        return $client->send();
    }

    /**
     * @param $eppn
     *
     * @todo: not ready for prod use
     * @return array|mixed
     * @throws \Exception
     */
    public function createTicket($eppn)
    {
        $params = [
            'eppn'           => $eppn,
            'ticket_type'    => 'mvs',
            'doc_id'         => 'mzk.MZK01-001579506',
            'doc_alt_ids'    => ['nkp.NKC01-002901834'],
            'date_requested' => date('Y-m-d'),
        ];
        $token  = $this->getLoginToken();
        $client = $this->getClient('tickets', 'POST');
        $client->setRawBody(Json::encode($params));
        $client->setHeaders(
            [
                'Content-Type'  => 'application/json',
                'Authorization' => "bearer $token",
            ]
        );

        return $client->send();
    }

    /**
     * @param $id
     *
     * @param $eppn
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getTicketDetail($id, $eppn)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("tickets/$id", 'GET');
        $client->setParameterGet(['eppn' => $eppn]);
        $client->setHeaders(['Authorization' => 'bearer '.$token]);

        return $client->send();
    }

    /**
     * @param $id
     * @param $eppn
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getTicketMessages($id, $eppn)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("messages/$id", 'GET');
        $client->setParameterGet(['eppn' => $eppn]);
        $client->setHeaders(['Authorization' => 'bearer '.$token]);

        return $client->send();
    }

    /**
     * @param $id
     * @param $eppn
     * @param $text
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    public function createMessage($id, $eppn, $text)
    {
        $params = [
            'eppn' => $eppn,
            'text' => $text,
        ];
        $token  = $this->getLoginToken();
        $client = $this->getClient("messages/$id", 'POST');
        $client->setHeaders(
            [
                'Content-Type'  => 'application/json',
                'Authorization' => "bearer $token",
            ]
        );
        $client->setRawBody(Json::encode($params));

        return $client->send();
    }

    public function makeMessageRead($id)
    {

    }
}