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
namespace MZKPortal\Auth;

use VuFind\Exception\Auth as AuthException, MZKPortal\Perun\IdentityResolver;
use VuFind\Exception\VuFind\Exception;
use VuFind\Db\Row\User;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package Authentication
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class PerunShibboleth extends ShibbolethWithWAYF
{

    const SEPARATOR = ".";

    const SEPARATOR_REGEXED = "\\.";

    protected $identityResolver;

    protected $loginDrivers = null;

    public function __construct(\VuFind\Config\PluginManager $configLoader, IdentityResolver $identityResolver = null)
    {
        parent::__construct($configLoader);
        $this->identityResolver = ($identityResolver == null) ? false : $identityResolver;

        // One more attr to check ..
        $this->attribsToCheck[] = 'epsa';
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
            throw new AuthException('authentication_error_loggedout');
        }

        // Set home_library to eduPersonPrincipalName's institute name - this approach should always succeed
        if (empty($attributes['home_library'])) {

            if (! empty($attributes['epsa'])) {
                $attributes['home_library'] = end(split('@', $attributes['epsa']));
                unset($attributes['epsa']);
            } else {
                $attributes['home_library'] = end(split('@', $attributes['username'])); // Assume we have eppn here ..
            }

            // Get rid of all dots (because of multibackend's dot usage later)
            $attributes['home_library'] = str_replace('.', '', $attributes['home_library']);

        }

        // FIXME: How about removing this conf ? .. actually this class is named PerunShibboleth which kinda enables it :-D
        // If we have Perun configuration enabled, than use Perun services
        if ($this->identityResolver) {

            // Detect if the institute, user logged in with, is a connected library & not e.g. Facebook or another library
            $isConnected = array_search($attributes['home_library'], $this->loginDrivers) !== FALSE;
            if ($isConnected) {
                // Set SIGLA & userId for Perun
                $sigla = $attributes['home_library'];

                if (empty($attributes['cat_username'])) {
                    throw new AuthException('cat_username_not_returned');
                }

                $userId = $attributes['cat_username'];
            }

            // Send data to Perun & get perunId with institutes
            list ($perunId, $institutes) = $this->identityResolver->getUserIdentityFromPerun($attributes['username'], $sigla, $userId);

            // This was eppn, now it is perunId
            $attributes['username'] = $perunId;

            if (empty($institutes)) {
                // If are institutes empty, that means user is not member of any connected library
                // In that case set cat_username's MultiBackend source dummy driver which
                $attributes['cat_username'] = '';
                $attributes['home_library'] = 'Dummy';
            } else {
                $handleLibraryCards = true;
            }
        }

        $prefix = $attributes['home_library'];

        $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];

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
        if ($handleLibraryCards) {
            $this->handleLibraryCards($user, $institutes);
        }

        return $user;
    }

    protected function init()
    {
        parent::init();

        if ($this->loginDrivers == null) {
            $multiBackend = $this->configLoader->get('MultiBackend');
            $this->loginDrivers = $multiBackend != null ? $multiBackend->Login->drivers->toArray() : [];
        }

        if ($this->identityResolver)
            $this->identityResolver->init($this->getConfig());
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
    protected function handleLibraryCards($user, $userLibraryIds)
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

            if($exceptions == null) {
                $_ENV['exception'] = $e->getMessage();
            } else {
                $_ENV['exception'] .= "\n" . $e->getMessage();
            }

            return false;
        }
    }
}
