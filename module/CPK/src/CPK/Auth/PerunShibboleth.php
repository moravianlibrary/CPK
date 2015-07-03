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

use VuFind\Exception\Auth as AuthException, CPK\Perun\IdentityResolver;
use VuFind\Db\Row\User;
use VuFind\Auth\Shibboleth as Shibboleth;

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

    const SHIB_IDENTITY_PROVIDER_ENV = 'Shib-Identity-Provider';

    const SHIB_ASSERTION_COUNT_ENV = 'Shib-Assertion-Count';

    const SEPARATOR = ".";

    const SEPARATOR_REGEXED = "\\.";

    protected $shibAssertionExportEnabled = false;

    protected $identityResolver;

    protected $loginDrivers = null;

    protected $shibbolethConfig = null;

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

    const CONFIG_FILE_NAME = "shibboleth";

    public function __construct(\VuFind\Config\PluginManager $configLoader, IdentityResolver $identityResolver)
    {
        $this->shibbolethConfig = $configLoader->get($this::CONFIG_FILE_NAME);

        if (empty($this->shibbolethConfig)) {
            throw new AuthException("Could not load " . $this::CONFIG_FILE_NAME . ".ini configuration file.");
        }

        $this->identityResolver = $identityResolver;
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

        if (! $isConnected) {
            $prefix = 'Dummy';

            // Set cat_username's MultiBackend source dummy driver
            $attributes['cat_username'] = 'Dummy.Dummy';
            $attributes['home_library'] = $prefix;
        } else {

            // Process additional Perun requests
            if (empty($attributes['cat_username'])) {
                throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['cat_username'] . '"');
            }

            // Get dummy institutes for now
            $institutes = $this->identityResolver->getDummyContent($prefix, $attributes['cat_username']);

            $perunId = $_SERVER['perunUserId'];

            if (empty($perunId)) {

                // User is now being redirected to registrar of Perun
                $linkToRegister = $this->identityResolver->getPerunRegistrarLink();
                header('Location: ' . $linkToRegister, true, 307);
                die();

                // TODO: After user went through registery, we need to ask IdP again to provide us new info
                // so that out SP can call AA to fetch new perunId to check if the registery was successfull

                // TODO: Then, if user has new identity without libraryCard in Perun, push a card to Perun
            } else {

                $handleLibraryCards = true;
            }

            $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];
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
     * Deletes all user's Library Cards & than creates new from list of institutes provided by Perun.
     *
     * ActiveCard doesn't have to be provided. If it is, on the other hand, it creates this card as the first
     * which makes it reliable it will be the one active after user gets into his account.
     *
     * If the provided ActiveCard is not found in institutes, than that card will not be created at all.
     *
     * @param User $user
     * @param array $institutes
     *            - associative array of user's CPK connected institutes returned by IdentityResolver
     * @param string $activeCard
     *            - it is basically cat_username user had active before new login
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

    public function isShibAssertionExportEnabled()
    {
        return $this->shibAssertionExportEnabled;
    }

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
