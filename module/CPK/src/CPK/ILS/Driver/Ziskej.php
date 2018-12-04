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

    use Zend\Http\Client;

    class Ziskej implements ZiskejInterface
    {
        protected $client;
        protected $apiUrl;
        protected $version;
        protected $config;

        /**
         * Ziskej constructor.
         *
         * @param $config
         */
        public function __construct($config)
        {
            $this->config  = $config;
            $this->client  = new Client();
            $this->apiUrl  = $this->config['apiUrl'];
            $this->version = $this->config['version'];
        }

        /**
         * @return string api url
         */
        public function getApiUrl()
        {
            return $this->apiUrl;
        }

        /**
         * @param string api url
         */
        public function setApiUrl($apiUrl)
        {
            $this->apiUrl = $apiUrl;
        }

        /**
         * @return string version of api
         */
        public function getVersion()
        {
            return $this->version;
        }

        /**
         * @param string version of api
         */
        public function setVersion($version)
        {
            $this->version = $version;
        }

        /**
         * @return object Zend\Http\Client
         */
        public function getClient()
        {
            return $this->client;
        }

        /**
         * @param $endPoint
         * @param $method
         *
         * @return object
         */
        public function prepareRequest($endPoint, $method)
        {
            $url    = $this->getApiUrl().'/'.$this->getVersion().$endPoint;
            $client = $this->getClient();
            $client->setUri($url);
            $client->setMethod($method);

            return $client;
        }

        /**
         * @return string token for login
         */
        private function getLoginToken()
        {
            $client = $this->prepareRequest('/login', 'POST');
            $client->setParameterPost([
                'username' => $this->config['username'],
                'password' => $this->config['password'],
            ]);
            $response = $client->send();
            $token    = '';
            if ( ! empty($response)) {
                $token = json_decode($response->getContent(), true)['token'];
            }

            return $token;
        }

        /**
         * @return array|mixed
         */
        public function getLibraries()
        {
            $client          = $this->prepareRequest('/libraries', 'GET');
            $response        = $client->send();
            $responseContent = [];
            if ( ! empty($response)) {
                $responseContent = json_decode($response->getContent(), true);
            }

            return $responseContent;
        }

        /**
         * @param $eppn
         * @param array $params
         *
         * @return array|mixed
         */
        public function getReader($eppn, array $params)
        {
            $token  = $this->getLoginToken();
            $client = $this->prepareRequest('/readers/'.$eppn, 'GET');
            $client->setHeaders(['Authorization' => 'bearer '.$token]);
            $response        = $client->send();
            $responseContent = [];
            if ( ! empty($response)) {
                $responseContent = json_decode($response->getContent(), true);
            }

            return $responseContent;
        }

        /**
         * @param $eppn
         * @param array $params
         *
         * @return array|mixed
         */
        public function getUserTickets($eppn, array $params)
        {
            $token  = $this->getLoginToken();
            $client = $this->prepareRequest('/tickets?eppn='.$eppn, 'GET');
            $client->setHeaders(['Authorization' => 'bearer '.$token]);
            $response        = $client->send();
            $responseContent = [];
            if ( ! empty($response)) {
                $responseContent = json_decode($response->getContent(), true);
            }

            return $responseContent;
        }

        /**
         * @param $id
         *
         * @param $eppn
         *
         * @return array|mixed
         */
        public function getTicket($id, $eppn)
        {
            $token  = $this->getLoginToken();
            $client = $this->prepareRequest('/tickets/'.$id.'?eppn='.$eppn, 'GET');
            $client->setHeaders(['Authorization' => 'bearer '.$token]);
            $response        = $client->send();
            $responseContent = [];
            if ( ! empty($response)) {
                $responseContent = json_decode($response->getContent(), true);
            }

            return $responseContent;
        }

        /**
         * @param $id
         * @param $eppn
         *
         * @return array|mixed
         */
        public function getTicketMessages($id, $eppn)
        {
            $token  = $this->getLoginToken();
            $client = $this->prepareRequest('/messages/'.$id.'?eppn='.$eppn, 'GET');
            $client->setHeaders(['Authorization' => 'bearer '.$token]);
            $response        = $client->send();
            $responseContent = [];
            if ( ! empty($response)) {
                $responseContent = json_decode($response->getContent(), true);
            }

            return $responseContent;
        }
    }