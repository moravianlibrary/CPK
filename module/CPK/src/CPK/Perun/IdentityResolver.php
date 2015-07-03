<?php

/**
 * Perun identity resolver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Perun
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Perun;

use VuFind\Exception\Auth as AuthException;
use CPK\Auth\PerunShibboleth;
use Zend\Session\Container as SessionContainer;
use VuFindSearch\Backend\Exception\BackendException;

/**
 * Class for resolving user's connected identities from Perun (https://github.com/CESNET/perun)
 *
 * @category VuFind2
 * @package Perun
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class IdentityResolver
{

    /**
     * Container for storing cached Perun data.
     *
     * @var SessionContainer
     */
    protected $session;

    const URL_PATH_GET_ATTR_DEF_BY_ID = "/json/attributesManager/getAttributeDefinitionById";

    protected $perunConfig;

    protected $attributeDefinition;

    protected $requiredConfigVariables = array(
        "registrar",
        "kerberosRpc",
        "targetNew",
        "targetExtended",
        "attributeNumberId",
        "voManagerLogin",
        "voManagerPassword"
    );

    public function __construct()
    {
        $this->session = new SessionContainer("IdentityResolver");
    }

    /**
     *
     * Gets Random identites to substitute for Perun implementation
     *
     * @param string $sigla
     * @param string $userId
     * @return array $institutes
     */
    public function getDummyContent($sigla, $userId)
    {
        return array(
            $sigla . PerunShibboleth::SEPARATOR . $userId,
            "mzk.70" . rand(0, 2),
            "xcncip2." . rand(3, 5),
            "xcncip2." . rand(7, 8),
            "xcncip2." . rand(9, 11)
        );
    }

    /**
     * Validate configuration parameters.
     * This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * This method is basically called by PerunShibboleth on it's own validateConfig call.
     *
     * @throws AuthException
     * @return void
     */
    public function validateConfig(\Zend\Config\Config $config)
    {
        $this->perunConfig = $config->Perun;

        if ($this->perunConfig == null) {
            throw new AuthException('[Perun] section not found in config.ini');
        }

        foreach ($this->requiredConfigVariables as $reqConf) {
            if (empty($this->perunConfig->$reqConf)) {
                throw new AuthException("Attribute '$reqConf' is not set in config.ini's [Perun] section");
            }
        }

        // Being here means we have all we need to initialize static variables
        $this->initializeStaticVariables();
    }

    protected function initializeStaticVariables()
    {
        // Aren't those in cache already?
        if (empty($attributeDefinition)) {
            $attributeDefinition = $this->getCache("attrDef");

            if (empty($attributeDefinition)) {
                $urlToRetrieveAttrDef = $this->perunConfig->kerberosRpc . $this::URL_PATH_GET_ATTR_DEF_BY_ID;

                $data = '{"id":' . $this->perunConfig->attributeNumberId . '}';

                list ($statusCode, $response) = $this->sendJSONpost($urlToRetrieveAttrDef, $data);

                if ($statusCode === 200) {
                    $attributeDefinition = json_decode($response);

                    $this->setCache("attrDef", $attributeDefinition);
                } else {
                    // Cannot throw anything .. nothing would catch it now
                    $_ENV['exception'] = "IdentityResolver could not fetch attribute definition. Rejected with $statusCode status code.";
                }
            }
        }
    }

    /**
     * Helper function for fetching cached data.
     * Data is cached until it is deleted
     *
     * @param string $id
     *            Cache entry id
     *
     * @return mixed|null Cached entry or null if not cached
     */
    protected function getCache($id)
    {
        if (isset($this->session->cache[$id])) {
            $item = $this->session->cache[$id];
            return $item['entry'];
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     * Data is cached until it is deleted
     *
     * @param string $id
     *            Cache entry id
     * @param mixed $entry
     *            Entry to be cached
     *
     * @return void
     */
    protected function setCache($id, $entry)
    {
        if (! isset($this->session->cache)) {
            $this->session->cache = [];
        }

        $this->session->cache[$id] = [
            'entry' => $entry
        ];
    }

    public function getPerunRegistrarLink()
    {
        return $this->perunConfig->registrar;
    }

    /**
     * Sends POST with application/json Content-Type & Auth-Basic from config to provided url.
     *
     * @param string $url
     * @param string $json
     * @return array( int statusCode, string bodyResponse )
     */
    protected function sendJSONpost($url, $json)
    {
        return $this->sendJSONpostWithBasicAuth($url, $this->perunConfig->voManagerLogin, $this->perunConfig->voManagerPassword, $json);
    }

    /**
     * Sends POST with application/json Content-Type & Auth-Basic to provided url.
     *
     * @param string $url
     * @param string $username
     * @param string $password
     * @param string $json
     * @return array( int statusCode, string bodyResponse )
     */
    public function sendJSONpostWithBasicAuth($url, $username, $password, $json)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ));
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array(
            $statusCode,
            $response
        );
    }
}