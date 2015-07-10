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
use ZfcRbacTest\Guard\ProtectionPolicyTraitTest;

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

    /**
     * This constant contains string which has to be replaced in loaded JSON attribute
     * definition with desired value - usually it replaces with array, thus it needs
     * doublequotes.
     *
     * @var const ATTR_DEF_VALUE_TO_REPLACE
     */
    const ATTR_DEF_VALUE_TO_REPLACE = '"VALUE_HERE"';

    /**
     * This constant contains string which is used to append onto Perun Kerberos url
     * to create valid url of setAttribute Perun method.
     *
     * @var const URL_PATH_SET_ATTRIBUTE
     */
    const URL_PATH_SET_ATTRIBUTE = "/json/attributesManager/setAttribute";

    /**
     * This string contains whole JSON definition of attribute in Perun of type ArrayList
     * we use to store LibraryCards into.
     *
     * @var string attributeDefinition
     */
    protected $attributeDefinition;

    /**
     * This boolean holds information about Perun connectivity.
     * It is set to true only if is in [Perun] section of
     * config.ini set "cantContactPerun = true".
     *
     * If you set this, then this VuFind configuration will not even try to contact Perun.
     * There will be random library cards created to be able of doing custom design
     * or new features without access to Perun.
     *
     * @var boolean cantContactPerun
     */
    protected $cantContactPerun = false;

    /**
     * This config contains [Perun] section from config.ini.
     *
     * @var \Zend\Config\Config perunConfig
     */
    protected $perunConfig;

    /**
     * This string is basically URL to registrar of configured VO.
     *
     * @var string registrar
     */
    protected $registrar;

    /**
     * Each element of this array specifies which attribute must be set in [Perun] section
     * of config.ini for IdentityProvider to work properly.
     *
     * @var array(string) requiredConfigVariables
     */
    protected $requiredConfigVariables = array(
        "registrar",
        "virtualOrganization",
        "loginEndpoint",
        "kerberosRpc",
        "attrDefFilename",
        "voManagerLogin",
        "voManagerPassword"
    );

    /**
     * This string contains url of Shibboleth's target from config.ini [Shibboleth] section
     * to know where return client after all the redirections.
     *
     * @var string shibbolethTarget
     */
    protected $shibbolethTarget;

    public function __construct()
    {}

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
                } elseif ($reqConf === 'registrar' && strpos($this->perunConfig->$reqConf, "/?vo=") === false) {
                    // Attribute registrar doesn't have VO specified in GET param, thus we will set it now
                    $this->registrar = $this->perunConfig->registrar . "/?vo=" . $this->perunConfig->virtualOrganization;
                }
            }

            $filename = $_SERVER['VUFIND_LOCAL_DIR'] . "/config/vufind/" . $this->perunConfig->attrDefFilename;
            $this->attributeDefinition = file_get_contents($filename);

            if (empty($this->attributeDefinition)) {
                throw new AuthException("Could not locate JSON definition of attribute for Perun at location " . $filename);
            }
        }

        if (! isset($config->Shibboleth->target)) {
            throw new AuthException("IdentityResolver could not load 'target' attribute from [Shibboleth] section in config.ini");
        }

        $this->shibbolethTarget = $config->Shibboleth->target;

        // Shibboleth->login is checked in PerunShibboleth
        $this->shibbolethLogin = $config->Shibboleth->login;

        if (isset($this->perunConfig->cantContactPerun)) {
            $this->cantContactPerun = $this->perunConfig->cantContactPerun;
        }
    }

    /**
     * Checks if logged in user has to be registered to Perun's VO
     * or no.
     * If user has no PerunId yet, returns yes also, because
     * registering user to VO automatically creates new Perun Id.
     *
     * @return boolean $shouldBeRegisteredToPerun
     */
    public function shouldBeRegisteredToPerun()
    {
        $perunId = $_SERVER['perunUserId'];

        if ($this->cantContactPerun)
            return false;

            // Empty perunId means user has no record in Perun or we didn't contact AA after user's registery
        return empty($perunId) || ! $this->isVoMember();
    }

    /**
     * Checks if logged in user is member of VO specified in config.
     *
     * @return boolean $isVoMember
     */
    protected function isVoMember()
    {
        $voName = $this->perunConfig->virtualOrganization;

        $voMemberships = split(";", $_SERVER['perunVoName']);

        return array_search($voName, $voMemberships) !== false;
    }

    /**
     * Returns predefined attribute definition with desired value to post into Perun with it's API.
     *
     * @return string attributeDefinitionWithCustomValue
     */
    protected function getAttributeWithValue($value)
    {
        $attributeDefinitionWithCustomValue = str_replace($this::ATTR_DEF_VALUE_TO_REPLACE, $value, $this->attributeDefinition);
        return $attributeDefinitionWithCustomValue;
    }

    /**
     *
     * Gets Random identites to substitute for Perun implementation
     *
     * @param string $sigla
     * @param string $userId
     * @return array $institutes
     */
    protected function getDummyContent($cat_username)
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
     * Return url where should be user returned after all redirections are done.
     *
     * It actually appends ?auth_method=Shibboleth&redirected_from= $redirectedFrom, which
     * is useful for later determining user's state of Perun registery.
     *
     * @param string $redirectedFrom
     * @return string targetToReturn
     */
    protected function getShibbolethTargetWithRedirectionParam($redirectedFrom)
    {
        // Append GET param "redirected_from" to know afterwards about this redirection
        $append = (strpos($this->shibbolethTarget, '?') !== false) ? '&' : '?';
        return $this->shibbolethTarget . $append . "auth_method=Shibboleth" . "&redirected_from=" . $redirectedFrom;
    }

    /**
     * Updates user's library cards in Perun only if current cat_username is not in array of institutes
     *
     * Note that array of institutes is actually array of all user's cat_username stored in library cards.
     *
     * If you can't contact Perun, it will return dummy institutes.
     *
     * @param string $cat_username
     * @param array $institutes
     * @return array institutes
     */
    public function updateUserInstitutesInPerun($cat_username, array $institutes)
    {
        if ($this->cantContactPerun) {
            return $this->getDummyContent($cat_username);
        }

        // Push current institutes only if those does not contain current cat_username,
        // which is possible only once - after user was registered into Perun ...
        if (! in_array($cat_username, $institutes)) {

            if (empty($institutes[0])) { // it is empty if no libraryIds was returned by AA
                $institutes[0] = $cat_username;
            } else {
                array_push($institutes, $cat_username);
            }

            $this->pushLibraryCardsToPerun($institutes);
        }
        return $institutes;
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
     */
    public function redirectUserToRegistrar($entityId)
    {
        $targetToReturn = $this->getShibbolethTargetWithRedirectionParam("registrar");

        // Whole url should look similar to this samle:
        // "PerunShibboleth.sso/Login?entityID=...&target=...urlencode(linkToRegister?vo=cpk&targetNew=...&targetExtended=...)";

        $redirectionUrl = $this->perunConfig->loginEndpoint . "?entityID=" . urlencode($entityId) . "&target=";

        // We know registrar contains "?" - now we can append escaped "&"
        $redirectionUrl .= urlencode($this->registrar . "&targetextended=" . urlencode($targetToReturn) . "&targetnew=" . urlencode($targetToReturn));

        header('Location: ' . $redirectionUrl, true, 307);
        die();
    }

    /**
     *
     * Redirects user to Perun's consolidator through Perun's SP Login endpoint with current logged in entityID.
     *
     * This workaround is required in order to prevent user from choosing another institute.
     *
     * Be aware of this method as the php thread dies.
     *
     * @param string $entityId
     */
    public function redirectUserToConsolidator($entityId)
    {

        // TODO: Create custom consolidator with AJAX calls
        // TODO: Implement redirect to consolidator & add config validation
        throw new AuthException("Consolidator redirection not implemented yet");

        $consolidator = ""; // TODO

        $targetToReturn = $this->getShibbolethTargetWithRedirectionParam("consolidator");

        $redirectionUrl = $this->perunConfig->loginEndpoint . "?entityID=" . urlencode($entityId) . "&target=";

        // FIXME: set the target so that it works with custom created consolidator
        $redirectionUrl .= urlencode($consolidator . "?target=" . $targetToReturn);

        header('Location: ' . $redirectionUrl, true, 307);
        die();
    }

    /**
     * Redirects user to local Service Provider with logged in entityId to refetch his attributes.
     *
     * This is most needed after was user registered to Perun & we need to know his perunId to push his libraryCards there.
     *
     * Be aware of this method as the php thread dies.
     *
     * @param string $entityId
     * @param string $redirectedFrom
     * @return Nothing .. the thread dies
     */
    public function redirectUserToLoginEndpoint($entityId, $redirectedFrom)
    {
        $targetToReturn = $this->getShibbolethTargetWithRedirectionParam($redirectedFrom);

        $redirectionUrl = $this->shibbolethLogin . "?entityID=" . urlencode($entityId) . "&target=" . urlencode($targetToReturn);

        header('Location: ' . $redirectionUrl, true, 307);
        die();
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
     * @param int $timeout
     *            Timeout in seconds to wait for response from url
     * @return array( int statusCode, string bodyResponse )
     */
    public function sendJSONpostWithBasicAuth($url, $username, $password, $json, $timeout = 10)
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

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

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