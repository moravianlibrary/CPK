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

use MZKCommon\Controller\MyResearchController as MyResearchControllerBase, VuFind\Exception\Auth as AuthException, VuFind\Exception\ListPermission as ListPermissionException, VuFind\Exception\RecordMissing as RecordMissingException, Zend\Stdlib\Parameters;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package Controller
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class MyResearchController extends MyResearchControllerBase
{

    /**
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        return parent::loginAction();
    }

    public function logoutAction()
    {
        $logoutTarget = $this->getConfig()->Site->url;
        return $this->redirect()->toUrl($this->getAuthManager()
            ->logout($logoutTarget));
    }

    public function profileAction()
    {
        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver()) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        $view = parent::profileAction();

        if ($view) {
            $this->checkBlocks($view->__get('profile'));
        }

        return $view;
    }

    public function userConnectAction()
    {
        // This eid serves only to warn user he wants to connect the same instituion account
        $entityIdInitiatedWith = $_GET['eid'];

        // Stop now if the user does not have valid catalog credentials available:
        if (empty($entityIdInitiatedWith) && ! $this->isLoggedInWithDummyDriver() && ! is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Perform local logout & redirect user to force him login to another account

        $authManager = $this->getAuthManager();

        if (empty($entityIdInitiatedWith)) {
            try {
                $redirectTo = $authManager->getAccountConsolidationRedirectUrl();

                return $this->redirect()->toUrl($redirectTo);
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);

                return $this->redirect()->toRoute('librarycards-home');
            }
        } else {

            // Clear followUp ...
            if ($this->getFollowupUrl())
                $this->clearFollowupUrl();

            try {
                $user = $this->getAuthManager()->connectIdentity();
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }

            if ($user->consolidationSucceeded)
                $this->processSuccessMessage("Identities were successfully connected");

            // Show user all his connected identities
            return $this->redirect()->toRoute('librarycards-home');
        }
    }

    protected function checkBlocks($profile)
    {
        foreach ($profile['blocks'] as $block) {
            if (! empty($block)) {
                $this->flashMessenger()->addErrorMessage($block);
            }
        }
    }

    protected function isLoggedInWithDummyDriver()
    {
        $user = $this->getAuthManager()->isLoggedIn();
        return $user ? $user['home_library'] == "Dummy" : false;
    }

    /**
     * Processess success message to Zend's flashMessenger
     *
     * @param string $msg
     */
    protected function processSuccessMessage($msg) {
        $this->flashMessenger()->setNamespace('success')->addMessage($msg);
    }
}
