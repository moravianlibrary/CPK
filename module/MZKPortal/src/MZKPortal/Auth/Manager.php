<?php
/**
 * Wrapper class for handling logged-in user in session.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace MZKPortal\Auth;
use VuFind\Auth\Manager as BaseManager,
    VuFind\Db\Table\User as UserTable,
    VuFind\Auth\PluginManager as PluginManager,
    Zend\Config\Config as Config,
    Zend\Session\SessionManager as SessionManager,
    VuFind\Cookie\CookieManager,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;


/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Manager extends BaseManager
{

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(Config $config, UserTable $userTable,
        SessionManager $sessionManager, PluginManager $pm, CookieManager $cookieManager)
    {
        parent::__construct($config, $userTable, $sessionManager, $pm, $cookieManager);
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user to after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiators($target)
    {
        if ($this->getAuth() instanceof ShibbolethWithWAYF) {
           return $this->getAuth()->getSessionInitiators($target);
        } else {
            return false;
        }
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     *            Request object containing
     *            account credentials.
     *
     * @throws AuthException
     * @return UserRow Object representing logged-in user.
     */
    public function login($request)
    {
        $user = parent::login($request);

        // Create library card from cat_username if doesn't exist yet
        $cards = $user->getLibraryCards();
        $cardExists = false;

        $cat_username = $user['cat_username'];

        // Get username without the source (we presume driver is MultiBackend - checking it would not be efficient)
        $username = split('\.', $user['username'])[1];

        foreach ($cards as $card) {
            $cardExists = $card['cat_username'] == $cat_username;
            if ($cardExists) {
                break;
            }
        }
        if (! $cardExists) {
            try {
                $user->saveLibraryCard(null, "Default card for $username", $cat_username, null);
            } catch (\VuFind\Exception\LibraryCard $e) {
                $this->flashMessenger()
                    ->setNamespace('error')
                    ->addMessage($e->getMessage());
                return false;
            }
        }

        // TODO: Prompt for all institutions user is in & parse cat_username used by those - then create cards from them
        // TODO: If there was more than 1 card, ask user which one will be his default

        return $user;
    }

}
