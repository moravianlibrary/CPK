<?php
/**
 * MyResearch Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

use VuFind\Controller\LibraryCardsController as LibraryCardsControllerBase, CPK\Db\Row\User as UserRow, MZKCommon\Controller\ExceptionsTrait;
use Zend\Mvc\MvcEvent;
use CPK\Auth\Manager;
use CPK\Auth\ShibbolethIdentityManager;
use CPK\Db\Table\User;

/**
 * Controller for the library card functionality.
 *
 * @category VuFind2
 * @package Controller
 * @author Demian Katz <demian.katz@villanova.edu>
 * @author Ere Maijala <ere.maijala@helsinki.fi>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class LibraryCardsController extends LibraryCardsControllerBase
{
    use ExceptionsTrait, LoginTrait;

    /**
     * Send user's library cards to the view
     *
     * @return mixed
     */
    public function homeAction()
    {
        if ($this->params()->fromPost('processLogin')
            || $this->getSessionInitiator()
            || $this->params()->fromPost('auth_method')
            || $this->params()->fromQuery('auth_method')
            ) {
                try {
                    if (!$this->getAuthManager()->isLoggedIn()) {
                        $this->getAuthManager()->login($this->getRequest());
                    }
                } catch (AuthException $e) {
                    $this->processAuthenticationException($e);
                }
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Check for "delete card" request; parameter may be in GET or POST depending
        // on calling context.
        $deleteId = $this->params()->fromPost('delete',
            $this->params()
                ->fromQuery('delete'));
        if ($deleteId) {
            // If the user already confirmed the operation, perform the delete now;
            // otherwise prompt for confirmation:
            $confirm = $this->params()->fromPost('confirm',
                $this->params()
                    ->fromQuery('confirm'));
            if ($confirm) {
                $success = $this->performDeleteLibraryCard($deleteId);
                if ($success !== true) {
                    return $success;
                }
            } else {
                return $this->confirmDeleteLibraryCard($deleteId);
            }
        }

        // Connect to the ILS for login drivers:
        $catalog = $this->getILS();

        return $this->createViewModel(
            [
                'libraryCards' => $user->getAllUserLibraryCards()
            ]);
    }

    /**
     * Redirects to librarycards-home
     *
     * @return mixed
     */
    public function deleteCardAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Get requested library card ID:
        $cardID = $this->params()->fromPost('cardID',
            $this->params()
                ->fromQuery('cardID'));

        if (! $user->hasThisLibraryCard($cardID)) {
            // Success Message
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage('Cannot disconnect foreign identity');
            // Redirect to MyResearch library cards
            return $this->redirect()->toRoute('librarycards-home');
        }

        $confirm = $this->params()->fromPost('confirm',
            $this->params()
                ->fromQuery('confirm'));

        if ($confirm) {
            $userLoggedInWithDisconnectedIdentity = $user->disconnectIdentity($cardID);

            // Success Message
            $this->flashMessenger()
                ->setNamespace('success')
                ->addMessage('Identity disconnected');
            // Redirect to MyResearch library cards

            if ($userLoggedInWithDisconnectedIdentity) {
                $authManager = $this->getServiceLocator()->get('VuFind\AuthManager');

                if ($authManager instanceof Manager) {
                    $relogUrl = $this->getRelogUrl($authManager);

                    // Perform only local logout
                    $authManager->logout('');

                    // Destroy consolidation cookie
                    $this->getUserTable()->deleteConsolidationToken($_COOKIE[ShibbolethIdentityManager::CONSOLIDATION_TOKEN_TAG]);

                    // Clearing user's COOKIE is quite impossible while redirecting
                    // We should do that after redirected

                    // "New account" will be now created from the one user used to login with
                    // Automatically accept the terms of use again as the user has appaerantely already accepted those
                    return $this->redirect()->toUrl($relogUrl);
                }
            }

            return $this->redirect()->toRoute('librarycards-home');
        }

        // If we got this far, we must display a confirmation message:
        return $this->confirm('confirm_delete_library_card_brief',
            $this->url()
                ->fromRoute('librarycards-deletecard'),
            $this->url()
                ->fromRoute('librarycards-home'), 'confirm_delete_library_card_text',
            [
                'cardID' => $cardID
            ]);
    }

    /**
     * Only identity removal is allowed - redirect user to libcards/home
     *
     * @return mixed
     */
    public function selectCardAction()
    {
        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Only identity removal is allowed - redirect user to libcards/home
     *
     * @return mixed
     */
    public function editCardAction()
    {
        return $this->redirect()->toRoute('librarycards-home');
    }

    protected function getRelogUrl(Manager $authManager)
    {
        $loginUrl = $authManager->getSessionInitiatorForEntityId($_SESSION['Account']->eidLoggedInWith);

        preg_match('/target=([^&]+)/', $loginUrl, $shibTargetRaw);

        $shibTargetOldRaw = urldecode($shibTargetRaw[1]);

        $appendix = '&';

        if (strpos($shibTargetOldRaw, '?') === false)
            $appendix .= '?';

            // Switch the common target with custom target
        $shibTargetNewRaw = $this->url()->fromRoute('librarycards-home') . $appendix . 'terms_of_use_accepted=yes';

        $shibTargetOld = urlencode($shibTargetOldRaw);
        $shibTargetNew = urlencode($shibTargetNewRaw);

        $loginUrl = str_replace($shibTargetOld, $shibTargetNew, $loginUrl);

        return $loginUrl;
    }

    /**
     * Process an authentication error.
     *
     * @param AuthException $e Exception to process.
     *
     * @return void
     */
    protected function processAuthenticationException(AuthException $e)
    {
        $msg = $e->getMessage();
        // If a Shibboleth-style login has failed and the user just logged
        // out, we need to override the error message with a more relevant
        // one:
        if ($msg == 'authentication_error_admin'
            && $this->getAuthManager()->userHasLoggedOut()
            && $this->getSessionInitiator()
        ) {
            $msg = 'authentication_error_loggedout';
        }
        $this->flashMessenger()->addMessage($msg, 'error');
    }

    /**
     * Convenience method to get a session initiator URL. Returns false if not
     * applicable.
     *
     * @return string|bool
     */
    protected function getSessionInitiator()
    {
        $url = $this->getServerUrl('myresearch-home');
        return $this->getAuthManager()->getSessionInitiator($url);
    }

    /**
     * Retruns table of User database
     *
     * @return User
     */
    protected function getUserTable()
    {
        if(! isset($this->userTable)) {
            $this->userTable = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')->get('User');
        }

        return $this->userTable;
    }
}
