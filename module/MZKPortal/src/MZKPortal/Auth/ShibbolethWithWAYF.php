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

    const SEPARATOR = "\t";

    protected $configLoader;

    protected $shibbolethConfig = null;

    protected $attribsToCheck = array(
        'cat_username', 'email', 'lastname', 'firstname',
        'college', 'major', 'home_library'
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
            throw new AuthException('authentication_error_admin');
        }
        $username = $request->getServer()->get($config->username);
        $user = $this->getUserTable()->getByUsername($username);
        if (empty($username)) {
            throw new AuthException('authentication_error_admin');
        }
        foreach ($this->attribsToCheck as $attribute) {
            if (isset($config->$attribute)) {
                $value = $request->getServer()->get($config->$attribute);
                if ($attribute == 'cat_username') {
                    $user->$attribute = $prefix . self::SEPARATOR . $value;
                } else {
                    $user->$attribute = $value;
                }
            }
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
