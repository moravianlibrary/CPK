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

use VuFind\Auth\Shibboleth as Shibboleth,
    VuFind\Exception\Auth as AuthException;
use Zend\XmlRpc\Value\String;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ShibbolethWithWAYF extends Shibboleth
{

    const SHIB_IDENTITY_PROVIDER_ENV = 'Shib-Identity-Provider';

    const SEPARATOR = ".";

    protected $configLoader;

    protected $shibbolethConfig = null;

    protected $attribsToCheck = array(
        'username', 'cat_username', 'email', 'lastname',
        'firstname', 'college', 'major', 'home_library',
        'pass_hash', 'verify_hash'
    );

    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
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
            } else {
                throw new AuthException('config_for_entityid_not_found');
            }
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
                } else if (strpos($key, ',') !== false) {
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

        if (empty($attributes['username']) || empty($attributes['cat_username'])) {
            throw new AuthException('username_not_returned');
        }

        if (!empty($attributes['home_library']))
            $prefix = $attributes['home_library'];

        // cat_username needs to have defined driver in MultiBackend.ini, which is the $prefix here
        $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];

        // username on the other hand needs to be always unique
        // we cannot risk having two people same usernames accross institutes
        $attributes['username'] = $prefix . self::SEPARATOR . $attributes['username'];


        if ($attributes['email'] == null) $attributes['email'] = '';
        if ($attributes['firstname'] == null) $attributes['firstname'] = '';
        if ($attributes['lastname'] == null) $attributes['lastname'] = '';

        $user = $this->getUserTable()->getByUsername($attributes['username']);
        foreach ($attributes as $key => $value) {
            $user->$key = $value;
        }
        // Save and return the user object:
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user to after login (some drivers may override this).
     *
     * @return array
     */
    public function getSessionInitiators($target) {
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

    protected function init() {
        if ($this->shibbolethConfig == null) {
            $this->shibbolethConfig = $this->configLoader->get('shibboleth');
        }
    }

}
