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

use CPK\ILS\Driver\Exception\ZiskejException;
use Exception;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Driver\DriverInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFindHttp\{HttpService, HttpServiceAwareInterface, HttpServiceAwareTrait};
use Zend\Http\{Client, Response};
use Zend\Json\Json;
use Zend\Log\LoggerAwareInterface;

class Ziskej implements DriverInterface, HttpServiceAwareInterface, LoggerAwareInterface
{
    use HttpServiceAwareTrait, LoggerAwareTrait;

    /**
     * Config data of SensitiveZiskej
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Base URL of API
     * @var string
     */
    protected $apiUrl;

    public function __construct(\Zend\Config\Config $configLoader)
    {
        $this->config = $configLoader->get('SensitiveZiskej');

        //@todo refactor
        $ziskejMode = $_COOKIE['ziskej'] ?? 'disabled';
        $this->apiUrl = $configLoader->get('Ziskej')->$ziskejMode;
    }

    /**
     * Initialize the driver.
     * Validate configuration and perform all resource-intensive tasks needed to make the driver active.
     *
     * @throws \VuFind\Exception\ILS
     */
    public function init(): void
    {
        $this->setHttpService(new HttpService());

        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
    }

    /**
     * Set API Url
     *
     * @param string $apiUrl
     */
    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     * @throws ILSException
     */
    public function getStatus($id)
    {
        throw new ILSException("Ziskej driver cannot get statuses");
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return void An array of getStatus() return values on success.
     * @throws ILSException
     */
    public function getStatuses($ids)
    {
        throw new ILSException("Ziskej driver cannot get statuses");
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     * @param array $patron Patron data
     *
     * @return void On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     * @throws ILSException
     */
    public function getHolding($id, array $patron = null)
    {
        throw new ILSException("Ziskej driver cannot get holdings");

    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return void An array with the acquisitions data on success.
     * @throws ILSException
     */
    public function getPurchaseHistory($id)
    {
        throw new ILSException("Ziskej driver cannot get purchase history");
    }

    /**
     * @return array|mixed
     * @throws Exception
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
     * @return Client
     * @throws Exception
     */
    protected function getClient($path, $method)
    {
        $url = "$this->apiUrl/$path";
        $client = $this->httpService->createClient($url, $method);

        return $client;
    }

    /**
     * @param       $eppn
     * @param       $expand
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getReader($eppn, $expand = false)
    {
        $token = $this->getLoginToken();
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
     * @throws Exception
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
        $token = '';
        if (!empty($response) && $response->getStatusCode() == 200) {
            $token = Json::decode($response->getContent(), true)['token'];
        }

        return $token;
    }

    /**
     * @param      $eppn
     * @param bool $expand
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getUserTickets($eppn, $expand = false)
    {
        $token = $this->getLoginToken();
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
     * @param string $eppn Edu Person Principal Name
     * @param string $documentId Document id
     * @param array $documentAlternativeIds Array of alternative document ids
     * @param string|null $dateRequested Requested date
     * @param string|null $readerNote Reader note
     * @return string Ticket ID
     *
     * @throws \CPK\ILS\Driver\Exception\ZiskejException
     */
    public function createTicket(
        string $eppn,
        string $documentId,
        array $documentAlternativeIds = [],
        string $dateRequested = null,
        string $readerNote = null
    ): string {
        $client = $this->getClient('tickets', 'POST');

        if (empty($eppn)) {
            throw new ZiskejException('Mandatory parameter $eppn is empty');
        }

        if (empty($documentId)) {
            throw new ZiskejException('Mandatory parameter $documentId is empty');
        }

        //@todo refactor
        $token = $this->getLoginToken();
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "bearer $token",
        ]);

        $params = [
            'eppn' => $eppn,
            'ticket_type' => 'mvs',
            'doc_id' => $documentId,
        ];
        if (!empty($documentAlternativeIds)) {
            //@todo ask to change Ziskej API to accept list instead of string here, then remove encode here
            $params['doc_alt_ids'] = Json::encode($documentAlternativeIds);
        }
        if (!empty($dateRequested)) {
            $params['date_requested'] = $dateRequested;
        }
        if (!empty($readerNote)) {
            $params['reader_note'] = $readerNote;
        }

        $json = Json::encode($params);
        $client->setRawBody($json);

        $this->debug('Ziskej API client.', (array)$client);

        $response = $client->send();

        $this->debug('Ziskej API Response', (array)$response);

        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
            throw new ZiskejException('Ziskej API error, code: ' . $response->getStatusCode() .
                ', message: ' . $response->getReasonPhrase());
        }

        $responseBody = Json::decode($response->getBody());
        $ticketID = $responseBody->ticket_id;

        return $ticketID;
    }

    /**
     * @param $id
     *
     * @param $eppn
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getTicketDetail($id, $eppn)
    {
        $token = $this->getLoginToken();
        $client = $this->getClient("tickets/$id", 'GET');
        $client->setParameterGet(['eppn' => $eppn]);
        $client->setHeaders(['Authorization' => 'bearer ' . $token]);

        return $client->send();
    }

    /**
     * @param $id
     * @param $eppn
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getTicketMessages($id, $eppn)
    {
        $token = $this->getLoginToken();
        $client = $this->getClient("messages/$id", 'GET');
        $client->setParameterGet(['eppn' => $eppn]);
        $client->setHeaders(['Authorization' => 'bearer ' . $token]);

        return $client->send();
    }

    /**
     * @param $id
     * @param $eppn
     * @param $text
     *
     * @return Response
     * @throws Exception
     */
    public function createMessage($id, $eppn, $text)
    {
        $params = [
            'eppn' => $eppn,
            'text' => $text,
        ];
        $token = $this->getLoginToken();
        $client = $this->getClient("messages/$id", 'POST');
        $client->setHeaders(
            [
                'Content-Type' => 'application/json',
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
     * @throws Exception
     */
    public function readMessage($id, $eppn)
    {
        $token = $this->getLoginToken();
        $client = $this->getClient("messages/$id/read", 'PUT');
        $client->setHeaders(
            [
                'Content-Type' => 'application/json',
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
     * @throws Exception
     */
    public function regOrUpdateReader($eppn, $params)
    {
        $token = $this->getLoginToken();
        $client = $this->getClient("readers/$eppn", 'PUT');
        $client->setHeaders(
            [
                'Content-Type' => 'application/json',
                'Authorization' => "bearer $token",
            ]
        );
        $client->setRawBody(Json::encode($params));
        $sent = $client->send();
        return $sent;
    }

    /**
     * Set configuration.
     *
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @return void
     */
    public function setConfig($config)
    {

    }
}
