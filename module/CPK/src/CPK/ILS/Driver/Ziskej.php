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

use mysql_xdevapi\CrudOperationBindable;
use VuFindHttp\{HttpService, HttpServiceAwareInterface};
use Zend\Http\Response;
use Zend\Json\Json;

class Ziskej implements ZiskejInterface, HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    protected $apiUrl;
    protected $config;
    static private $ziskej;

    /**
     * Ziskej constructor.
     *
     */
    private function __construct()
    {
        $this->setHttpService(new HttpService());
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    static public function getZiskej()
    {
        if (!isset(Ziskej::$ziskej)) {
            Ziskej::$ziskej = new Ziskej();
        }
        return Ziskej::$ziskej;
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
        $url    = "$this->apiUrl/$path";
        $client = $this->httpService->createClient($url, $method);

        return $client;
    }

    /**
     * @param       $eppn
     * @param       $expand
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getReader($eppn, $expand = false)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("readers/$eppn", 'GET');
        if ($expand) {
            $params = ['expand' => 'status'];
            $client->setParameterGet($params);
        }
        $client->setHeaders(['Authorization' => "bearer $token"]);

        return $client->send();
    }

    /**
     * @return string token for login
     * @throws \Exception
     */
    private function getLoginToken()
    {
        $postParams = [
            'username' => $this->config['username'] ?? '',
            'password' => $this->config['password'] ?? '',
        ];
        $client = $this->getClient('login', 'POST');
        $client->setParameterPost($postParams);
        $response = $client->send();
        $token    = '';
        if ( ! empty($response) && $response->getStatusCode() == 200) {
            $token = Json::decode($response->getContent(), true)['token'];
        }

        return $token;
    }

    /**
     * @param      $eppn
     * @param bool $expand
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getUserTickets($eppn, $expand = false)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient('tickets', 'GET');
        $params = ['eppn' => $eppn];
        if ($expand) {
            $params['expand'] = 'detail';
        }
        $client->setParameterGet($params);
        $client->setHeaders(['Authorization' => "bearer $token"]);

        return $client->send();
    }

    /**
     * @param       $eppn
     *
     * @param       $documentId
     * @param array $docAltIds
     *
     * @param null $dateRequested
     * @param null $readerNote
     * @return array|mixed
     * @throws \Exception
     */
    public function createTicket($eppn, $documentId, array $docAltIds, $dateRequested = null, $readerNote = null)
    {
        $params = [
            'eppn'           => $eppn,
            'ticket_type'    => 'mvs',
            'doc_id'         => $documentId,
            'doc_alt_ids'    => $docAltIds,
        ];
        if (isset($date)) $params[] = ['date_requested' => $dateRequested];
        if (isset($reader_note)) $params[] = ['reader_note' => $readerNote];
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
     * @return Response
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

    /**
     * @param $id
     * @param $eppn
     *
     * @return Response
     * @throws \Exception
     */
    public function readMessage($id, $eppn)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("messages/$id/read", 'PUT');
        $client->setHeaders(
            [
                'Content-Type'  => 'application/json',
                'Authorization' => "bearer $token",
            ]
        );
        $client->setRawBody(Json::encode(['eppn' => $eppn]));

        return $client->send();
    }

    /**
     * @param $eppn
     * @param $params
     *
     * @return Response
     * @throws \Exception
     */
    public function regOrUpdateReader($eppn, $params)
    {
        $token  = $this->getLoginToken();
        $client = $this->getClient("readers/$eppn", 'PUT');
        $client->setHeaders(
            [
                'Content-Type'  => 'application/json',
                'Authorization' => "bearer $token",
            ]
        );
        $client->setRawBody(Json::encode($params));
        return $client->send();
    }
}