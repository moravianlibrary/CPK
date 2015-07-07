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

/**
 * Class for resolving user's connected identities from Perun (https://github.com/CESNET/perun)
 *
 * It basically manages all the communication with Perun
 *
 * @category VuFind2
 * @package Perun
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class IdentityResolver
{

    const URL_PATH_SET_ATTRIBUTE = "/json/attributesManager/setAttribute";

    protected $perunConfig;

    protected $attributeDefinition;

    protected $requiredConfigVariables = array(
        "registrar",
        "loginEndpoint",
        "kerberosRpc",
        "attrDefFilename",
        "voManagerLogin",
        "voManagerPassword"
    );

    public function __construct()
    {}

    /**
     *
     * Gets Random identites to substitute for Perun implementation
     *
     * @param string $sigla
     * @param string $userId
     * @return array $institutes
     */
    public function getDummyContent($cat_username)
    {
        return array(
            $cat_username,
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
        } elseif ($this->perunConfig->validateConfig) {
            // Validation must be turned on by setting validateConfig = true
            foreach ($this->requiredConfigVariables as $reqConf) {
                if (empty($this->perunConfig->$reqConf)) {
                    throw new AuthException("Attribute '$reqConf' is not set in config.ini's [Perun] section");
                } elseif ($reqConf === 'registrar' && strpos($this->perunConfig->$reqConf, "/?vo=" === false)) {
                    // Attribute registrar must have VO specified in GET param ...
                    throw new AuthException("Attribute '$reqConf' does not contain 'vo' specification, e.g. 'registrar/?vo=cpk'");
                }
            }
        }
    }

    /**
     * Returns predefined attribute definition with desired value to post.
     *
     * @throws AuthException
     * @return string json
     */
    protected function getAttributeWithValue($value)
    {
        $filename = $_SERVER['VUFIND_LOCAL_DIR'] . "/config/vufind/" . $this->perunConfig->attrDefFilename;
        $jsonDef = file_get_contents($filename);

        if (! $jsonDef) {
            throw new AuthException("Could not locate JSON definition of attribute for Perun at location " . $filename);
        }

        return str_replace('"VALUE_HERE"', $value, $jsonDef);
    }

    /**
     *
     * Redirects user to Perun's registrar through Perun's SP Login endpoint with current logged in entityID.
     *
     * This workaround is required in order to prevent user from choosing another institute.
     *
     * Be aware of this method as the php thread dies.
     *
     * @param string $entityId
     * @param string $targetToReturn
     */
    public function registerUser($entityId, $targetToReturn)
    {
        $loginEndpoint = $this->perunConfig->loginEndpoint;

        $registrar = $this->perunConfig->registrar;

        // Append GET param "fromRegistrar" to know afterwards about this redirection
        $append = (strpos($targetToReturn, '?') !== false) ? '&' : '?';
        $targetToReturn .= $append . "from_registrar=1";

        // Whole url should look similar to this samle:
        // "PerunShibboleth.sso/Login?entityID=...&target=...urlencode(linkToRegister?vo=cpk&targetNew=...&targetExtended=...)";

        $redirectionUrl = $loginEndpoint . "?entityID=" . urlencode($entityId) . "&target=";

        // We know registrar contains "?" - now we can append escaped "&"
        $redirectionUrl .= urlencode($registrar . "&targetnew=$targetToReturn" . "&targetextended=$targetToReturn");

        header('Location: ' . $redirectionUrl, true, 307);
        die();
    }

    /**
     * Redirects user to local Service Provider with logged in entityId to refetch his attributes.
     *
     * This is most needed after was user registered to Perun & we need to know his perunId to push his libraryCards there.
     *
     * @param unknown $entityId
     * @param unknown $loginEndpoint
     * @param unknown $targetToReturn
     */
    public function refetchUser($entityId, $loginEndpoint, $targetToReturn)
    {
        // Append GET param "fromRegistrar" to know afterwards about this redirection
        $append = (strpos($targetToReturn, '?') !== false) ? '&' : '?';
        $targetToReturn .= $append . "auth_method=Shibboleth";

        $redirectionUrl = $loginEndpoint . "?entityID=" . urlencode($entityId) . "&target=" . urlencode($targetToReturn);

        header('Location: ' . $redirectionUrl, true, 307);
        die();
    }

    /**
     * Updates user's library cards in Perun only if current cat_username is not in array of institutes
     *
     * @param string $cat_username
     * @param array $institutes
     * @return array institutes
     */
    public function updateUserInstitutesInPerun($cat_username, array $institutes) {

        // Push current institutes only if those does not contain current cat_username,
        // which is possible only once - after user was registered into Perun ...

        if (! in_array($cat_username, $institutes)) {
            array_push($institutes, $cat_username);

            $this->pushLibraryCardsToPerun($institutes);
        }
        return $institutes;
    }

    /**
     * Updates user's libraryCards in Perun
     *
     * @param array $userLibraryIds
     */
    protected function pushLibraryCardsToPerun(array $userLibraryIds)
    {
        $attribute = $this->getAttributeWithValue(json_encode($userLibraryIds));

        $userId = $_SERVER['perunUserId'];

        $json = '{"user":' . $userId . ',"attribute":' . $attribute . '}';

        $url = $this->perunConfig->kerberosRpc . $this::URL_PATH_SET_ATTRIBUTE;

        $response = $this->sendJSONpost($url, $json);
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

    /**
     * Sends POST with application/json Content-Type & Auth-Basic from config to provided url.
     *
     * @param string $url
     * @param string $json
     * @throws AuthException
     * @return stdClass attributeDefinition
     */
    protected function sendJSONpost($url, $json)
    {
        list ($statusCode, $response) = $this->sendJSONpostWithBasicAuth($url, $this->perunConfig->voManagerLogin, $this->perunConfig->voManagerPassword, $json);
        if ($statusCode === 200) {
            return json_decode($response);
        } else {
            throw new AuthException("IdentityResolver was rejected with $statusCode status code. Check your credentials in config.ini");
        }
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

        // TODO: Shouldn't be the timeout set in config?
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