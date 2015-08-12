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

        // Stop now if the user does not have valid catalog credentials available:
        if (! is_array($patron = $this->catalogLogin())) {
            $this->flashExceptions();
            return $patron;
        }

        $user = $this->getAuthManager()->isLoggedIn();

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        $logos = $user->getIdentityProvidersLogos();

        foreach ($identities as $identity) {

            $profileFetched = $identity->cat_username === $patron['cat_username'];

            if (! $profileFetched)
                $patron = $this->parsePatronFromIdentity($identity);

                // Here starts VuFind/MyResearch/profileAction
                // Process home library parameter (if present):
            $homeLibrary = $this->params()->fromPost('home_library', false);
            if (! empty($homeLibrary)) {
                $user->changeHomeLibrary($homeLibrary);
                $this->getAuthManager()->updateSession($user);
                $this->flashMessenger()
                    ->setNamespace('info')
                    ->addMessage('profile_update');
            }

            // Begin building view object:
            $currentIdentityView = $this->createViewModel();

            // Obtain user information from ILS:
            $catalog = $this->getILS();

            if (! $profileFetched) {
                $profile = $catalog->getMyProfile($patron);
            } else
                $profile = $patron;

            $profile['home_library'] = $user->home_library;
            $currentIdentityView->profile = $profile;

            try {
                $currentIdentityView->pickup = $catalog->getPickUpLocations($patron);
                $currentIdentityView->defaultPickupLocation = $catalog->getDefaultPickUpLocation($patron);
            } catch (\Exception $e) {
                // Do nothing; if we're unable to load information about pickup
                // locations, they are not supported and we should ignore them.
            }
            // Here ends VuFind/MyResearch/profileAction

            // Here starts VuFind/MZKCommon/profileAction
            if ($currentIdentityView) {
                $catalog = $this->getILS();
                $currentIdentityView->profileChange = $catalog->checkCapability('changeUserRequest');
            }

            // Here ends VuFind/MZKCommon/profileAction

            if ($currentIdentityView) {
                $this->processBlocks($currentIdentityView->__get('profile'), $logos);
            }

            $libraryIdentities[$identity['eppn']] = $currentIdentityView;
        }

        $viewVars['libraryIdentities'] = $libraryIdentities;
        $viewVars['logos'] = $logos;
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions();
        return $view;
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $user = $this->getAuthManager()->isLoggedIn();

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        foreach ($identities as $identity) {

            $patron = $this->parsePatronFromIdentity($identity);
            // Start of VuFind/MyResearch/holdsAction

            // Connect to the ILS:
            $catalog = $this->getILS();

            // Process cancel requests if necessary:
            $cancelStatus = $catalog->checkFunction('cancelHolds', compact('patron'));
            $currentIdentityView = $this->createViewModel();
            $currentIdentityView->cancelResults = $cancelStatus ? $this->holds()->cancelHolds($catalog, $patron) : [];
            // If we need to confirm
            if (! is_array($currentIdentityView->cancelResults)) {
                return $currentIdentityView->cancelResults;
            }

            // By default, assume we will not need to display a cancel form:
            $currentIdentityView->cancelForm = false;

            // Get held item details:
            $result = $catalog->getMyHolds($patron);
            $recordList = [];
            $this->holds()->resetValidation();
            foreach ($result as $current) {
                // Add cancel details if appropriate:
                $current = $this->holds()->addCancelDetails($catalog, $current, $cancelStatus);
                if ($cancelStatus && $cancelStatus['function'] != "getCancelHoldLink" && isset($current['cancel_details'])) {
                    // Enable cancel form if necessary:
                    $currentIdentityView->cancelForm = true;
                }

                // Build record driver:
                $recordList[] = $this->getDriverForILSRecord($current);
            }

            // Get List of PickUp Libraries based on patron's home library
            try {
                $currentIdentityView->pickup = $catalog->getPickUpLocations($patron);
            } catch (\Exception $e) {
                // Do nothing; if we're unable to load information about pickup
                // locations, they are not supported and we should ignore them.
            }
            $currentIdentityView->recordList = $recordList;
            // End of VuFind/MyResearch/holdsAction

            // Start of MZKCommon/MyResearch/holdsAction
            $currentIdentityView = $this->addViews($currentIdentityView);
            // End of MZKCommon/MyResearch/holdsAction

            $libraryIdentities[$identity['eppn']] = $currentIdentityView;
        }

        $viewVars['libraryIdentities'] = $libraryIdentities;
        $viewVars['logos'] = $user->getIdentityProvidersLogos();
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions();
        return $view;
    }

    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $user = $this->getAuthManager()->isLoggedIn();

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        foreach ($identities as $identity) {

            $patron = $this->parsePatronFromIdentity($identity);

            // Start of VuFind/MyResearch/checkedoutAction

            // Connect to the ILS:
            $catalog = $this->getILS();

            // Get the current renewal status and process renewal form, if necessary:
            $renewStatus = $catalog->checkFunction('Renewals', compact('patron'));
            $renewResult = $renewStatus ? $this->renewals()->processRenewals($this->getRequest()
                ->getPost(), $catalog, $patron) : [];

            // By default, assume we will not need to display a renewal form:
            $renewForm = false;

            // Get checked out item details:
            $result = $catalog->getMyTransactions($patron);

            // Get page size:
            $config = $this->getConfig();
            $limit = isset($config->Catalog->checked_out_page_size) ? $config->Catalog->checked_out_page_size : 50;

            // Build paginator if needed:
            if ($limit > 0 && $limit < count($result)) {
                $adapter = new \Zend\Paginator\Adapter\ArrayAdapter($result);
                $paginator = new \Zend\Paginator\Paginator($adapter);
                $paginator->setItemCountPerPage($limit);
                $paginator->setCurrentPageNumber($this->params()
                    ->fromQuery('page', 1));
                $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
                $pageEnd = $paginator->getAbsoluteItemNumber($limit) - 1;
            } else {
                $paginator = false;
                $pageStart = 0;
                $pageEnd = count($result);
            }

            $transactions = $hiddenTransactions = [];
            foreach ($result as $i => $current) {
                // Add renewal details if appropriate:
                $current = $this->renewals()->addRenewDetails($catalog, $current, $renewStatus);
                if ($renewStatus && ! isset($current['renew_link']) && $current['renewable']) {
                    // Enable renewal form if necessary:
                    $renewForm = true;
                }

                // Build record driver (only for the current visible page):
                if ($i >= $pageStart && $i <= $pageEnd) {
                    $transactions[] = $this->getDriverForILSRecord($current);
                } else {
                    $hiddenTransactions[] = $current;
                }
            }

            $currentIdentityView = compact('transactions', 'renewForm', 'renewResult', 'paginator', 'hiddenTransactions');
            // End of VuFind/MyResearch/checkedoutAction

            // Start of MZKCommon/MyResearch/checkedoutAction
            $showOverdueMessage = false;
            foreach ($currentIdentityView->transactions as $resource) {
                $ilsDetails = $resource->getExtraDetail('ils_details');
                if (isset($ilsDetails['dueStatus']) && $ilsDetails['dueStatus'] == "overdue") {
                    $showOverdueMessage = true;
                    break;
                }
            }
            if ($showOverdueMessage) {
                $this->flashMessenger()
                    ->setNamespace('error')
                    ->addMessage('overdue_error_message');
            }
            $currentIdentityView->history = false;
            $currentIdentityView = $this->addViews($currentIdentityView);
            // End of MZKCommon/MyResearch/checkedoutAction

            $libraryIdentities[$identity['eppn']] = $currentIdentityView;
        }

        $viewVars['libraryIdentities'] = $libraryIdentities;
        $viewVars['logos'] = $user->getIdentityProvidersLogos();
        $view = $this->createViewModel($viewVars);

        $this->flashExceptions();
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

    /**
     * Returns redirect to myresearch-home because the ill requests are disabled.
     *
     * @return \Zend\Http\Response
     */
    public function illRequestsAction()
    {
        return $this->redirect()->toRoute('myresearch-home');
    }

    protected function isLoggedInWithDummyDriver()
    {
        $user = $this->getAuthManager()->isLoggedIn();
        return $user ? $user['home_library'] == "Dummy" : false;
    }

    protected function processBlocks($profile, $logos)
    {
        if ($logos instanceof \Zend\Config\Config) {
            $logos = $logos->toArray();
        }

        foreach ($profile['blocks'] as $institution => $block) {
            $logo = $logos[$institution];

            $message[$logo] = $block;

            $this->flashMessenger()->setNamespace('error')->addMessage($message);
        }
    }

    /**
     * Processess success message to Zend's flashMessenger
     *
     * @param string $msg
     */
    protected function processSuccessMessage($msg)
    {
        $this->flashMessenger()
            ->setNamespace('success')
            ->addMessage($msg);
    }

    protected function parsePatronFromIdentity($identity)
    {
        $patron['cat_username'] = $identity->cat_username;
        $patron['mail'] = $identity->card_name;
        $patron['eppn'] = $identity->eppn;

        $patron['id'] = $patron['cat_username'];
        return $patron;
    }
}
