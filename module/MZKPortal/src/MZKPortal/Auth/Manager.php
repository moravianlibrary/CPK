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

use VuFind\Auth\Manager as BaseManager, VuFind\Db\Table\User as UserTable, VuFind\Auth\PluginManager as PluginManager, Zend\Config\Config as Config, Zend\Session\SessionManager as SessionManager, VuFind\Cookie\CookieManager, Zend\ServiceManager\ServiceLocatorAwareInterface, Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package Authentication
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class Manager extends BaseManager
{

    protected $configAccessor;

    protected $authenticator;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     */
    public function __construct(Config $config, UserTable $userTable, SessionManager $sessionManager, PluginManager $pm, CookieManager $cookieManager)
    {
        $this->configAccessor = $config;

        parent::__construct($config, $userTable, $sessionManager, $pm, $cookieManager);
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
        // Perform authentication:
        try {
            $this->authenticator = $this->getAuth();
            $user = $this->authenticator->authenticate($request);
        } catch (AuthException $e) {
            // Pass authentication exceptions through unmodified
            throw $e;
        } catch (\VuFind\Exception\PasswordSecurity $e) {
            // Pass password security exceptions through unmodified
            throw $e;
        } catch (\Exception $e) {
            // Catch other exceptions, log verbosely, and treat them as technical
            // difficulties
            error_log("Exception in " . get_class($this) . "::login: " . $e->getMessage());
            error_log($e);
            throw new AuthException('authentication_error_technical');
        }

        // Store the user in the session if not handling this later
        if (! empty($user['id']))
            $this->updateSession($user);
        else
            $user = $this->handleLibraryCards($user);

        // Send it back to caller
        return $user;
    }

    /**
     * If user doesn't exists nor in the user_card table, than
     * are created user_cards based on institutes user is member of.
     *
     * If user exists but only in user_card table, that card is then
     * activated and all user's institutes are refreshed from IdP.
     *
     * If is user found in user table, than are just refreshed user's
     * institutes from IdP.
     *
     * @param \VuFind\Db\Row\User $user
     * @return \VuFind\Db\Row\User $user
     */
    protected function handleLibraryCards($user)
    {
        $tableManager = $this->authenticator->getDbTableManager();
        $userCardTable = $tableManager->get("UserCard");

        $institutesUserIsIn = $this->getInstitutesUserIsIn($user);

        // Is the incoming $user variable actually a user_card ??
        // If it is, than the cat_username isn't in user table, but in user_card
        $isInputOnlyUserCard = $this->isInputOnlyUserCard($user, $userCardTable);

        if ($isInputOnlyUserCard) {

            $inputUserCard = $user;

            $userId = $isInputOnlyUserCard['user_id'];

            $user = $this->userTable->select([
                'id' => $userId
            ])->current();

            // User we have just accessed does not have to exist anymore at the IdP side .. check it out
            $userExistsOnIdPSide = false;
            foreach ($institutesUserIsIn as $instituteUserIsIn) {
                if ($instituteUserIsIn['user'] == $user['cat_username']) {
                    $userExistsOnIdPSide = $instituteUserIsIn;
                    break;
                }
            }

            // If the user does not exists on the IdP side anymore, replace it with identity used to log in
            if (!$userExistsOnIdPSide) {
                $user['username'] = $inputUserCard['username'];
                $user['cat_username'] = $inputUserCard['cat_username'];
                $user['home_library'] = $inputUserCard['home_library'];
                $user['email'] = $inputUserCard['email'];
                $user['firstname'] = $inputUserCard['firstname'];
                $user['lastname'] = $inputUserCard['lastname'];
            } else {
                // Update $user from IdP
                // Being here means cat_username matches, thus update library only ..
                $user['home_library'] = $instituteUserIsIn['lib'];
            }

            // Update user session
            $this->updateSession($user);
        }

        // Finally save the user if it was not done before
        if ($this->configAccessor->UserCards['ignore_default_user_creation'])
            $user->save();

        // Now delete all user cards & create new from IdP fresh list of Institutes
        $resultSet = $userCardTable->select(['user_id' => $user['id']]);
        foreach($resultSet as $result) {
            $result->delete();
        }

        foreach ($institutesUserIsIn as $instituteUserIsIn) {
            $cat_username = $instituteUserIsIn['lib'] . "." . $instituteUserIsIn['user'];
            $home_library = $instituteUserIsIn['lib'];
            try {
                $user->saveLibraryCard(null, '', $cat_username, null, $home_library);
            } catch (\VuFind\Exception\LibraryCard $e) {
                $this->flashMessenger()
                    ->setNamespace('error')
                    ->addMessage($e->getMessage());
                return false;
            }
        }

        // TODO: If there was more than 1 card, ask user which one will be his default unless he has chosen it already

        return $user;
    }

    protected function isInputOnlyUserCard($user, $userCardTable)
    {
        $cat_username = $user['cat_username'];

        $userFromUserTable = $this->userTable->select([
            'cat_username' => $cat_username
        ])->current();

        $userCard = $userCardTable->select([
            'cat_username' => $cat_username
        ])->current();

        // User is not in User table & is in UserCard table ..
        return (empty($userFromUserTable) && ! empty($userCard)) ? false : $userCard;
    }

    protected function getInstitutesUserIsIn($user)
    {
        return array(
            array(
                "user" => "700",
                "lib" => "mzk"
            ),
            array(
                "user" => "700",
                "lib" => "MZKLIB1"
            ),
            array(
                "user" => "3",
                "lib" => "KOHALIB1"
            )
        );
    }
}
