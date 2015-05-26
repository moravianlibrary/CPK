<?php
/**
 * Shibboleth authentication module.
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
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace MZKPortal\Auth;

use VuFind\Auth\Shibboleth as Shibboleth, VuFind\Exception\Auth as AuthException, Zend\XmlRpc\Value\String, MZKPortal\Perun\IdentityResolver;
use VuFind\Exception\VuFind\Exception;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package Authentication
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @author Franck Borel <franck.borel@gbv.de>
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class ShibbolethWithWAYF extends Shibboleth
{

    const SHIB_IDENTITY_PROVIDER_ENV = 'Shib-Identity-Provider';

    const SEPARATOR = ".";
    const SEPARATOR_REGEXED = "\\.";

    protected $configLoader;

    protected $identityResolver;

    protected $shibbolethConfig = null;

    protected $loginDrivers = null;

    protected $attribsToCheck = array(
        'username',
        'cat_username',
        'email',
        'lastname',
        'firstname',
        'college',
        'major',
        'home_library',
        'pass_hash',
        'verify_hash'
    );

    public function __construct(\VuFind\Config\PluginManager $configLoader, IdentityResolver $identityResolver = null)
    {
        $this->configLoader = $configLoader;
        $this->identityResolver = $identityResolver == null ? false : $identityResolver;
    }

    public function authenticate($request)
    {
        $this->init();
        $entityId = $request->getServer()->get(self::SHIB_IDENTITY_PROVIDER_ENV);
        $config = null;
        $prefix = null;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == $configuration['entityId']) {
                $config = $configuration;
                $prefix = $name;
                break;
            }
        }
        if ($config == null) {
            if (isset($this->shibbolethConfig['default'])) {
                $config = $this->shibbolethConfig['default'];
                $prefix = 'default';
            } else
                throw new AuthException('config_for_entityid_not_found');
        }
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
                } else
                    if (strpos($key, ',') !== false) {
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

        if (empty($attributes['username'])) {
            throw new AuthException('username_not_returned');
        }

        // Set home_library to eduPersonPrincipalName's institute name - this approach should always succeed
        if (empty($attributes['home_library'])) {
            $attributes['home_library'] = end(split('@', $attributes['username']));

            // Get rid of all dots (because of multibackend's dot usage later)
            $attributes['home_library'] = str_replace('.', '', $attributes['home_library']);
        }

        // If we have Perun configuration enabled, than use Perun services
        if ($this->identityResolver) {

            // Detect if the institute, user logged in with, is a connected library & not e.g. Facebook or another library
            $isConnected = array_search($attributes['home_library'], $this->loginDrivers) !== FALSE;
            if ($isConnected) {
                // Set SIGLA & userId for Perun
                $sigla = $attributes['home_library'];
                $userId = $attributes['cat_username'];
            }

            // Send data to Perun & get perunId with institutes
            list ($perunId, $institutes) = $this->identityResolver->getUserIdentityFromPerun($attributes['username'], $sigla, $userId);

            $attributes['username'] = $perunId;

            if (empty($institutes)) {
                // If are institutes empty, that means user is not member of any connected library
                // In that case set cat_username's MultiBackend source dummy driver which
                $attributes['cat_username'] = 'dummyAccount';
                $attributes['home_library'] = 'dummyDriver';
                // FIXME create dummyDriver

                throw new AuthException("No dummyDriver implemented yet");
                /*
                 * TODO if user has dummyDriver, than enable only these features on My Account:
                 * 'Favorites', 'Your Saved Searches', 'Log Out', 'Your Favorites', 'Create a List'
                 */
            } else {
                // Note that user's cat_username & home_library will be set by first Library Card created
                $attributes['cat_username'] = '';
                $attributes['home_library'] = '';
                $handleLibraryCards = true;
            }
        }

        $prefix = $attributes['home_library'];

        // cat_username needs to have defined driver in MultiBackend.ini, which is the $prefix here
        $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];

        if ($attributes['email'] == null)
            $attributes['email'] = '';
        if ($attributes['firstname'] == null)
            $attributes['firstname'] = '';
        if ($attributes['lastname'] == null)
            $attributes['lastname'] = '';

        $user = $this->getUserTable()->getByUsername($attributes['username']);

        $activeCard = $user['cat_username'];

        foreach ($attributes as $key => $value) {
            $user->$key = $value;
        }

        // Save/Update user in database
        $user->save();

        // We need user->id to create library cards - that provides $user->save() method
        if ($handleLibraryCards) {
            $this->handleLibraryCards($user, $institutes, $activeCard);
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

    protected function init()
    {
        if ($this->shibbolethConfig == null) {
            $this->shibbolethConfig = $this->configLoader->get('shibboleth');
        }

        if ($this->loginDrivers == null) {
            $multiBackend = $this->configLoader->get('MultiBackend');
            $this->loginDrivers = $multiBackend != null ? $multiBackend->Login->drivers->toArray() : [];
        }

        if ($this->identityResolver)
            $this->identityResolver->init($this->getConfig());
    }

    protected function handleLibraryCards($user, $institutes, $activeCard)
    {
        $tableManager = $this->getDbTableManager();
        $userCardTable = $tableManager->get("UserCard");

        // Now delete all user cards & create new from IdP fresh list of Institutes
        $resultSet = $userCardTable->select([
            'user_id' => $user['id']
        ]);
        foreach ($resultSet as $result) {
            $result->delete();
        }

        // Save activeCard first as it is always being activated by "being first user's card created"
        if ($this->isActiveCardInInstitutes($activeCard, $institutes)) {
            $home_library = split(self::SEPARATOR_REGEXED, $activeCard)[0];
            $this->createLibraryCard($user, $activeCard, $home_library);
        }

        foreach ($institutes as $institute) {
            $cat_username = $institute[IdentityResolver::LIBRARY_KEY] . self::SEPARATOR . $institute[IdentityResolver::USER_KEY];

            // Do not save already saved activeCard
            if ($cat_username !== $activeCard) {
                $home_library = $institute[IdentityResolver::LIBRARY_KEY];
                $this->createLibraryCard($user, $cat_username, $home_library);
            }
        }
    }

    protected function isActiveCardInInstitutes($activeCard, $institutes) {
        if (empty($activeCard))
            return false;

        foreach ($institutes as $institute) {
            $cat_username = $institute[IdentityResolver::LIBRARY_KEY] . self::SEPARATOR . $institute[IdentityResolver::USER_KEY];
            if ($cat_username === $activeCard)
                return true;
        }

        return false;
    }

    protected function createLibraryCard($user, $cat_username, $home_library) {
        try {
            $user->saveLibraryCard(null, '', $cat_username, null, $home_library);
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()
            ->setNamespace('error')
            ->addMessage($e->getMessage());
            return false;
        }
    }
}
