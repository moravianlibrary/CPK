<?php
/**
 * Shibboleth authentication module crafted to manage user's connected identities using "eppn" column in user_card table.
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
 * @package  Authentication
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace CPK\Auth;

use VuFind\Exception\Auth as AuthException, CPK\Db\Row\User as UserRow, VuFind\Auth\Shibboleth as Shibboleth, VuFind\Db\Row\UserCard;
use VuFind\Cookie\CookieManager;
use CPK\Exception\TermsUnaccepted;
use Zend\ServiceManager\ServiceManager;
use CPK\Db\Table\EmailDelayer;
use CPK\Db\Table\EmailTypes;
use CPK\Db\Table\NotificationTypes;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package Authentication
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class ShibbolethIdentityManager extends Shibboleth
{

    /**
     * This is configuration filename without ini ext to use for configuration of all the IdPs we support.
     *
     * It is actually not only used in loading this config, but also in outputting the right filename on
     * AuthException when is config validation about to be done.
     *
     * @var const CONFIG_FILE_NAME
     */
    const CONFIG_FILE_NAME = "shibboleth";

    /**
     * This is TAG used to retrieve token from user's cookie while connecting with another account.
     *
     * @var const CONSOLIDATION_TOKEN_TAG
     */
    const CONSOLIDATION_TOKEN_TAG = "__connAcc";

    /**
     * Holds how long does it take to expire an consolidation token
     *
     * @var const int
     */
    const CONSOLIDATION_TOKEN_EXPIRATION = 900;

    /**
     * Flag for having the user's consolidation token set as a cookie already - just in case
     * there is an attempt to create another one before the cookie get propagated to user's browser
     *
     * @var bool
     */
    protected $consolidationCookieSet = false;

    /**
     * Private CookieManager instance
     *
     * @var CookieManager
     */
    protected $cookieManager = null;

    /**
     * This is key in $_SERVER how it Shibooleth SP returns for entityID of IdP user used to log in.
     *
     * @var const SHIB_IDENTITY_PROVIDER_ENV
     */
    const SHIB_IDENTITY_PROVIDER_ENV = 'Shib-Identity-Provider';

    /**
     * This is key in $_SERVER how it Shibooleth SP returns for Shibboleth assertion count used to
     * determine count of assertions Shibboleth SP used to be able of theoretically endless iteration
     * over those assertions.
     *
     * @var const SHIB_ASSERTION_COUNT_ENV
     */
    const SHIB_ASSERTION_COUNT_ENV = 'Shib-Assertion-Count';

    /**
     * This value stores configuration for showing assertions passed by Shibboleth SP.
     *
     * @var boolean shibAssertionExportEnabled
     */
    protected $shibAssertionExportEnabled = false;

    /**
     * It's value must match the separator MultiBackend driver used to explode() cat_username.
     *
     * @var const SEPARATOR
     */
    const SEPARATOR = ".";

    /**
     * It's value is same as static::SEPARATOR, but regex ready.
     *
     * @var const SEPARATOR_REGEXED
     */
    const SEPARATOR_REGEXED = "\\.";

    /**
     * Service locator to retrieve services dynamically on-demand
     *
     * @var ServiceManager serviceLocator
     */
    protected $serviceLocator = null;

    /**
     * This is a standalone file with filename shibboleth.ini in localconfig/config/vufind directory
     *
     * @var \Zend\Config\Config shibbolethConfig
     */
    protected $shibbolethConfig = null;

    /**
     * Holds User tableGateway to store and retrieve data from there.
     *
     * @var \CPK\Db\Table\User $userTableGateway
     */
    protected $userTableGateway = null;

    /**
     * This is array of attributes which $this->authenticate() method should check for.
     *
     * WARNING: can contain only such attributes, which are writeable to user table!
     *
     * @var array attribsToCheck
     */
    protected $attribsToCheck = array(
        'cat_username',
        'college',
        'major'
    );

    /**
     * This is either false (if not set at all), or contains
     * array of eppn scopes of institutes, with which can user
     * connect more than once.
     *
     * @var array $canConsolidateMoreTimes
     */
    protected $canConsolidateMoreTimes = null;

    /**
     * This is basically array of entityIds which does support SingleLogout service.
     *
     * @var array $eidsSupportingSLO
     */
    protected $eidsSupportingSLO = [];

    /**
     * Session container
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * Notifications handler
     *
     * @var \CPK\Notifications\NotificationsHandler
     */
    protected $notificationsHandler;

    public function __construct(ServiceManager $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        $this->userTableGateway = $serviceLocator->get('VuFind\DbTablePluginManager')->get('user');
        $this->cookieManager = $serviceLocator->get('VuFind\CookieManager');

        $configLoader = $serviceLocator->get('VuFind\Config');

        $this->shibbolethConfig = $configLoader->get(static::CONFIG_FILE_NAME);

        if (empty($this->shibbolethConfig)) {
            throw new AuthException("Could not load " . static::CONFIG_FILE_NAME . ".ini configuration file.");
        }

        // Set up session:
        $this->session = new \Zend\Session\Container('Account');

        $this->notificationsHandler = $serviceLocator->get('CPK\NotificationsHandler');
    }

    public function authenticate($request, UserRow $userToConnectWith = null)
    {
        unset($request);

        $entityId = $this->fetchCurrentEntityId();
        $config = null;
        $prefix = null;

        $loggedWithKnownLibraryEntityId = false;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == $configuration['entityId']) {
                $config = $configuration;
                $prefix = $name;

                $loggedWithKnownLibraryEntityId = !empty($config['cat_username']);

                $homeLibrary = $loggedWithKnownLibraryEntityId ? $prefix : 'Dummy';
                break;
            }
        }
        if ($config == null) {
            if (isset($this->shibbolethConfig['default'])) {
                $config = $this->shibbolethConfig['default'];
                $homeLibrary = $prefix = 'Dummy';
            } else
                throw new AuthException('Recieved entityId was not found in ' . static::CONFIG_FILE_NAME . '.ini config nor default config part exists.');
        }
        $eppn = $this->getEduPersonPrincipalName($prefix, $entityId);
        $attributes = $this->fetchAttributes($config);

        if (!isset($attributes['cat_username']) && isset($config['ils_lookup']) && $config['ils_lookup']) {
            $userInfo = [
                'source'   => $prefix,
                'username' => $this->unscope($eppn)
            ];
            $driver = $this->serviceLocator->get('VuFind\ILSConnection')->getDriver();
            $attributes['cat_username'] = $driver->getUserLibraryIdentifier($userInfo);
            $homeLibrary = $prefix;
        }
        $homeLibrary = isset($attributes['cat_username']) ? $homeLibrary : 'Dummy';

        // Store eppn in session for required logging-off logic
        $this->session->eppnLoggedInWith = $eppn;

        // EID for connecting identities
        $this->session->eidLoggedInWith = $entityId;

        // modification for GDPR - do not store last name and first name in DB
        $userInfo = [];
        if (isset($_SERVER['givenName'])) {
            $userInfo['firstname'] = $_SERVER['givenName'];
        }
        if (isset($_SERVER['sn'])) {
            $userInfo['lastname'] = $_SERVER['sn'];
        }
        if (isset($_SERVER['email'])) {
            $userInfo['email'] = $_SERVER['email'];
        }
        $this->session->userInfo = $userInfo;

        // Get UserRow by checking for known eppn
        $currentUser = $this->userTableGateway->getUserRowByEppn($eppn);

        // Now we need to know if there is a request to connect two identities
        $connectIdentities = $userToConnectWith !== null;
        if ($connectIdentities) {

            if ($currentUser !== false && $currentUser->id === $userToConnectWith['id'])
                throw new AuthException($this->translate("You already have this identity connected"));

            if ($loggedWithKnownLibraryEntityId && ! empty($attributes['cat_username'])) {

                $updateUserRow = true;

                // Set here the prefix to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $homeLibrary . static::SEPARATOR . $attributes['cat_username'];
            } else {
                // We now detected unkown library entityID - this identity will be Dummy
                $updateUserRow = false;

                // We can't store current $prefix to home_library as it always needs to be Dummy,
                // so we store the information about $prefix in cat_username unscoped
                $attributes['cat_username'] = $homeLibrary . static::SEPARATOR . $prefix;
            }

            if ($currentUser === false) {

                if (! isSet($attributes['email']))
                    $attributes['email'] = null;

                    // We now detected user has no entry with current eppn in our DB, thus append new libCard
                $userToConnectWith->createLibraryCard($attributes['cat_username'], $homeLibrary, $eppn, null, $this->canConsolidateMoreTimes);
            } else {
                // We now detected user has two entries in our user table, thus we need to merge those

                // We will always keep the one account created as the first
                $switchRoles = $currentUser->id < $userToConnectWith->id;

                if ($switchRoles) {
                    $tmp = $currentUser;
                    $currentUser = $userToConnectWith;
                    $userToConnectWith = $tmp;
                    unset($tmp);
                }

                $this->transferLibraryCards($currentUser, $userToConnectWith);

                // We need to check, if there doesn't exist library card with the same institution to update it
                $this->updateIdentity($userToConnectWith, $attributes['cat_username'], $homeLibrary, $eppn);

                $this->userTableGateway->mergeUserInAllTables($currentUser, $userToConnectWith);
            }

            if ($updateUserRow) {
                $userToConnectWith = $this->updateUserRow($userToConnectWith, $attributes);
            }

            $currentUser = $userToConnectWith;
        } else { // Being here means there is no other identity to connect with - regular login

            // If there was no User found, create one
            if (! $currentUser) {

                // eppn will be user's username
                $currentUser = $this->userTableGateway->createRowForUsername($eppn);

                $userRowCreatedRecently = true;
            } else
                $userRowCreatedRecently = false;

            if ($loggedWithKnownLibraryEntityId && ! empty($attributes['cat_username'])) {

                // Set here the homeLibrary to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $homeLibrary . static::SEPARATOR . $attributes['cat_username'];

                // Did the userRow exist before? ..
                if (! $userRowCreatedRecently) {

                    $wasDummyBefore = $currentUser->home_library === 'Dummy';

                    // We need to check, if there doesn't exist library card with the same institution to update it
                    if (! $wasDummyBefore) {
                        // We should check, if we have correct cat_username & home_library ...
                        $this->updateIdentity($currentUser, $attributes['cat_username'], $homeLibrary, $eppn);
                    } else {
                        // IdP finally returned cat_username for this User .. update proprietary libCard
                        $currentUser->upgradeLibraryCardFromDummy($eppn, $attributes['cat_username'], $homeLibrary);
                    }
                }
            } else {
                // We now detected unkown entityID - this identity will be Dummy

                // We can't store current $prefix to home_library as it always needs to be Dummy,
                // so we store the information about $prefix in cat_username unscoped
                $attributes['cat_username'] = $homeLibrary . static::SEPARATOR . $prefix;

                if (! $userRowCreatedRecently) {
                    $currentUser->updateLibCardIfNeeded($prefix, $homeLibrary, $attributes['cat_username']);
                }
            }

            if ($userRowCreatedRecently) {
                $currentUser = $this->createUser($currentUser, $attributes, $homeLibrary, $eppn);
            }
        }

        $this->createConsolidationCookie($currentUser->id);

        return $currentUser;
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url
     *            URL to redirect user to after logging out.
     *
     * @return string Redirect URL (usually same as $url, but modified in
     *         some authentication modules).
     */
    public function logout($url)
    {
        // If single log-out is enabled, use a special URL:
        $logoutEndpoint = $this->config->Shibboleth->logout;

        if (isset($logoutEndpoint) && ! empty($logoutEndpoint)) {

            $returnTo = urlencode($url);

            $url = $logoutEndpoint . '?return=' . $returnTo;
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }

    /**
     * Validate configuration parameters.
     * This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        // Throw an exception if the required login setting is missing.
        $shib = $this->config->Shibboleth;

        if (! isset($shib->login)) {
            throw new AuthException('Shibboleth login configuration parameter is not set.');
        } elseif (isset($shib->getAssertion) && $shib->getAssertion == true) {
            $this->shibAssertionExportEnabled = true;
        }

        if (! isset($shib->target)) {
            throw new AuthException('Could not find the target configuration.');
        }

        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($name === 'Dummy') {
                throw new AuthException('Shibboleth config section cannot be called \'Dummy\', this name is reserved.');
            }

            if ($name !== 'main') {
                if (! isset($configuration['entityId'])) {
                    throw new AuthException("Shibboleth 'entityId' is missing in your " . static::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "'");
                }

                $entityId = $configuration['entityId'];

                if (isSet($configuration['logout']) && $configuration['logout'] === 'local')
                    $supportsSLO = false;
                else
                    $supportsSLO = true;

                if ($supportsSLO)
                    $this->eidsSupportingSLO[] = $entityId;

            } elseif ($name === 'main') {
                $this->canConsolidateMoreTimes = $this->shibbolethConfig->main->canConsolidateMoreTimes;

                if ($this->canConsolidateMoreTimes !== null)
                    $this->canConsolidateMoreTimes = $this->canConsolidateMoreTimes->toArray();
            }
        }

        if (empty($this->canConsolidateMoreTimes))
            $this->canConsolidateMoreTimes = false;
    }

    /**
     * Checks if current User's IdP supports SLO (SingleLogoutService).
     *
     * Returns true if current User's IdP supports SLO.
     *
     * Note that this is configurable via shibboleth.ini in each section you can write:
     *
     * logout = local
     *
     * To make that particular entityId perform only local logout when consolidating identities
     *
     * @return boolean $supportsSLO
     */
    public function supportsSLO()
    {
        $currentEntityId = $this->fetchCurrentEntityId();

        return in_array($currentEntityId, $this->eidsSupportingSLO);
    }

    /**
     * Connects current logged in identity with the one provided in User's cookie
     * of consolidation token.
     *
     * @throws AuthException
     * @return UserRow $userRow
     */
    public function consolidateIdentity()
    {
        $token = $this->getConsolidationTokenFromCookie();

        if (empty($token))
            throw new AuthException($this->translate('No token recieved after logging with another account'));

        $userToConnectWith = $this->userTableGateway->getUserFromConsolidationToken($token, self::CONSOLIDATION_TOKEN_EXPIRATION);

        if (! $userToConnectWith) {

            // unset the cookie
            setcookie(static::CONSOLIDATION_TOKEN_TAG, null, - 1, '/');

            throw new AuthException($this->translate('The consolidation has expired. Please authenticate again.'));
        }

        return $this->authenticate(null, $userToConnectWith);
    }

    /**
     * Returns account consolidation redirect url where should be user
     * redirected to successfully consolidate another identity.
     *
     * It also handles generating & saving token to verify User's identity after connection
     * from another identity to merge those identities.
     *
     * It also saves this token
     * to session table where "data" column has value of user's row id from user table.
     *
     * @param number $userRowId
     *
     * @return string $accountConsolidationUrl
     * @throws AuthException
     */
    public function getAccountConsolidationUrl($userRowId, $targetEid)
    {
        // Create & write token to DB & user's cookie
        if (! $this->consolidationCookieSet && ! isset($_COOKIE[static::CONSOLIDATION_TOKEN_TAG])) {
            $this->createConsolidationCookie($userRowId);
        }

        // Create redirection URL
        $hostname = $this->config->Site->url;

        if (substr($hostname, - 1) !== '/') {
            $hostname .= '/';
        }

        $target = $hostname . 'MyResearch/UserConnect';

        $entityId = $this->fetchCurrentEntityId();
        $target .= '?eid=' . urlencode($entityId);

        $loginRedirect = $this->config->Shibboleth->login . '?forceAuthn=1&target=' . urlencode($target);

        if (! empty($targetEid))
            $loginRedirect .= '&entityID=' . urlencode($targetEid);

        if ($this->supportsSLO()) {
            return $this->config->Shibboleth->logout . '?return=' . urlencode($loginRedirect);
        } else
            return $loginRedirect;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).
     * Returns false when no session initiator is needed.
     *
     * @param string $entityId
     *
     * @return mixed bool|string
     */
    public function getSessionInitiatorForEntityId($entityId)
    {
        $config = $this->getConfig();

        $hasControllerAttached = preg_match("/\/[^\/]+\//", $_SERVER['REQUEST_URI']);

        $shibTarget = isset($_SERVER['SSL_SESSION_ID']) ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . $hasControllerAttached ? $_SERVER['REQUEST_URI'] : '/Search/Home';

        $append = (strpos($shibTarget, '?') !== false) ? '&' : '?';

        $initiator = $config->Shibboleth->login . '?target=' . urlencode($shibTarget) . urlencode($append . 'auth_method=Shibboleth');

        if (! empty($entityId))
            $initiator .= '&entityID=' . urlencode($entityId);

        return $initiator;
    }

    /**
     * This function returns array of assertions Shibboleth SP sent us.
     * If the PHP script was unable to
     * load contents of provided links, then each array element contains the link to parse the assertion.
     *
     * @return array assertions
     */
    public function getShibAssertions()
    {
        if (! isset($_SERVER[static::SHIB_ASSERTION_COUNT_ENV]))
            return [];

        $count = intval($_SERVER[static::SHIB_ASSERTION_COUNT_ENV]);

        $assertions = array();

        for ($i = 0; $i < $count; ++ $i) {
            $shibAssertionEnv = $this->getShibAssertionNumberEnv($i + 1);

            $assertions[$i] = $_SERVER[$shibAssertionEnv];

            if ($assertions[$i] == null) {
                unset($assertions[$i]);
            } else {
                $url = $assertions[$i];

                if (strpos($url, 'https:') !== false) {
                    $ch = curl_init();
                    // Running on localhost means we can trust whatever SSL it has .. (Shibboleth SP should run on loc)
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_URL, $url);

                    $contents = curl_exec($ch);
                    curl_close($ch);
                } else
                    $contents = file_get_contents($assertions[$i]);

                    // If we have parsed contents successfully, set it to assertion
                    // If not, then leave there the link to find out what is the problem
                if (! empty($contents)) {
                    $assertions[$i] = $contents;
                }
            }
        }

        return $assertions;
    }

    /**
     * Returns true if the assertion export is enabled in config.ini [Shibboleth] section.
     *
     * You can enable it by typing "getAssertion = 1" to [Shibboleth] config section. Note that
     * it has to be enabled in apache configuration too.
     *
     * @return boolean shibAssertionExportEnabled
     */
    public function isShibAssertionExportEnabled()
    {
        return $this->shibAssertionExportEnabled;
    }

    /**
     * Marks library card for deletion so that it can be recovered easily.
     *
     * @param UserCard $userCard
     * @param boolean $shouldBeDeleted
     */
    public function setLibCardDeletionFlag(UserCard $userCard, $shouldBeDeleted)
    {
        $eppn = $userCard->eppn;

        if ($shouldBeDeleted) {
            $userCard->eppn = "DEL_$eppn";
        } elseif (substr($eppn, 0, 4) === 'DEL_') {
            $userCard->eppn = substr($eppn, 4);
        }

        $userCard->save();
    }

    /**
     * Creates User entry in user table & returns
     * UserRow of created user.
     *
     * @param UserRow $userRow
     * @param array $attributes
     * @param string $prefix
     * @param string $eppn
     * @return UserRow $createdUser
     */
    protected function createUser(UserRow $userRow, $attributes, $prefix, $eppn)
    {
        $usernameRank = $this->userTableGateway->getUsernameRank($eppn);

        // This username will never change, at least until is user row deleted
        $userRow->username = "$eppn;" . ++ $usernameRank;

        $userRow->home_library = $prefix;

        // Now we need to physically create record for this user to retrieve needed row id
        $userRow = $this->updateUserRow($userRow, $attributes);

        // Assign the user new library card
        $userRow->createLibraryCard($userRow->cat_username, $userRow->home_library, $eppn, null, $this->canConsolidateMoreTimes);

        return $userRow;
    }

    /**
     * Maps premapped attributes from shibboleth.ini particular section where is know-how for parsing
     * attributes the IdP returned.
     *
     * It basically returns array $attributes, which is later saved to 'user' table as current user.
     * There may be some minor modifications, e.g. to cat_username is appended institute delimited
     * by SEPARATOR const.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     * @param \Zend\Config\Config $config
     *            containing only array of attributes mapping from attribute-map.xml to user table in VuFind
     * @return array attributes
     */
    protected function fetchAttributes($config)
    {
        if (isSet($_SERVER['mail']))
            $_SERVER['email'] = $_SERVER['mail'];

        $attributes = array();
        foreach ($this->attribsToCheck as $attribute) {
            if (isset($config->$attribute)) {
                $key = $config->$attribute;
                $pattern = null;
                $value = null;
                if (strpos($key, '|') !== false) {
                    $keys = explode('|', $key);
                    foreach ($keys as $key) {
                        $key = trim($key);

                        if (isset($_SERVER[$key])) {
                            $value = explode(";", $_SERVER[$key])[0];
                            break;
                        }
                    }
                } elseif (strpos($key, ',') !== false) {
                    list ($key, $pattern) = explode(',', $key, 2);
                    $pattern = trim($pattern);
                }

                if (isset($_SERVER[$key])) {
                    $value = explode(";", $_SERVER[$key])[0];

                    if ($pattern != null && $value != null) {
                        $matches = array();
                        preg_match($pattern, $value, $matches);
                        $value = $matches[1];
                    }

                    // cat_username needs to contain agencyId in some cases for XCNCIP2 valid LookupUser message
                    if ($attribute === 'cat_username' && isset($config['changeAgencyIdSeparator']) && $config['changeAgencyIdSeparator'] instanceof \Zend\Config\Config) {
                        $agencyIdSeparators = $config['changeAgencyIdSeparator']->toArray();

                        $currentSeparator = key($agencyIdSeparators);
                        $desiredSeparator = $agencyIdSeparators[$currentSeparator];

                        $catUsernameSplitted = explode($currentSeparator, $value);

                        if (isset($config['invertAgencyIdWithUsername']) && $config['invertAgencyIdWithUsername'])
                            $catUsernameSplitted = array_reverse($catUsernameSplitted);

                        $value = implode($desiredSeparator, $catUsernameSplitted);
                    }

                    $attributes[$attribute] = $value;
                }
            } elseif (isset($_SERVER[$attribute])) {
                $attributes[$attribute] = $_SERVER[$attribute];
            }
        }

        return $attributes;
    }

    protected function fetchCurrentEntityId()
    {
        return isset($_SERVER[static::SHIB_IDENTITY_PROVIDER_ENV]) ? $_SERVER[static::SHIB_IDENTITY_PROVIDER_ENV] : null;
    }

    /**
     * Gets the mandatory eduPersonPrincipalName.
     *
     * Throws an exception if not provided by the IdP & sends an email to appropriate technical contact
     *
     * @param string $source
     * @param string $entityId
     *
     * @throws AuthException
     * @return string $eppn
     */
    protected function getEduPersonPrincipalName($source, $entityId)
    {
        if (isset($_SERVER['Meta-technicalContact']))
            $technicalContacts = $_SERVER['Meta-technicalContact'];
        else
            throw new AuthException('Could not find the technical contact in metadata, or Shibboleth SP is incorrectly configured.');

        $eppnExists = isset($_SERVER['eduPersonPrincipalName']);

        if ($eppnExists) {

            $this->clearNoEppnEmailAttempts($technicalContacts, $source);

            return explode(";", $_SERVER['eduPersonPrincipalName'])[0];
        } else {

            $mailer = $this->serviceLocator->get('CPK\Mailer');

            $mailer instanceof \CPK\Mailer\Mailer;

            $testingUrl = $this->getSessionInitiatorForEntityId($entityId);

            $templateVars = [
                'entityId' => $entityId,
                'testingUrl' => $testingUrl
            ];

            $renderer = $this->serviceLocator->get('ViewRenderer');

            $mailer->sendEppnMissing($technicalContacts, $source, $renderer, $templateVars);

            throw new AuthException('IdP "' . $source . '" didn\'t provide eduPersonPrincipalName attribute.');
        }
    }

    /**
     * Same as EmailDelayer's clearAttempts method.
     *
     * @param string $technicalContacts
     */
    protected function clearNoEppnEmailAttempts($technicalContacts, $homeLibrary)
    {
        $this->serviceLocator->get('VuFind\DbTablePluginManager')
            ->get('email_delayer')
            ->clearAttempts($technicalContacts, $homeLibrary, EmailTypes::IDP_NO_EPPN);
    }

    /**
     * Returns 32-length string token.
     *
     * @return string Token
     */
    protected function generateToken()
    {
        return chr(mt_rand(97, 122)) . chr(mt_rand(97, 122)) . substr(md5(time()), 3) . chr(mt_rand(97, 122));
    }

    /**
     * Returns & deletes user's token from cookie.
     *
     * @return string Token
     */
    protected function getConsolidationTokenFromCookie()
    {
        if (! isSet($_COOKIE[static::CONSOLIDATION_TOKEN_TAG]))
            return null;

        $token = $_COOKIE[static::CONSOLIDATION_TOKEN_TAG];
        // unset the cookie ...
        setcookie(static::CONSOLIDATION_TOKEN_TAG, null, - 1, '/');
        return $token;
    }

    protected function getShibAssertionNumberEnv($i)
    {
        if ($i < 10) {
            return 'Shib-Assertion-0' . $i;
        } else {
            return 'Shib-Assertion-' . $i;
        }
    }

    /**
     * This method deletes all cards within supplied User $from & saves those to User $into.
     *
     * @param UserRow $from
     * @param UserRow $into
     *
     * @return void
     */
    protected function transferLibraryCards(UserRow $from, UserRow $into)
    {
        $libCards = $from->getAllUserLibraryCards();
        foreach ($libCards as $libCard) {

            // Because eppn column is across user_card table unique, we need to mark it to deletion
            // in order to be able of recreating it after any failure while transferring
            $this->setLibCardDeletionFlag($libCard, true);

            try {
                $into->createLibraryCard($libCard->cat_username, $libCard->home_library, $libCard->eppn, $libCard->card_name, $this->canConsolidateMoreTimes);
            } catch (AuthException $e) {
                // Something went wrong - restore libCard flagged for deletion
                $this->setLibCardDeletionFlag($libCard, false);
                throw $e;
            }

            $from->deleteLibraryCardRow($libCard, false);
        }
    }

    /**
     * Creates an consolidation token.
     *
     * It also distributes it to database & user's cookie. It is paired with current user's account
     *
     * @param number $userRowId
     * @throws AuthException
     */
    protected function createConsolidationCookie($userRowId)
    {
        $token = $this->generateToken();

        $currTime = time();
        $expires = $currTime + static::CONSOLIDATION_TOKEN_EXPIRATION;

        $this->cookieManager->set(static::CONSOLIDATION_TOKEN_TAG, $token, $expires);

        $tokenCreated = $this->userTableGateway->saveUserConsolidationToken($token, $userRowId);

        if (! $tokenCreated)
            throw new AuthException('Could not create consolidation token entry into session table');

        $this->consolidationCookieSet = true;
    }

    /**
     * Checks for User's libraryCards, whether there is any identity with the same eppn & if it is,
     * it is then checked if cat_username or home_library has changed, which results in an update .
     *
     *
     * @param UserRow $user
     * @param string $cat_username
     * @param string $source
     * @param string $eppn
     *
     * @return void
     */
    protected function updateIdentity(UserRow $user, $cat_username, $source, $eppn)
    {
        $resultSet = $user->getAllUserLibraryCards();

        $libCardToSave = false;
        foreach ($resultSet as $libraryCard) {

            // Find the corresponding libraryCard which should be updated ..
            if ($libraryCard->eppn === $eppn) {

                // Now check if the cat_username has changed ..
                if ($libraryCard->cat_username !== $cat_username) {

                    // Set new cat_username
                    $libraryCard->cat_username = $cat_username;

                    $libCardToSave = $libraryCard;
                }

                // To be sure, also update home_library if neccessary
                if ($libraryCard->home_library !== $source) {
                    $libraryCard->home_library = $source;

                    if (! $libCardToSave)
                        $libCardToSave = $libraryCard;
                }

                break;
            }
        }

        // Perform an SQL query only if the eppn was found within user's cards
        if ($libCardToSave instanceof UserCard) {
            $libCardToSave->save();

            // We need to update user table with the new cat_username in case User did have only dummy accounts consolidated
            $user->activateBestLibraryCard();
        }
    }

    /**
     * Updates UserRow $user with $attributes in DB.
     *
     * @param UserRow $userRow
     * @param array $attributes
     * @return UserRow $updatedUser
     */
    protected function updateUserRow(UserRow $userRow, $attributes)
    {
        foreach ($attributes as $key => $value) {
            $userRow->$key = $value;
        }

        if (! isset($userRow->email))
            $userRow->email = '';

            // Save/Update user in database
        $userRow->save();

        return $userRow;
    }

    protected function unscope($value)
    {
        return substr($value, 0, strrpos($value, "@"));
    }

}
