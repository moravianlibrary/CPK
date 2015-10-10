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

use VuFind\Exception\Auth as AuthException, CPK\Db\Table\User as UserTable, CPK\Db\Row\User as UserRow, VuFind\Auth\Shibboleth as Shibboleth, VuFind\Exception\VuFind\Exception as VuFindException, VuFind\Db\Row\UserCard;

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
     * @var CONSOLIDATION_TOKEN_TAG
     */
    const CONSOLIDATION_TOKEN_TAG = "__connAcc";

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
     * This is a standalone file with filename shibboleth.ini in localconfig/config/vufind directory
     *
     * @var \Zend\Config\Config shibbolethConfig
     */
    protected $shibbolethConfig = null;

    /**
     * Holds User tableGateway to store and retrieve data from there.
     *
     * @var UserTable $userTableGateway
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
        'email',
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
     * This is either false (if not set at all), or contains
     * array of entityIds which support SingleLogout service.
     *
     * @var array $workingLogoutEntityIds
     */
    protected $workingLogoutEntityIds = null;

    public function __construct(\VuFind\Config\PluginManager $configLoader,
        UserTable $userTableGateway)
    {
        $this->shibbolethConfig = $configLoader->get(static::CONFIG_FILE_NAME);

        if (empty($this->shibbolethConfig)) {
            throw new AuthException(
                "Could not load " . static::CONFIG_FILE_NAME .
                     ".ini configuration file.");
        }

        $this->userTableGateway = $userTableGateway;
    }

    public function authenticate($request, UserRow $userToConnectWith = null)
    {
        unset($request);

        $entityId = $this->fetchCurrentEntityId();
        $config = null;

        $loggedWithKnownEntityId = false;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == $configuration['entityId']) {
                $config = $configuration;
                $prefix = $name;
                $loggedWithKnownEntityId = true;
                break;
            }
        }

        if ($config == null) {
            if (isset($this->shibbolethConfig['default'])) {
                $config = $this->shibbolethConfig['default'];
                $prefix = 'Dummy';
            } else
                throw new AuthException(
                    'Recieved entityId was not found in ' . static::CONFIG_FILE_NAME .
                         '.ini config nor default config part exists.');
        }

        $attributes = $this->fetchAttributes($config);

        $eppn = $this->fetchEduPersonPrincipalName();

        if (empty($eppn)) {
            throw new AuthException(
                'IdP "' . $prefix .
                     '" didn\'t provide eduPersonPrincipalName attribute.');
        }

        // Get UserRow by checking for known eppn
        $currentUser = $this->userTableGateway->getUserRowByEppn($eppn);

        // Now we need to know if there is a request to connect two identities
        $connectIdentities = $userToConnectWith !== null;
        if ($connectIdentities) {

            if ($currentUser !== false &&
                 $currentUser->id === $userToConnectWith['id'])
                throw new AuthException(
                    $this->translate("You already have this identity connected"));

            if ($loggedWithKnownEntityId && ! empty($attributes['cat_username'])) {

                $updateUserRow = true;

                // Set here the prefix to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $prefix . static::SEPARATOR .
                     $attributes['cat_username'];
            } else {
                // We now detected unkown entityID - this identity will be Dummy
                $updateUserRow = false;

                // We can't store current $prefix to home_library as it always needs to be Dummy,
                // so we store the information about $prefix in cat_username unscoped
                $attributes['cat_username'] = 'Dummy' . static::SEPARATOR . $prefix;

                if ($loggedWithKnownEntityId) {
                    $prefix = 'Dummy'; // This will be home_library
                }
            }

            if ($currentUser === false) {
                // We now detected user has no entry with current eppn in our DB, thus append new libCard
                $userToConnectWith->createLibraryCard($attributes['cat_username'],
                    $prefix, $eppn, $attributes['email'],
                    $this->canConsolidateMoreTimes);
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
                $this->updateIdentity($userToConnectWith,
                    $attributes['cat_username'], $prefix, $eppn);

                $this->userTableGateway->mergeUserInAllTables($currentUser,
                    $userToConnectWith);
            }

            if ($updateUserRow) {
                $userToConnectWith = $this->updateUserRow($userToConnectWith,
                    $attributes);
            }

            return $userToConnectWith;
        } else { // Being here means there is no other identity to connect with - regular login

            // If there was no User found, create one
            if (! $currentUser) {

                // eppn will be user's username
                $currentUser = $this->userTableGateway->createRowForUsername($eppn);

                $userRowCreatedRecently = true;
            } else
                $userRowCreatedRecently = false;

            if ($loggedWithKnownEntityId && ! empty($attributes['cat_username'])) {

                // Set here the prefix to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $prefix . static::SEPARATOR .
                     $attributes['cat_username'];

                // Did the userRow exist before? ..
                if (! $userRowCreatedRecently) {

                    $wasDummyBefore = $currentUser->home_library === 'Dummy';

                    // We need to check, if there doesn't exist library card with the same institution to update it
                    if (! $wasDummyBefore) {
                        // We should check, if we have correct cat_username & home_library ...
                        $this->updateIdentity($currentUser,
                            $attributes['cat_username'], $prefix, $eppn);
                    } else {
                        // IdP finally returned cat_username for this User .. update proprietary libCard
                        $currentUser->upgradeLibraryCardFromDummy($eppn,
                            $attributes['cat_username'], $prefix);
                    }
                }
            } else {
                // We now detected unkown entityID - this identity will be Dummy

                // We can't store current $prefix to home_library as it always needs to be Dummy,
                // so we store the information about $prefix in cat_username unscoped
                $attributes['cat_username'] = 'Dummy' . static::SEPARATOR . $prefix;

                if ($loggedWithKnownEntityId) {
                    $prefix = 'Dummy'; // This will be home_library
                }
            }

            if ($userRowCreatedRecently) {
                $currentUser = $this->createUser($currentUser, $attributes, $prefix,
                    $eppn);
            }

            return $currentUser;
        }
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
            $url = $logoutEndpoint . '?return=' . urlencode($url);
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
            throw new AuthException(
                'Shibboleth login configuration parameter is not set.');
        } elseif (isset($shib->getAssertion) && $shib->getAssertion == true) {
            $this->shibAssertionExportEnabled = true;
        }

        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($name === 'Dummy') {
                throw new AuthException(
                    'Shibboleth config section cannot be called \'Dummy\', this name is reserved.');
            }

            if ($name !== 'main') {
                if (! isset($configuration['username']) ||
                     empty($configuration['username'])) {
                    throw new AuthException(
                        "Shibboleth 'username' is missing in your " .
                         static::CONFIG_FILE_NAME . ".ini configuration file for '" .
                         $name . "'");
                }

                if ($name !== 'default') {
                    if (! isset($configuration['entityId']) ||
                         empty($configuration['entityId'])) {
                        throw new AuthException(
                            "Shibboleth 'entityId' is missing in your " .
                             static::CONFIG_FILE_NAME .
                             ".ini configuration file for '" . $name . "'");
                    } elseif (! isset($configuration['cat_username']) ||
                         empty($configuration['cat_username'])) {
                        throw new AuthException(
                            "Shibboleth 'cat_username' is missing in your " .
                             static::CONFIG_FILE_NAME .
                             ".ini configuration file for '" . $name .
                             "' with entityId " . $configuration['entityId']);
                    }
                }
            } else {
                $this->canConsolidateMoreTimes = $this->shibbolethConfig->main->canConsolidateMoreTimes;

                if ($this->canConsolidateMoreTimes !== null)
                    $this->canConsolidateMoreTimes = $this->canConsolidateMoreTimes->toArray();

                $this->workingLogoutEntityIds = $this->shibbolethConfig->main->workingLogoutEntityIds;

                if ($this->workingLogoutEntityIds !== null)
                    $this->workingLogoutEntityIds = $this->workingLogoutEntityIds->toArray();
            }
        }

        if (empty($this->canConsolidateMoreTimes))
            $this->canConsolidateMoreTimes = false;

        if (empty($this->workingLogoutEntityIds))
            $this->workingLogoutEntityIds = false;
    }

    /**
     * Checks if User can logout safely.
     * It basically checks for presence of current
     * entityID in $this->workingLogoutEntityIds.
     *
     * @return boolean $canLogoutSafely
     */
    public function canLogoutSafely()
    {
        $currentEntityId = $this->fetchCurrentEntityId();
        if (array_search(null,
            [
                $currentEntityId,
                $this->workingLogoutEntityIds
            ]) === false)
            return in_array($currentEntityId, $this->workingLogoutEntityIds);
        else
            return true;
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
            throw new AuthException(
                $this->translate(
                    'No token recieved after logging with another account'));

        $userToConnectWith = $this->userTableGateway->getUserFromConsolidationToken(
            $token);

        if (! $userToConnectWith)
            throw new AuthException(
                $this->translate(
                    'The consolidation has expired. Please authenticate again.'));

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
     * @return string $accountConsolidationRedirectUrl
     * @throws AuthException
     */
    public function getAccountConsolidationRedirectUrl($userRowId)
    {
        // Create & write token to DB & user's cookie
        $token = $this->generateToken();
        setCookie(static::CONSOLIDATION_TOKEN_TAG, $token);

        $tokenCreated = $this->userTableGateway->saveUserConsolidationToken($token,
            $userRowId);

        if (! $tokenCreated)
            throw new AuthException(
                'Could not create consolidation token entry into session table');

            // Create redirection URL
        $hostname = $this->config->Site->url;

        if (substr($hostname, - 1) !== '/') {
            $hostname .= '/';
        }

        $target = $hostname . 'MyResearch/UserConnect';

        $entityId = $this->fetchCurrentEntityId();
        $target .= '?eid=' . urlencode($entityId);

        $loginRedirect = $this->config->Shibboleth->login . '?forceAuthn=1&target=' .
             urlencode($target);

        if ($this->canLogoutSafely()) {
            return $this->config->Shibboleth->logout . '?return=' .
                 urlencode($loginRedirect);
        } else
            return $loginRedirect;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).
     * Returns false when no session initiator is needed.
     *
     * @param string $target
     *            Full URL where external authentication method should
     *            send user to after login (some drivers may override this).
     *
     * @return array
     */
    public function getSessionInitiators($target)
    {
        $this->init();
        $config = $this->getConfig();
        if (isset($config->Shibboleth->target)) {
            $shibTarget = $config->Shibboleth->target;
        } else {
            $shibTarget = $target;
        }
        $initiators = array();
        foreach ($this->shibbolethConfig as $name => $configuration) {
            $entityId = $configuration['entityId'];
            $loginUrl = $config->Shibboleth->login . '?target=' .
                 urlencode($shibTarget) . '&entityID=' . urlencode($entityId);
            $initiators[$name] = $loginUrl;
        }
        return $initiators;
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
        $userRow->createLibraryCard($userRow->cat_username, $userRow->home_library,
            $eppn, $userRow->email, $this->canConsolidateMoreTimes);

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
                    if ($attribute === 'cat_username' &&
                         isset($config['changeAgencyIdSeparator']) &&
                         $config['changeAgencyIdSeparator'] instanceof \Zend\Config\Config) {
                        $agencyIdSeparators = $config['changeAgencyIdSeparator']->toArray();

                        $currentSeparator = key($agencyIdSeparators);
                        $desiredSeparator = $agencyIdSeparators[$currentSeparator];

                        $catUsernameSplitted = explode($currentSeparator, $value);

                        if (isset($config['invertAgencyIdWithUsername']) &&
                             $config['invertAgencyIdWithUsername'])
                            $catUsernameSplitted = array_reverse(
                                $catUsernameSplitted);

                        $value = implode($desiredSeparator, $catUsernameSplitted);
                    }

                    $attributes[$attribute] = $value;
                }
            }
        }

        return $attributes;
    }

    protected function fetchCurrentEntityId()
    {
        return isset($_SERVER[static::SHIB_IDENTITY_PROVIDER_ENV]) ? $_SERVER[static::SHIB_IDENTITY_PROVIDER_ENV] : null;
    }

    protected function fetchEduPersonPrincipalName()
    {
        return explode(";", $_SERVER[$this->shibbolethConfig->default->username])[0];
    }

    /**
     * Returns 32-length string token.
     *
     * @return string Token
     */
    protected function generateToken()
    {
        return chr(mt_rand(97, 122)) . chr(mt_rand(97, 122)) . substr(md5(time()), 3) .
             chr(mt_rand(97, 122));
    }

    /**
     * Returns & deletes user's token from cookie.
     *
     * @return string Token
     */
    protected function getConsolidationTokenFromCookie()
    {
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
                $into->createLibraryCard($libCard->cat_username,
                    $libCard->home_library, $libCard->eppn, $libCard->card_name,
                    $this->canConsolidateMoreTimes);
            } catch (AuthException $e) {
                // Something went wrong - restore libCard flagged for deletion
                $this->setLibCardDeletionFlag($libCard, false);
                throw $e;
            }

            $from->deleteLibraryCardRow($libCard, false);
        }
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
                if ($libCard_cat_username !== $cat_username) {

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
}
