<?php
/**
 * MyResearch Controller
 *
 * PHP version 5.6
 *
 * Copyright (C) Moravian Library 2016.
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
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @author Martin Kravec <kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

use CPK\Auth\Manager as AuthManager;
use CPK\Controller\Exception\TicketNotFoundException;
use Mzk\ZiskejApi\RequestModel\Message;
use VuFind\Controller\MyResearchController as MyResearchControllerBase;
use VuFind\Exception\Auth as AuthException;
use Zend\Session\Container as SessionContainer;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package Controller
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @author Martin Kravec <kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class MyResearchController extends MyResearchControllerBase
{
    use ExceptionsTrait, LoginTrait;

    /**
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        $view = parent::loginAction();

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

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
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

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
            $config['General']['async_profile']) {
            $isSynchronous = false;
        } else {
            $isSynchronous = true;
        }

        $viewVars['isSynchronous'] = $isSynchronous;

        foreach ($identities as $identity) {

            // Begin building view object:
            $currentIdentityView = $this->createViewModel();

            if (!$isSynchronous) {

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
     * @return array|mixed|\Zend\View\Model\ViewModel
     *
     * @throws \Http\Client\Exception
     * @throws \VuFind\Exception\LibraryCard
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_holds']) &&
            $config['General']['async_holds']) {
            $isSynchronous = false;
        } else {
            $isSynchronous = true;
        }

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
            if (!is_array($patron['cancelResults'])) {
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
            {
                $currentIdentityView->cat_username = $patron['cat_username'];
            }

            // Unknown purpose .. copied from parent MZKCommon ..
            $currentIdentityView = $this->addViews($currentIdentityView);

            $viewVars['libraryIdentities'][$eppn] = $currentIdentityView;
        }

        $viewVars['isSynchronous'] = $isSynchronous;

        $request = $this->getRequest();
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    public function profileChangeAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $op = $this->params()->fromQuery('op');
        $catalog = $this->getILS();
        $fromPost = $this->params()->fromPost('change');
        $hmac = $this->params()->fromPost('hmac');
        if ($fromPost) {
            if ($hmac != $this->getHMAC()) {
                $this->flashMessenger()->setNamespace('info')->addMessage('request_failed_due_to_hmac');
            } elseif ($op == 'nickname') {
                $nickname = trim($this->params()->fromPost('nickname'));
                if (!empty($nickname)) {
                    try {
                        $catalog->changeUserNickname($patron, trim($nickname));
                        $this->flashMessenger()->setNamespace('info')->addMessage('nickname_change_successful');
                        return $this->redirect()->toRoute('myresearch-profile');
                    } catch (\VuFind\Exception\ILS $ex) {
                        $this->flashMessenger()->setNamespace('error')->addMessage('nickname_change_error');
                    }
                } else {
                    $this->flashMessenger()->setNamespace('error')->addMessage('nickname_empty_error');
                }
            } elseif ($op == 'email') {
                $email = trim($this->params()->fromPost('email'));
                if (!empty($email)) {
                    try {
                        $catalog->changeUserEmailAddress($patron, trim($email));
                        $this->flashMessenger()->setNamespace('info')->addMessage('email_change_successful');
                        $user->email = trim($email);
                        $user->save();
                        return $this->redirect()->toRoute('myresearch-profile');
                    } catch (\VuFind\Exception\ILS $ex) {
                        $this->flashMessenger()->setNamespace('error')->addMessage('email_change_error');
                    }
                } else {
                    $this->flashMessenger()->setNamespace('error')->addMessage('email_empty_error');
                }
            } elseif ($op == 'password') {
                $oldPassword = $this->params()->fromPost('old_password');
                $newPassword = $this->params()->fromPost('new_password');
                $newPasswordCheck = $this->params()->fromPost('new_password_repeat');
                if (empty($oldPassword) || empty($newPassword) || empty($newPasswordCheck)) {
                    $this->flashMessenger()->setNamespace('error')->addMessage('password_empty_error');
                } elseif ($newPassword != $newPasswordCheck) {
                    $this->flashMessenger()->setNamespace('error')->addMessage('password_check_error');
                } else {
                    try {
                        $catalog->changeUserPassword($patron, $oldPassword, $newPassword);
                        $this->flashMessenger()->setNamespace('info')->addMessage('password_change_successful');
                        return $this->redirect()->toRoute('myresearch-profile');
                    } catch (\VuFind\Exception\ILS $ex) {
                        $this->flashMessenger()->setNamespace('error')->addMessage('password_change_error');
                    }
                }
            }
        }
        $view = $this->createViewModel(
            array(
                'label' => 'change_' . $op,
                'hmac' => $this->getHMAC(),
                'profileChange' => true,
                'op' => $op,
            )
        );
        if ($op == 'nickname') {
            $view->nickname = $catalog->getUserNickname($patron);
        }
        if ($op == 'email') {
            $view->email = $user->email;//$patron['email'];
        }
        $view->setTemplate('myresearch/profilechange');

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    public function finesPaymentAction()
    {
        $status = $this->params()->fromQuery('status');
        if ($status == 'ok') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_fine_payment_successful');
        } elseif ($status == 'nok') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_fine_payment_failed');
        } elseif ($status == 'error') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_fine_payment_error');
        }
        return $this->redirect()->toRoute('myresearch-fines');
    }

    public function prolongationPaymentAction()
    {
        $status = $this->params()->fromQuery('status');
        if ($status == 'ok') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_prolongation_payment_successful');
        } elseif ($status == 'nok') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_prolongation_payment_failed');
        } elseif ($status == 'error') {
            $this->flashMessenger()->setNamespace('info')->addMessage('online_prolongation_payment_error');
        }
        return $this->redirect()->toRoute('myresearch-profile');
    }


    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

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
            $config['General']['async_checkedout']) {
            $isSynchronous = false;
        } else {
            $isSynchronous = true;
        }

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
                    if ($renewStatus && !isset($current['renew_link']) &&
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
            } else {
                $currentIdentityView['cat_username'] = $patron['cat_username'];
            }

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
     * Get a session namespace specific to the current class.
     *
     * @return SessionContainer
     */
    public function getSession()
    {
        static $session = false;
        if (!$session) {
            $session = new SessionContainer(get_class($this));
        }
        return $session;
    }

    /**
     * Retrieve the last view option used.
     *
     * @return string
     */
    public function getLastView()
    {
        $session = $this->getSession();
        return isset($session->lastView) ? $session->lastView : null;
    }

    /**
     * Adds list and table views to view
     *
     * @param $view
     *
     * @return mixed
     */
    protected function addViews($view)
    {
        $defaultView = 'list';
        $availViews = array('list', 'table');
        $selectedView;
        $lastView = $this->getLastView();

        // Check for a view parameter in the url.
        $viewGet = $this->getRequest()->getQuery()->get('view');
        if (!empty($viewGet)) {
            // make sure the url parameter is a valid view
            if (in_array($viewGet, array_keys($availViews))) {
                $selectedView = $viewGet;
                $this->rememberLastView($viewGet);
            } else {
                $selectedView = $defaultView;
            }
        } elseif (!empty($lastView)) {
            // if there is nothing in the URL, check the Session
            $selectedView = $lastView;
        } else {
            // otherwise load the default
            $selectedView = $defaultView;
        }

        $views = array();
        foreach ($availViews as $availView) {
            $uri = clone $this->getRequest()->getUri();
            $uri->setQuery(array('view' => $availView));
            $views[$availView] = array(
                'uri' => $uri,
                'selected' => $availView == $selectedView
            );
        }

        $childView = array('selected' => $selectedView, 'views' => $views);

        if ($view instanceof \Zend\View\Model\ViewModel) {
            $view->view = $childView;
        } elseif (is_array($view)) {
            $view['view'] = $childView;
        }

        return $view;
    }

    public function checkedOutHistoryAction()
    {
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('LibraryCards', 'Home');
        }

        return $this->createViewModel([
            'libraryIdentities' => $user->getLibraryCards()
        ]);
    }

    /**
     * Remember the last view option used.
     *
     * @param string $last Option to remember.
     *
     * @return void
     */
    public function rememberLastView($last)
    {
        $session = $this->getSession();
        if (!$session->getManager()->getStorage()->isImmutable()) {
            $session->lastView = $last;
        }
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new \Exception('Lists disabled');
        }

        $this->preSetAutocompleteParams();

        $config = $this->getConfig();

        // Are "offline favorites" enabled ?
        $offlineFavoritesEnabled = false;

        if ($config->Site['offlineFavoritesEnabled'] !== null) {
            $offlineFavoritesEnabled = (bool)$config->Site['offlineFavoritesEnabled'];
        }

        // Do we have request for a public list?
        $idEmpty = $this->params()->fromRoute('id') === null;

        // And is user not logged in ?
        $userNotLoggedIn = $this->getUser() === false;

        if ($offlineFavoritesEnabled && $idEmpty && $userNotLoggedIn) {
            // Well then, render the favorites for not logged in user & let JS handle it ..

            return $this->createViewModel([
                'loggedIn' => false
            ]);
        } else {
            // Nope, let's behave the old-style :)
            return parent::mylistAction();
        }
    }

    public function favoritesImportAction()
    {
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $favorites = $catalog->getMyFavorites($patron);
        foreach ($favorites as $favorite) {
            $folder = $favorite['folder'];
            $id = $favorite['id'];
            $note = $favorite['note'];
            $userListTable = $this->getTable('UserList');
            $list = $userListTable->getByUserAndTitle($user, $folder);
            if ($list == null) {
                $list = $userListTable->getNew($user);
                $list->title = $folder;
                $list->save($user);
            }
            $resourceTable = $this->getTable('Resource');
            $resource = $resourceTable->findResource($id);
            $userResourceTable = $this->getTable('UserResource');
            $userResourceTable->createOrUpdateLink($resource->id, $user->id, $list->id, $note);
        }
        $this->flashMessenger()->setNamespace('info')->addMessage('fav_import_successful');
        return $this->redirect()->toRoute('myresearch-favorites');
    }

    public function shortLoansAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = true;
        $view = $this->createViewModel();
        try {
            $view->cancelResults = $cancelStatus
                ? $this->shortLoanRequests()->cancelShortLoanRequests($catalog, $patron) : array();
        } catch (\Exception $ex) {
            $this->flashMessenger()->setNamespace('error')->addMessage('cancel_short_loan_request_error_text');
        }
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        $ilsBookings = $catalog->getMyShortLoanRequests($patron);

        $bookings = array();
        foreach ($ilsBookings as $current) {
            $current = $this->shortLoanRequests()->addCancelDetails($catalog, $current);
            $bookings[] = $this->getDriverForILSRecord($current);
        }

        $view = $this->createViewModel(
            array(
                'bookings' => $bookings,
                'cancelForm' => true
            )
        );
        $view->setTemplate('myresearch/shortloans');

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    public function userConnectAction()
    {
        // This eid serves only to warn user he wants to connect the same instituion account
        if (isset($_GET['eid'])) {
            $entityIdInitiatedWith = $_GET['eid'];
        } else {
            $entityIdInitiatedWith = null;
        }

        $patron = $this->catalogLogin();

        // We can't really determine if is user logged in if entityIdInitiatedWith is provided
        // We have to leave this on ShibbolethIdentityManager ..
        $haveToLogin = !is_array($patron) && !$this->isLoggedInWithDummyDriver($patron) && empty($entityIdInitiatedWith);

        // Stop now if the user does not have valid catalog credentials available:
        if ($haveToLogin) {
            $this->flashExceptions($this->flashMessenger());
            $this->clearFollowupUrl();
            return $patron;
        }

        // Perform local logout & redirect user to force him login to another account

        $authManager = $this->getAuthManager();

        if (!$authManager instanceof AuthManager) {
            throw new AuthException("ERROR: authManager is not CPK\\Manager");
        }

        if (empty($entityIdInitiatedWith)) {

            return $this->redirect()->toRoute('librarycards-home');
        } else {

            // Clear followUp ...
            if ($this->getFollowupUrl()) {
                $this->clearFollowupUrl();
            }

            try {
                $user = $authManager->consolidateIdentity();
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);

                // Stop now if the user does not have valid catalog credentials available:
                if (!$user = $this->getAuthManager()->isLoggedIn()) {
                    $this->flashExceptions($this->flashMessenger());
                    return $this->forceLogin();
                }

                return $this->redirect()->toRoute('librarycards-home');
            }

            if ($user->consolidationSucceeded) {
                $this->processSuccessMessage("Identities were successfully connected");
            }

            // Show user all his connected identities
            return $this->redirect()->toRoute('librarycards-home');
        }
    }

    /**
     * Deletes user account if it is confirmed
     *
     * @return mixed|\Zend\Http\Response|\Zend\Http\Response
     */
    public function userDeleteAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $confirm = $this->params()->fromPost('confirm', $this->params()
            ->fromQuery('confirm'));

        if ($confirm) {

            $user->deleteAccout();
            return $this->logoutAction();
        }

        $this->flashMessenger()->addErrorMessage($this->translate('delete-user-account-not-confirmed'));
        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Send list of fines to view
     *
     * @return mixed
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

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
            $config['General']['async_fines']) {
            $isSynchronous = false;
        } else {
            $isSynchronous = true;
        }

        $viewVars['isSynchronous'] = $isSynchronous;

        foreach ($identities as $identity) {

            $patron = $user->libCardToPatronArray($identity);

            // Begin building view object:
            $currentIdentityView = $this->createViewModel();

            $fines = [];

            if (!$isSynchronous) {
                $fines['cat_username'] = $patron['cat_username'];
            } else {
                // Get fine details:
                $result = $catalog->getMyFines($patron);

                foreach ($result as $row) {
                    // Attempt to look up and inject title:
                    try {
                        if (!isset($row['id']) || empty($row['id'])) {
                            throw new \Exception();
                        }
                        $source = isset($row['source']) ? $row['source'] : 'VuFind';
                        $row['driver'] = $this->getServiceLocator()
                            ->get('VuFind\RecordLoader')
                            ->load($row['id'], $source);
                        $row['title'] = $row['driver']->getShortTitle();
                    } catch (\Exception $e) {
                        if (!isset($row['title'])) {
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
        if (!empty($result)) {
            foreach ($result as $fine) {
                $totalFine += ($fine['amount']);
            }
        }
        if ($totalFine < 0) {
            $viewVars['paymentUrl'] = $catalog->getPaymentURL($patron,
                -1 * $totalFine);
        }
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    protected function getILLRequestFieldsForSerial()
    {
        return array(
            'ill_request_for_serial' => array(
                'title' => array('label' => 'ill_journal_title', 'type' => 'text', 'required' => true),
                'issn' => array('label' => 'ill_additional_authors', 'type' => 'text', 'required' => false),
                'year' => array('label' => 'ill_year', 'type' => 'text', 'required' => true),
                'volume' => array('label' => 'ill_volume', 'type' => 'text', 'required' => false),
                'issue' => array('label' => 'ill_issue', 'type' => 'text', 'required' => false),
                'source' => array('label' => 'ill_source', 'type' => 'text', 'required' => false),
            ),
            'ill_article_information' => array(
                'sub-author' => array('label' => 'ill_article_author', 'type' => 'text', 'required' => false),
                'sub-title' => array('label' => 'ill_article_title', 'type' => 'text', 'required' => false),
                'pages' => array('label' => 'ill_pages', 'type' => 'text', 'required' => false),
                'note' => array('label' => 'Note', 'type' => 'text', 'required' => false),
            ),
            'ill_administration_information' => array(
                'last-interest-date' => array('label' => 'ill_last_interest_date', 'type' => 'date', 'required' => true),
                'media' => array(
                    'label' => 'ill_request_type',
                    'type' => 'select',
                    'required' => false,
                    'options' => array(
                        'C-COPY' => 'ill_photocopy',
                        'L-COPY' => 'ill_loan',
                    ),
                ),
            ),
            'ill_author_rights_restriction' => array(
                'paragraph' => array('type' => 'paragraph', 'text' => 'ill_author_rights_restriction_text'),
            ),
            'ill_payment_options' => array(
                'payment' => array(
                    'label' => 'ill_type',
                    'type' => 'select',
                    'required' => false,
                    'options' => array(
                        '100-200' => 'ill_serial_request_from_abroad',
                        'kopie ČR' => 'ill_serial_request_from_Czech_Republic',
                    ),
                ),
                'confirmation' => array('label' => 'ill_confirmation', 'type' => 'checkbox'),
                'hmac' => array('type' => 'hidden', 'value' => $this->getHMAC()),
            ),
        );
    }

    protected function getHMAC()
    {
        $config = $this->getConfig();
        $hmacKey = $config->Security->HMACkey;
        return hash_hmac('md5', session_id(), $hmacKey);
    }

    protected function getIllRequestFieldsForMonography()
    {
        return array(
            'new_ill_request_for_monography' => array(
                'author' => array('label' => 'Author', 'type' => 'text', 'required' => true),
                'additional_authors' => array('label' => 'ill_additional_authors', 'type' => 'text', 'required' => false),
                'title' => array('label' => 'Title', 'type' => 'text', 'required' => true),
                'edition' => array('label' => 'ill_edition', 'type' => 'text', 'required' => false),
                'place-of-publication' => array('label' => 'ill_place_of_publication', 'type' => 'text', 'required' => false),
                'isbn' => array('label' => 'ISBN', 'type' => 'text', 'required' => false),
                'year-of-publication' => array('label' => 'ill_year', 'type' => 'text', 'required' => true),
                'series' => array('label' => 'Series', 'type' => 'text', 'required' => false),
                'source' => array('label' => 'ill_source', 'type' => 'text', 'required' => false),
                'publisher' => array('label' => 'ill_publisher', 'type' => 'text', 'required' => false),
            ),
            'ill_part_of_the_monography' => array(
                'sub-author' => array('label' => 'ill_sub_author', 'type' => 'text', 'required' => false),
                'sub-title' => array('label' => 'ill_sub_title', 'type' => 'text', 'required' => false),
                'pages' => array('label' => 'ill_pages', 'type' => 'text', 'required' => false),
                'note' => array('label' => 'Note', 'type' => 'text', 'required' => false),
            ),
            'ill_administration_information' => array(
                'last-interest-date' => array('label' => 'ill_last_interest_date', 'type' => 'date', 'required' => true),
                'media' => array(
                    'label' => 'ill_request_type',
                    'type' => 'select',
                    'required' => false,
                    'options' => array(
                        'L-PRINTED' => 'ill_loan',
                        'C-PRINTED' => 'ill_photocopy',
                    ),
                ),
            ),
            'ill_author_rights_restriction' => array(
                'paragraph' => array('type' => 'paragraph', 'text' => 'ill_author_rights_restriction_text'),
            ),
            'ill_payment_options' => array(
                'payment' => array(
                    'label' => 'ill_type',
                    'type' => 'select',
                    'required' => false,
                    'options' => array(
                        '50' => 'ill_request_from_Czech_Republic',
                        '300' => 'ill_request_from_Europe',
                        '600' => 'ill_request_from_Great_Britain_or_oversea',
                    ),
                ),
                'confirmation' => array('label' => 'ill_confirmation', 'type' => 'checkbox', 'required' => true),
                'hmac' => array('type' => 'hidden', value => $this->getHMAC()),
            ),
        );
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
        if ($user instanceof \Zend\View\Model\ViewModel) {
            return false;
        }
        return isset($user['home_library']) ? $user['home_library'] == "Dummy" : false;
    }

    protected function processBlocks($profile, $logos)
    {
        if (isset($profile['blocks'])) {
            if ($logos instanceof \Zend\Config\Config) {
                $logos = $logos->toArray();
            }

            foreach ($profile['blocks'] as $institution => $block) {
                if (isset($logos[$institution])) {
                    $logo = $logos[$institution];
                } else {
                    $logo = $institution;
                }

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
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

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

        $selectedCitationStyle = (!empty($preferedCitationStyle))
            ? $preferedCitationStyle
            : $defaultCitationStyle;

        $viewVars['selectedCitationStyle'] = $selectedCitationStyle;
        $viewVars['availableCitationStyles'] = $availableCitationStyles;
        $viewVars['institutions'] = $this->getInstitutions($user, true);
        $viewVars['preferredInstitutions'] = array_filter($userSettingsTable->getSavedInstitutions($user));

        /* Records per page fieldset */
        $searchesConfig = $this->getConfig('searches');
        $recordsPerPageOptions = explode(",", $searchesConfig->General->limit_options);
        $recordsPerPageDefaultValue = $searchesConfig->General->default_limit;
        $preferredRecordsPerPageValue = $userSettingsTable->getRecordsPerPage($user);

        $selectedRecordsPerPageOption = (!empty($preferredRecordsPerPageValue))
            ? $preferredRecordsPerPageValue
            : $recordsPerPageDefaultValue;

        $viewVars['recordsPerPageOptions'] = $recordsPerPageOptions;
        $viewVars['selectedRecordsPerPageOption'] = $selectedRecordsPerPageOption;

        /* Sorting fieldset */
        $sortingOptions = $searchesConfig->Sorting->toArray();
        $defaultSorting = $searchesConfig->General->default_sort;
        $preferredSorting = $userSettingsTable->getSorting($user);

        $selectedSorting = (!empty($preferredSorting))
            ? $preferredSorting
            : $defaultSorting;

        $viewVars['sortingOptions'] = $sortingOptions;
        $viewVars['selectedSorting'] = $selectedSorting;

        //
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());

        $searchesConfig = $this->getConfig('searches');
        // If user have preferred limit and sort settings
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $userSettingsTable = $this->getTable("usersettings");

            $userSettingsTable = $this->getTable("usersettings");
            $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
            $preferredSorting = $userSettingsTable->getSorting($user);

            if ($preferredRecordsPerPage) {
                $this->layout()->limit = $preferredRecordsPerPage;
            } else {
                $this->layout()->limit = $searchesConfig->General->default_limit;
            }

            if ($preferredSorting) {
                $this->layout()->sort = $preferredSorting;
            } else {
                $this->layout()->sort = $searchesConfig->General->default_sort;
            }
        } else {
            $this->layout()->limit = $searchesConfig->General->default_limit;
            $this->layout()->sort = $searchesConfig->General->default_sort;
        }

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit;
        $_SESSION['VuFind\Search\Solr\Options']['lastSort'] = $this->layout()->sort;


        return $view;
    }

    /**
     * Functions presets limit and sort type for autocomplete
     *
     * @return  void
     */
    protected function preSetAutocompleteParams()
    {
        $searchesConfig = $this->getConfig('searches');
        // If user have preferred limit and sort settings
        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $userSettingsTable = $this->getTable("usersettings");

            $userSettingsTable = $this->getTable("usersettings");
            $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
            $preferredSorting = $userSettingsTable->getSorting($user);

            if ($preferredRecordsPerPage) {
                $this->layout()->limit = $preferredRecordsPerPage;
            } else {
                $this->layout()->limit = $searchesConfig->General->default_limit;
            }

            if ($preferredSorting) {
                $this->layout()->sort = $preferredSorting;
            } else {
                $this->layout()->sort = $searchesConfig->General->default_sort;
            }
        } else {
            $this->layout()->limit = $searchesConfig->General->default_limit;
            $this->layout()->sort = $searchesConfig->General->default_sort;
        }

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit;
        $_SESSION['VuFind\Search\Solr\Options']['lastSort'] = $this->layout()->sort;
    }

    /**
     * Change title request
     *
     * @return mixed
     */
    public function changetitleAction()
    {
        // Fail if saved searches are disabled.
        $check = $this->getServiceLocator()->get('VuFind\AccountCapabilities');
        if ($check->getSavedSearchSetting() === 'disabled') {
            throw new \Exception('Saved searches disabled.');
        }

        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Check for the save / delete parameters and process them appropriately:
        $search = $this->getTable('Search');
        if (($id = $this->params()->fromQuery('save', false)) !== false) {
            $user_id = $user->id;
            $row = $search->getRowById($id);
            $rowData = $row->toArray();
            $rowData['saved'] = true;
            if ($user_id !== false) {
                $rowData['user_id'] = $user_id;
            }
            $rowData['title'] = $this->params()->fromQuery('title', '');
            $row->populate($rowData, true);
            $row->save();
            $this->flashMessenger()->addMessage('search_save_success', 'success');
        } elseif (($id = $this->params()->fromQuery('delete', false)) !== false) {
            $search->setSavedFlag($id, false);
            $this->flashMessenger()->addMessage('search_unsave_success', 'success');
        } else {
            throw new \Exception('Missing save and delete parameters.');
        }

        // Forward to the appropriate place:
        if ($this->params()->fromQuery('mode') == 'history') {
            return $this->redirect()->toRoute('search-history');
        } else {
            // Forward to the Search/Results action with the "saved" parameter set;
            // this will in turn redirect the user to the appropriate results screen.
            $this->getRequest()->getQuery()->set('saved', $id);
            return $this->forwardTo('Search', 'Results');
        }
    }

    /*
     * Get institutions from Solr
     *
     * @param \CPK\Db\Row\User
     * @param bool $withoutUserSavedInstitution
     *
     * @return array
     */
    public function getInstitutions(\CPK\Db\Row\User $user, $withoutUserSavedInstitution = false)
    {
        $results = $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager')->get('Solr');
        $params = $results->getParams();
        $params->addFacet('local_region_institution_facet_str_mv', 'Institutions');
        $params->setLimit(0);
        $params->setFacetLimit(10000);
        $results->getResults();
        $facets = $results->getFacetList()['local_region_institution_facet_str_mv']['list'];
        foreach ($facets as $key => $facet) {
            if (in_array(substr($facet['value'], 0, 1), ['0', '1'])) {
                unset($facets[$key]);
            }
        }
        if ($withoutUserSavedInstitution) {
            $savedInstitutions = $this->getTable("usersettings")->getSavedInstitutions($user);

            if (!empty($savedInstitutions)) {
                foreach ($facets as $key => $facet) {
                    if (in_array($facet['value'], $savedInstitutions)) {
                        unset($facets[$key]);
                    }
                }
            }
        }
        return $facets;
    }

    /**
     * Updates user's saved institution
     *
     * @return mixed|\Zend\Http\Response|\Zend\Http\Response
     */
    public function userUpdateSavedInstitutionAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }
        $institution = $this->params()->fromPost('institution', $this->params()->fromQuery('institution'));
        if ($institution) {
            if ($this->params()->fromQuery('action') == 'removeSavedInstitution') {
                $this->getTable("usersettings")->removeSavedInstitution($user, $institution);
            } else {
                $this->getTable("usersettings")->saveInstitution($user, $institution);
            }
        }
        return $this->redirect()->toRoute('myresearch-settings');
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
     * Ziskej tickets page
     *
     * @return mixed|\Zend\View\Model\ViewModel
     * @throws \Http\Client\Exception
     */
    public function ziskejAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $this->preSetAutocompleteParams();

        // Forwarding for Dummy connector to Home page ..
        if ($this->isLoggedInWithDummyDriver($user)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        $config = $catalog->getDriverConfig();

        if (isset($config['General']['async_holds']) &&
            $config['General']['async_holds']) {
            $isSynchronous = false;
        } else {
            $isSynchronous = true;
        }

        $viewVars = [];

        $userTickets = [];

        $viewVars['isSynchronous'] = $isSynchronous;

        $request = $this->getRequest();
        $ziskejCurrentMode = $request->getCookie()->ziskej ?? 'disabled';
        if ($ziskejCurrentMode != 'disabled') {

            /** @var \CPK\ILS\Driver\MultiBackend $multiBackend */
            $multiBackend = $this->getILS()->getDriver();

            try {

                /** @var \Mzk\ZiskejApi\Api $ziskejApi */
                $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

                /** @var string[] $ziskejLibsCodes */
                $ziskejLibsCodes = [];

                $ziskejLibs = $ziskejApi->getLibraries();
                foreach ($ziskejLibs->getAll() as $ziskejLib) {
                    $id = $multiBackend->siglaToSource($ziskejLib->getSigla());
                    if (!empty($id)) {
                        $ziskejLibsCodes[] = $id;
                    }
                }
                if ($user) {
                    $userSources = $user->getNonDummyInstitutions();
                    $userLibCards = $user->getAllUserLibraryCards();

                    $connectedZiskejLibs = array_filter($userSources, function ($userLib) use ($ziskejLibsCodes) {
                        return in_array($userLib, $ziskejLibsCodes);
                    });
                    $sourceEppn = [];
                    foreach ($userLibCards as $userLibCard) {
                        if (in_array($userLibCard->home_library, $connectedZiskejLibs)) {
                            $sourceEppn[$userLibCard->home_library] = $userLibCard->eppn;
                        }
                    }
                    $viewVars['connectedZiskejLibs'] = $connectedZiskejLibs;

                    foreach ($sourceEppn as $source => $eppn) {
                        $ziskejReader = $ziskejApi->getReader($eppn);
                        if ($ziskejReader && $ziskejReader->isActive()) {
                            $userTickets[$source] = $ziskejApi->getTickets($eppn);
                        }
                    }
                }
            } catch (\Exception $ex) {
                if ($ziskejCurrentMode != 'disabled') {
                    $this->flashMessenger()->addMessage('ziskej_warning_api_disconnected',
                        'warning');  //@todo presunout do view pod nadpis ziskej
                }
            }
        }

        $viewVars['userTickets'] = $userTickets;
        $viewVars['ziskejCurrentMode'] = $ziskejCurrentMode;
        $view = $this->createViewModel($viewVars);
        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    /**
     * Ziskej ticket detail
     * url: /MyResearch/ZiskejTicket/<eppn>/<ticket_id>
     *
     * @return mixed|\Zend\View\Model\ViewModel
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     * @throws \CPK\Controller\Exception\TicketNotFoundException
     */
    public function ziskejTicketAction()
    {
        $eppn = $this->params()->fromRoute('eppn');
        if (!$eppn) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $ticketId = $this->params()->fromRoute('ticket_id');
        if (!$ticketId) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        /** @var \VuFind\Db\Row\UserCard[] $userCards */
        $userCards = $user->getAllUserLibraryCards();

        $userCard = null;
        /** @var \VuFind\Db\Row\UserCard $userCard */
        foreach ($userCards as $card) {
            if ($eppn == $card->eppn) {
                $userCard = $card;
            }
        }

        if (!$userCard) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

        $ticket = $ziskejApi->getTicket($eppn, $ticketId);

        $messages = $ziskejApi->getMessages($eppn, $ticketId);

        $recordLoader = $this->getRecordLoader();
        /** @var \CPK\RecordDriver\SolrMarcLocal $record */
        $record = $recordLoader->load($ticket->getDocumentId(), 'VuFind');

        $view = $this->createViewModel();
        $view->setVariable('user', $user);
        $view->setVariable('userCard', $userCard);
        $view->setVariable('ticket', $ticket);
        $view->setVariable('messages', $messages->getAll());
        $view->setVariable('driver', $record);
        //$this->flashExceptions($this->flashMessenger());  //@todo???
        return $view;

    }

    /**
     * Cancel Ziskej ticket
     * url: /MyResearch/ZiskejTicketCancel/<eppn>/<ticket_id>
     *
     * @return mixed|\Zend\Http\Response
     * @throws \CPK\Controller\Exception\TicketNotFoundException
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function ziskejTicketCancelAction()
    {
        $eppn = $this->params()->fromRoute('eppn');
        if (!$eppn) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $ticketId = $this->params()->fromRoute('ticket_id');
        if (!$ticketId) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        /** @var \VuFind\Db\Row\UserCard[] $userCards */
        $userCards = $user->getAllUserLibraryCards();

        $userCard = null;
        /** @var \VuFind\Db\Row\UserCard $userCard */
        foreach ($userCards as $card) {
            if ($eppn == $card->eppn) {
                $userCard = $card;
            }
        }

        if (!$userCard) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

        $deleted = $ziskejApi->cancelTicket($eppn, $ticketId);

        if ($deleted) {
            $this->flashMessenger()->addMessage('message_ziskej_order_cancel_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('message_ziskej_order_cancel_fail', 'error');
        }

        return $this->redirect()->toRoute('MyResearch-ziskejTicket', [
            'eppn' => $eppn,
            'ticket_id' => $ticketId,
        ]);

    }

    /**
     * Send form: Create new message
     *
     * @return mixed|\Zend\Http\Response
     * @throws \CPK\Controller\Exception\TicketNotFoundException
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function ziskejTicketCreateMessageFormAction()
    {
        //@todo if method != POST

        $eppn = $this->params()->fromRoute('eppn');
        if (!$eppn) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $ticketId = $this->params()->fromRoute('ticket_id');
        if (!$ticketId) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        /** @var \VuFind\Db\Row\UserCard[] $userCards */
        $userCards = $user->getAllUserLibraryCards();

        $userCard = null;
        /** @var \VuFind\Db\Row\UserCard $userCard */
        foreach ($userCards as $card) {
            if ($eppn == $card->eppn) {
                $userCard = $card;
            }
        }

        if (!$userCard) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $params = $this->params()->fromPost();
        if (empty($params['ticketMessage'])) {
            $this->flashMessenger()->addMessage('message_ziskej_message_required_ticketMessage', 'error');

            return $this->redirect()->toRoute('MyResearch-ziskejTicket', [
                'eppn' => $eppn,
                'ticket_id' => $ticketId,
            ]);
        }

        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

        $message = new Message((string)$params['ticketMessage']);

        $creaded = $ziskejApi->createMessage($eppn, $ticketId, $message);
        if ($creaded) {
            $this->flashMessenger()->addMessage('message_ziskej_message_send_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('message_ziskej_message_send_fail', 'error');
        }

        return $this->redirect()->toRoute('MyResearch-ziskejTicket', [
            'eppn' => $eppn,
            'ticket_id' => $ticketId,
        ]);
    }

}
