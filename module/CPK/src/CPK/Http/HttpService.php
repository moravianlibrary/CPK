<?php

/**
 * VuFind HTTP service class file.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind2
 * @package  Http
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-HTTP
 */

namespace CPK\Http;

use VuFindHttp\HttpService as HttpServiceBase;

/**
 * VuFind HTTP service.
 *
 * @category VuFind2
 * @package  Http
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-search-subsystem
 */
class HttpService extends HttpServiceBase
{
    /**
     * Proxify an existing client.
     *
     * Returns the client given as argument with appropriate proxy setup.
     *
     * @param \Zend\Http\Client $client  HTTP client
     * @param array             $options ZF2 ProxyAdapter options
     *
     * @return \Zend\Http\Client
     */
    public function proxify(\Zend\Http\Client $client, array $options = array())
    {
        if ($this->proxyConfig) {
            $host = $client->getUri()->getHost();
            if (! $this->isLocal($host)) {

                $socks5 = isset($this->proxyConfig['socks5']) && $this->proxyConfig['socks5'];

                if ($socks5) {
                    $adapter = new \Zend\Http\Client\Adapter\Curl();
                    $host = $this->proxyConfig['proxy_host'];
                    $port = $this->proxyConfig['proxy_port'];

                    $adapter->setCurlOption(CURLOPT_FOLLOWLOCATION, true);
                    $adapter->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    $adapter->setCurlOption(CURLOPT_PROXY, $host);

                    if (isset($port) && !empty($port))
                        $adapter->setCurlOption(CURLOPT_PROXYPORT, $port);

                    $client->setAdapter($adapter);

                } else {
                    $adapter = new \Zend\Http\Client\Adapter\Proxy();
                    $options = array_replace($this->proxyConfig, $options);
                    $adapter->setOptions($options);
                    $client->setAdapter($adapter);
                }
            }
        }
        return $client;
    }

}
