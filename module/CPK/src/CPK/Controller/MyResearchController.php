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

use MZKCommon\Controller\MyResearchController as MyResearchControllerBase, VuFind\Exception\Auth as AuthException, VuFind\Exception\ListPermission as ListPermissionException, VuFind\Exception\RecordMissing as RecordMissingException, Zend\Stdlib\Parameters, MZKCommon\Controller\ExceptionsTrait;

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
    use ExceptionsTrait;

    public function logoutAction()
    {
        $logoutTarget = $this->getConfig()->Site->url;
        return $this->redirect()->toUrl(
            $this->getAuthManager()
                ->logout($logoutTarget));
    }

    public function profileAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {

            if ($this->listsEnabled()) {
                return $this->forwardTo('Search', 'History');
            }
            return $this->forwardTo('MyResearch', 'Favorites');
        }

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        $logos = $user->getIdentityProvidersLogos();

        // Obtain user information from ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_profile']) &&
             $config['General']['async_profile'])
            $isSynchronous = false;
        else
            $isSynchronous = true;

        $viewVars['isSynchronous'] = $isSynchronous;

        foreach ($identities as $identity) {

            // Begin building view object:
            $currentIdentityView = $this->createViewModel();

            if (! $isSynchronous) {

                // We only need to let AJAX handle itself with the right data
                $profile = $user->libCardToPatronArray($identity);
            } else {

                $patron = $user->libCardToPatronArray($identity);

                $profile = $catalog->getMyProfile($patron);

                $profile['prolongRegistrationUrl'] = $catalog->getProlongRegistrationUrl(
                    $profile);

                $this->processBlocks($profile, $logos);
            }

            $currentIdentityView->profile = $profile;
            $libraryIdentities[$identity['eppn']] = $currentIdentityView;
        }

        $viewVars['libraryIdentities'] = $libraryIdentities;
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
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
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_holds']) &&
             $config['General']['async_holds'])
            $isSynchronous = false;
        else
            $isSynchronous = true;

        $viewVars = $patrons = [];

        $identities = $user->getLibraryCards();

        // Process cancel jobs if any ..
        foreach ($identities as $identity) {

            $patron = $user->libCardToPatronArray($identity);

            // Start of VuFind/MyResearch/holdsAction

            // Process cancel requests if necessary:
            $cancelStatus = $catalog->checkFunction('cancelHolds', compact('patron'));
            $patron['cancelResults'] = $cancelStatus ? $this->holds()->cancelHolds(
                $catalog, $patron, $isSynchronous) : [];
            // If we need to confirm
            if (! is_array($patron['cancelResults'])) {
                return $patron['cancelResults'];
            }

            $eppn = $patron['eppn'];
            $patrons[$eppn] = $patron;
        }

        $this->holds()->resetValidation();

        // Now process actual holds rendering
        foreach ($patrons as $eppn => $patron) {

            $currentIdentityView = $this->createViewModel();

            // Append cancelResults from previous iteration ..
            $currentIdentityView->cancelResults = $patron['cancelResults'];

            if ($isSynchronous) {
                // Get held item details:
                $result = $catalog->getMyHolds($patron);
                $recordList = [];

                // Let's assume there is not avaiable any cancelling
                $currentIdentityView->cancelForm = false;

                foreach ($result as $current) {
                    // Add cancel details if appropriate:
                    $current = $this->holds()->addCancelDetails($catalog, $current,
                        $cancelStatus);
                    if ($cancelStatus &&
                         $cancelStatus['function'] != "getCancelHoldLink" &&
                         isset($current['cancel_details'])) {
                        // Enable cancel form if necessary:
                        $currentIdentityView->cancelForm = true;
                    }

                    // Build record driver:
                    $recordList[] = $this->getDriverForILSRecord($current);
                }

                // Get List of PickUp Libraries based on patron's home library
                try {
                    $currentIdentityView->pickup = $catalog->getPickUpLocations(
                        $patron);
                } catch (\Exception $e) {
                    // Do nothing; if we're unable to load information about pickup
                    // locations, they are not supported and we should ignore them.
                }
                $currentIdentityView->recordList = $recordList;
            } else // This means we have async deal ..
                $currentIdentityView->cat_username = $patron['cat_username'];

            // Unknown purpose .. copied from parent MZKCommon ..
            $currentIdentityView = $this->addViews($currentIdentityView);

            $viewVars['libraryIdentities'][$eppn] = $currentIdentityView;
        }

        $viewVars['isSynchronous'] = $isSynchronous;

        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('LibraryCards', 'Home');
        }

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        // Connect to the ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_checkedout']) &&
             $config['General']['async_checkedout'])
            $isSynchronous = false;
        else
            $isSynchronous = true;

        $viewVars['isSynchronous'] = $isSynchronous;

        foreach ($identities as $identity) {

            $patron = $user->libCardToPatronArray($identity);

            // Start of VuFind/MyResearch/checkedoutAction

            // Get the current renewal status and process renewal form, if necessary:
            $renewStatus = $catalog->checkFunction('Renewals', compact('patron'));
            $renewResult = $renewStatus ? $this->renewals()->processRenewals(
                $this->getRequest()
                    ->getPost(), $catalog, $patron) : [];

            if (is_array($renewResult) && count($renewResult)) {
                foreach ($renewResult as $id => $detail) {
                    if ($detail['success'] === false && isset($detail['sysMessage'])) {
                        $this->flashMessenger()
                            ->setNamespace('error')
                            ->addMessage($detail['sysMessage']);
                    }
                }
            }

            // By default, assume we will not need to display a renewal form:
            $renewForm = false;

            if ($isSynchronous) {

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
                    $paginator->setCurrentPageNumber(
                        $this->params()
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
                    $current = $this->renewals()->addRenewDetails($catalog, $current,
                        $renewStatus);
                    if ($renewStatus && ! isset($current['renew_link']) &&
                         $current['renewable']) {
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

                $currentIdentityView = compact('transactions', 'renewForm',
                    'renewResult', 'paginator', 'hiddenTransactions');
                // End of VuFind/MyResearch/checkedoutAction

                // Start of MZKCommon/MyResearch/checkedoutAction
                $showOverdueMessage = false;
                foreach ($currentIdentityView['transactions'] as $resource) {
                    $ilsDetails = $resource->getExtraDetail('ils_details');
                    if (isset($ilsDetails['dueStatus']) &&
                         $ilsDetails['dueStatus'] == "overdue") {
                        $showOverdueMessage = true;
                        break;
                    }
                }
                if ($showOverdueMessage) {
                    $this->flashMessenger()
                        ->setNamespace('error')
                        ->addMessage('overdue_error_message');
                }
                $currentIdentityView['history'] = false;
            } else
                $currentIdentityView['cat_username'] = $patron['cat_username'];

            $currentIdentityView = $this->addViews($currentIdentityView);
            // End of MZKCommon/MyResearch/checkedoutAction

            $libraryIdentities[$identity['eppn']] = $currentIdentityView;
        }

        $viewVars['libraryIdentities'] = $libraryIdentities;
        $view = $this->createViewModel($viewVars);

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
            // Fail if lists are disabled:
        if (! $this->listsEnabled()) {
            throw new \Exception('Lists disabled');
        }
        
        $config = $this->getConfig();
        
        // Are "offline favorites" enabled ?
        $offlineFavoritesEnabled = false;
        
        if ($config->Site['offlineFavoritesEnabled'] !== null) {
            $offlineFavoritesEnabled = (bool) $config->Site['offlineFavoritesEnabled'];
        }
        
        // And is user not logged in ?
        $userNotLoggedIn = $this->getUser() === false;
        
        if ($offlineFavoritesEnabled && $userNotLoggedIn) {
            // Well then, render the favorites for not logged in user & let JS handle it ..
            
            return $this->createViewModel([
                'loggedIn' => false
            ]);
        } else {
            // Nope, let's behave the old-style :)
            return parent::mylistAction();
        }
    }

    public function userConnectAction()
    {
        // This eid serves only to warn user he wants to connect the same instituion account
        if (isset($_GET['eid']))
            $entityIdInitiatedWith = $_GET['eid'];
        else
            $entityIdInitiatedWith = null;

        $patron = $this->catalogLogin();

        // We can't really determine if is user logged in if entityIdInitiatedWith is provided
        // We have to leave this on ShibbolethIdentityManager ..
        $haveToLogin = ! is_array($patron) &&
             ! $this->isLoggedInWithDummyDriver($patron) &&
             empty($entityIdInitiatedWith);

        // Stop now if the user does not have valid catalog credentials available:
        if ($haveToLogin) {
            $this->flashExceptions($this->flashMessenger());
            $this->clearFollowupUrl();
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
                $user = $this->getAuthManager()->consolidateIdentity();
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }

            if ($user->consolidationSucceeded)
                $this->processSuccessMessage(
                    "Identities were successfully connected");

                // Show user all his connected identities
            return $this->redirect()->toRoute('librarycards-home');
        }
    }

    /**
     * Send list of fines to view
     *
     * @return mixed
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        $identities = $user->getLibraryCards();

        $viewVars = $libraryIdentities = [];

        $allFines = [];

        // Connect to the ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_fines']) &&
             $config['General']['async_fines'])
            $isSynchronous = false;
        else
            $isSynchronous = true;

        $viewVars['isSynchronous'] = $isSynchronous;

        foreach ($identities as $identity) {

            $patron = $user->libCardToPatronArray($identity);

            // Begin building view object:
            $currentIdentityView = $this->createViewModel();

            $fines = [];

            if (! $isSynchronous) {
                $fines['cat_username'] = $patron['cat_username'];
            } else {
                // Get fine details:
                $result = $catalog->getMyFines($patron);

                foreach ($result as $row) {
                    // Attempt to look up and inject title:
                    try {
                        if (! isset($row['id']) || empty($row['id'])) {
                            throw new \Exception();
                        }
                        $source = isset($row['source']) ? $row['source'] : 'VuFind';
                        $row['driver'] = $this->getServiceLocator()
                            ->get('VuFind\RecordLoader')
                            ->load($row['id'], $source);
                        $row['title'] = $row['driver']->getShortTitle();
                    } catch (\Exception $e) {
                        if (! isset($row['title'])) {
                            $row['title'] = null;
                        }
                    }
                    $fines[] = $row;
                }
            }
            $allFines[$identity['eppn']] = $fines;
        }
        $viewVars['fines'] = $allFines;
        $totalFine = 0;
        if (! empty($result)) {
            foreach ($result as $fine) {
                $totalFine += ($fine['amount']);
            }
        }
        if ($totalFine < 0) {
            $viewVars['paymentUrl'] = $catalog->getPaymentURL($patron,
                - 1 * $totalFine);
        }
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
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

    protected function isLoggedInWithDummyDriver($user)
    {
        if ($user instanceof \Zend\View\Model\ViewModel)
            return false;
        return isset($user['home_library']) ? $user['home_library'] == "Dummy" : false;
    }

    protected function processBlocks($profile, $logos)
    {
        if (isset($profile['blocks'])) {
            if ($logos instanceof \Zend\Config\Config) {
                $logos = $logos->toArray();
            }

            foreach ($profile['blocks'] as $institution => $block) {
                if (isset($logos[$institution]))
                    $logo = $logos[$institution];
                else
                    $logo = $institution;

                $message[$logo] = $block;

                $this->flashMessenger()
                    ->setNamespace('error')
                    ->addMessage($message);
            }
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
    
    /**
     * Settings view
     *
     * @return Zend\View\Model\ViewModel
     */
    public function settingsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }
        
        /* Citation style fieldset */
        $citationStyleTable = $this->getTable('citationstyle');
        $availableCitationStyles = $citationStyleTable->getAllStyles();
        
        $defaultCitationStyleValue = $this->getConfig()->Record->default_citation_style;
        
        foreach ($availableCitationStyles as $style) {
            if ($style['value'] === $defaultCitationStyleValue) {
                $defaultCitationStyle = $style['id'];
                break;
            }
        }
        
        $userSettingsTable = $this->getTable("usersettings");
        $preferedCitationStyle = $userSettingsTable->getUserCitationStyle($user);
        
        $selectedCitationStyle = (! empty($preferedCitationStyle)) 
            ? $preferedCitationStyle 
            : $defaultCitationStyle;
        
        $viewVars['selectedCitationStyle']   = $selectedCitationStyle;
        $viewVars['availableCitationStyles'] = $availableCitationStyles;
        
        /* Records per page fieldset */
        $searchesConfig = $this->getConfig('searches');
        $recordsPerPageOptions = explode(",", $searchesConfig->General->limit_options);
        $recordsPerPageDefaultValue = $searchesConfig->General->default_limit;
        $preferredRecordsPerPageValue = $userSettingsTable->getRecordsPerPage($user);
        
        $selectedRecordsPerPageOption = (! empty($preferredRecordsPerPageValue))
        ? $preferredRecordsPerPageValue
        : $recordsPerPageDefaultValue;
        
        $viewVars['recordsPerPageOptions'] = $recordsPerPageOptions;
        $viewVars['selectedRecordsPerPageOption'] = $selectedRecordsPerPageOption;
        
        /* Sorting fieldset */
        $sortingOptions = $searchesConfig->Sorting->toArray();
        $defaultSorting = $searchesConfig->General->default_sort;
        $preferredSorting = $userSettingsTable->getSorting($user);
        
        $selectedSorting = (! empty($preferredSorting))
        ? $preferredSorting
        : $defaultSorting;
        
        $viewVars['sortingOptions'] = $sortingOptions;
        $viewVars['selectedSorting'] = $selectedSorting;
        
        //
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
    }
}
