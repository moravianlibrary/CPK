<?php
/**
 * Shibboleth authentication module crafted with respect to Perun - open-source Identity and Access Management System.
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

use VuFind\Exception\Auth as AuthException, CPK\Perun\IdentityResolver, VuFind\Db\Row\User, VuFind\Auth\Shibboleth as Shibboleth;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package Authentication
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class PerunShibboleth extends Shibboleth
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
     * It is used to show these assertions on demand from config to user's Profile module/CPK/src/CPK/Auth/PerunShibboleth.php(useful for determining
     * which attributes did IdP actually sent us)
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
     * It's value must match the separator MultiBackend driver uses to explode() cat_username.
     *
     * @var const SEPARATOR
     */
    const SEPARATOR = ".";

    /**
     * It's value is same as $this::SEPARATOR, but regex ready.
     *
     * @var const SEPARATOR_REGEXED
     */
    const SEPARATOR_REGEXED = "\\.";

    /**
     * Instance of IdentityResolver to call it's methods used for communication with Perun API.
     *
     *
     * @var \CPK\Perun\IdentityResolver identityResolver
     */
    protected $identityResolver;

    /**
     * This is a standalone file with filename shibboleth.ini in localconfig/config/vufind directory
     *
     * @var \Zend\Config\Config shibbolethConfig
     */
    protected $shibbolethConfig = null;

    /**
     * This is array of attributes which $this->authenticate() method should check for.
     *
     * WARNING: can contain only such attributes, which are writeable to user table!
     *
     * @var array attribsToCheck
     */
    protected $attribsToCheck = array(
        'username',
        'cat_username',
        'email',
        'lastname',
        'firstname',
        'college',
        'major',
        'home_library'
    );

    public function __construct(\VuFind\Config\PluginManager $configLoader, IdentityResolver $identityResolver)
    {
        $this->shibbolethConfig = $configLoader->get($this::CONFIG_FILE_NAME);

        if (empty($this->shibbolethConfig)) {
            throw new AuthException("Could not load " . $this::CONFIG_FILE_NAME . ".ini configuration file.");
        }

        $this->identityResolver = $identityResolver;
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

        foreach ($this->shibbolethConfig as $name => $configuration) {
            if (! isset($configuration['username']) || empty($configuration['username'])) {
                throw new AuthException("Shibboleth 'username' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "'");
            }

            if ($name !== 'default') {
                if (! isset($configuration['entityId']) || empty($configuration['entityId'])) {
                    throw new AuthException("Shibboleth 'entityId' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "'");
                } elseif (! isset($configuration['cat_username']) || empty($configuration['cat_username'])) {
                    throw new AuthException("Shibboleth 'cat_username' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "' with entityId " . $configuration['entityId']);
                }
            }
        }

        // Validate also IdentityResolver's config from here
        $this->identityResolver->validateConfig($this->config);
    }

    public function authenticate($request)
    {
        $entityId = $request->getServer()->get(self::SHIB_IDENTITY_PROVIDER_ENV);
        $config = null;
        $prefix = null;

        $isConnected = false;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == $configuration['entityId']) {
                $config = $configuration;
                $prefix = $name;
                $isConnected = true;
                break;
            }
        }

        if ($config == null) {
            if (isset($this->shibbolethConfig['default'])) {
                $config = $this->shibbolethConfig['default'];
                $prefix = 'default';
            } else
                throw new AuthException('Recieved entityId was not found in " . $this::CONFIG_FILE_NAME . ".ini config nor default config part exists.');
        }

        $attributes = $this->fetchAttributes($request, $config);

        if (empty($attributes['username'])) {
            throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['username'] . '"');
        }

        $perunId = $_SERVER['perunUserId'];

        // Empty perunId means user has no record in Perun or we didn't contact AA after user's registery
        if (empty($perunId)) {
            $this->registerUserToPerun($entityId);
            // Died here ...
        }

        if (! $isConnected) {
            // FIXME: User can login via FB & still can be member of one of connected institutes - look into his institutes
            // TODO: If user logged in doesn't have connected his accounts from connected institutes, how should we tell him to do it?
            $prefix = 'Dummy';

            // Set cat_username's MultiBackend source dummy driver
            $attributes['cat_username'] = 'Dummy.Dummy';
            $attributes['home_library'] = $prefix;
        } else {

            if (empty($attributes['cat_username'])) {
                throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['cat_username'] . '"');
            }

            $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];

            // Get dummy institutes for now
            $dummyInstitutes = $this->identityResolver->getDummyContent($attributes['cat_username']);

            $institutesFromPerun = split(";", $_SERVER['userLibraryIds']);

            // If user has new identity without libraryCard in Perun, push a card of current account to Perun
            // FIXME: update institutes using $institutesFromPerun, not $dummyInstitutes !! ... but after AA returns libraryIDs
            $institutes = $this->identityResolver->updateUserInstitutesInPerun($attributes['cat_username'], $dummyInstitutes);

            $handleLibraryCards = true;
        }

        if ($attributes['email'] == null)
            $attributes['email'] = '';
        if ($attributes['firstname'] == null)
            $attributes['firstname'] = '';
        if ($attributes['lastname'] == null)
            $attributes['lastname'] = '';

        $user = $this->getUserTable()->getByUsername($attributes['username']);

        foreach ($attributes as $key => $value) {
            $user->$key = $value;
        }

        // Save/Update user in database
        $user->save();

        // We need user->id to create library cards - that provides $user->save() method
        if ($isConnected) {
            $this->handleLibraryCards($user, $institutes);
        }

        return $user;
    }

    /**
     * This method is a tiny ecosystem, where is user redirected to multiple endpoints
     * based on current user's Perun registery/login state.
     *
     * For more information please read comments inside function.
     *
     * Be aware of this method as the php thread dies.
     *
     * @param string $entityId
     */
    protected function registerUserToPerun($entityId)
    {
        if ($this->isUserRedirectedFrom("registrar_relogged")) {
            throw new AuthException("User was successfully registered to Perun, but Perun AA didn't return perunId. Please contact the support.");
        }

        if ($this->isUserRedirectedFrom("registrar")) {

            // User already registered - we just need to refetch his attributes from IdP & AA via our SP
            // This is done by another redirect to our SP

            $this->identityResolver->redirectUserToLoginEndpoint($entityId, "registrar_relogged");
            // Died here ...
        } elseif ($this->isUserRedirectedFrom("consolidator")) {

            /*
             * TODO: Detect if the user returned from consolidator has now assigned any PerunId
             * or still has no PerunId
             *
             * First possibility occurs only if user already had PerunId before & he successfully
             * connected current account from IdP
             *
             * Second is after immediate redirect back here after consolidator didn't found any
             * similar identity
             *
             * TODO: Remove then following Exception
             */
            throw new AuthException("Sorry, we cannot register you to Perun at this time.");

            // TODO: Redirect user to registrar only if he has no PerunId now ...
            $this->identityResolver->redirectUserToRegistrar($entityId);
            // Died here ...
        }

        $this->identityResolver->redirectUserToConsolidator($entityId);
        // Died here ...
    }

    /**
     * Returns true only if there is $_GET["redirected_from"] param set to $tag
     *
     * @param
     *            string tag
     * @return boolean isRedirectedFromTag
     */
    protected function isUserRedirectedFrom($tag)
    {
        return isset($_GET['redirected_from']) && $_GET['redirected_from'] === $tag;
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
            $loginUrl = $config->Shibboleth->login . '?target=' . urlencode($shibTarget) . '&entityID=' . urlencode($entityId);
            $initiators[$name] = $loginUrl;
        }
        return $initiators;
    }

    /**
     * Deletes user's Library Cards which are not in array $userLibraryIds & creates those cards, which user
     * doesn't have compared to array $userLibraryIds
     *
     * @param User $user
     * @param unknown $userLibraryIds
     */
    protected function handleLibraryCards(User $user, $userLibraryIds)
    {
        $tableManager = $this->getDbTableManager();
        $userCardTable = $tableManager->get("UserCard");

        $resultSet = $userCardTable->select([
            'user_id' => $user['id']
        ]);

        // Delete lost identitites
        foreach ($resultSet as $result) {
            $cat_username = $result['cat_username'];

            // Doesn't exists -> delete it
            if (! in_array($cat_username, $userLibraryIds)) {
                $result->delete();
            } else
                $existing[] = $cat_username;
        }

        // Create new identities
        foreach ($userLibraryIds as $userLibraryId) {

            if (! in_array($userLibraryId, $existing)) {
                $home_library = split(self::SEPARATOR_REGEXED, $userLibraryId)[0];
                $this->createLibraryCard($user, $userLibraryId, $home_library);
            }
        }
    }

    protected function createLibraryCard($user, $cat_username, $home_library)
    {
        try {
            return $user->saveLibraryCard(null, '', $cat_username, null, $home_library);
        } catch (\VuFind\Exception\LibraryCard $e) { // If an exception is thrown, just show a flash message ..
            $exceptions = $_ENV['exception'];

            if ($exceptions == null) {
                $_ENV['exception'] = $e->getMessage();
            } else {
                $_ENV['exception'] .= "\n" . $e->getMessage();
            }

            return false;
        }
    }

    /**
     * Maps premapped attributes from shibboleth.ini particular section where is know-how for parsing
     * attributes the IdP returned.
     *
     * It basically returns array $attributes, which is later saved to 'user' table as current user.
     * There may be some minor modifications, e.g. to cat_username is appended institute delimited
     * by $this::SEPARATOR.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     * @param \Zend\Config\Config $config
     *            containing only array of attributes mapping from attribute-map.xml to user table in VuFind
     * @return array attributes
     */
    protected function fetchAttributes($request, $config)
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
                        $value = $request->getServer()->get($key);
                        if ($value != null) {
                            break;
                        }
                    }
                } elseif (strpos($key, ',') !== false) {
                    list ($key, $pattern) = explode(',', $key, 2);
                    $pattern = trim($pattern);
                }
                if ($value == null) {
                    $value = $request->getServer()->get($key);
                }
                if ($pattern != null) {
                    $matches = array();
                    preg_match($pattern, $value, $matches);
                    $value = $matches[1];
                }

                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
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
     * This function returns array of assertions Shibboleth SP sent us.
     * If the PHP script was unable to
     * load contents of provided links, then each array element contains the link to parse the assertion.
     *
     * @return array assertions
     */
    public function getShibAssertions()
    {
        $assertions = array();

        $count = intval($_SERVER[$this::SHIB_ASSERTION_COUNT_ENV]);

        if (! empty($count))
            for ($i = 0; $i < $count; ++ $i) {
                $shibAssertionEnv = $this->getShibAssertionNumberEnv($i + 1);

                $assertions[$i] = $_SERVER[$shibAssertionEnv];

                if ($assertions[$i] == null) {
                    unset($assertions[$i]);
                } else {
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

    protected function getShibAssertionNumberEnv($i)
    {
        if ($i < 10) {
            return 'Shib-Assertion-0' . $i;
        } else {
            return 'Shib-Assertion-' . $i;
        }
    }
}
