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
namespace MZKCommon\Controller;


use VuFind\Controller\MyResearchController as MyResearchControllerBase,
VuFind\Exception\Auth as AuthException,
VuFind\Exception\ListPermission as ListPermissionException,
VuFind\Exception\RecordMissing as RecordMissingException,
Zend\Stdlib\Parameters,
Zend\Session\Container as SessionContainer;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends MyResearchControllerBase
{
    use ExceptionsTrait;

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

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        $view = parent::holdsAction();
        $view = $this->addViews($view);

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    /**
     * Send list of checked out books to view
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        $view = parent::checkedoutAction();
        $showOverdueMessage = false;
        foreach ($view->transactions as $resource) {
            $ilsDetails = $resource->getExtraDetail('ils_details');
            if (isset($ilsDetails['dueStatus']) && $ilsDetails['dueStatus'] == "overdue") {
                $showOverdueMessage = true;
                break;
            }
        }
        if ($showOverdueMessage) {
            $this->flashMessenger()->setNamespace('error')->addMessage('overdue_error_message');
        }
        $view->history = false;
        $view = $this->addViews($view);

        $this->flashExceptions($this->flashMessenger());
        return $view;
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

    public function illRequestsAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($type = $this->params()->fromQuery('new')) {
            $fields = array();
            if ($type == 'monography') {
                $fields = $this->getILLRequestFieldsForMonography();
            } else if ($type == 'serial') {
                $fields = $this->getILLRequestFieldsForSerial();
            }
            $missingValues = false;
            $fromPost = $this->params()->fromPost('placeIll');
            $details = array();
            if ($fromPost) {
                $allFields = array();
                foreach ($fields as $group => &$subfields) {
                    foreach ($subfields as $name => &$attributes) {
                        $attributes['missing'] = false;
                        $attributes['value'] = '';
                        $value = $this->params()->fromPost($name);
                        if ($value && trim($value) != '') {
                            $attributes['value'] = $value;
                            $details[$name] = $value;
                            if ($attributes['type'] == 'date') {
                                $converter = $this->getServiceLocator()->get('VuFind\DateConverter');
                                try {
                                    $converter->convertFromDisplayDate('Ymd', $value);
                                } catch (\VuFind\Exception\Date $de) {
                                    $attributes['missing'] = true;
                                    $this->flashMessenger()->setNamespace('error')->addMessage('invalid_date_format');
                                }
                            }
                        } else if ($attributes['required']) {
                            $attributes['missing'] = true;
                            $missingValues = true;
                        }
                    }
                }
            }
            if ($missingValues) {
                $this->flashMessenger()->setNamespace('error')->addMessage('ill_required_fields_missing_error');
            } else if ($fromPost) {
                if ($details['hmac'] != $this->getHMAC()) {
                    $this->flashMessenger()->setNamespace('info')->addMessage('ill_request_failed_due_to_hmac');
                } else {
                    $details['new'] = $type;
                    $result = $this->getILS()->placeILLRequest($patron, $details);
                    if ($result['success']) {
                        $this->flashMessenger()->setNamespace('info')->addMessage('ill_request_successful');
                        return $this->redirect()->toRoute('myresearch-illrequests');
                    } else {
                        $this->flashMessenger()->setNamespace('info')->addMessage('ill_request_failed');
                    }
                }
            }
            $view = $this->createViewModel(array('fields' => $fields));
            $view->setTemplate('myresearch/illrequest-new');

            $this->flashExceptions($this->flashMessenger());
            return $view;
        } else {
            return parent::illRequestsAction();
        }
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
                'media' => array('label' => 'ill_request_type', 'type' => 'select',  'required' => false,
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
                'payment' => array('label' => 'ill_type', 'type' => 'select',  'required' => false,
                    'options' => array(
                        '50'   => 'ill_request_from_Czech_Republic',
                        '300'  => 'ill_request_from_Europe',
                        '600'  => 'ill_request_from_Great_Britain_or_oversea',
                    ),
                ),
                'confirmation' => array('label' => 'ill_confirmation', 'type' => 'checkbox', 'required' => true),
                'hmac' => array('type' => 'hidden', value => $this->getHMAC()),
            ),
        );
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
                'media' => array('label' => 'ill_request_type', 'type' => 'select',  'required' => false,
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
                'payment' => array('label' => 'ill_type', 'type' => 'select',  'required' => false,
                    'options' => array(
                        '100-200'   => 'ill_serial_request_from_abroad',
                        'kopie ČR'  => 'ill_serial_request_from_Czech_Republic',
                    ),
                ),
                'confirmation' => array('label' => 'ill_confirmation', 'type' => 'checkbox'),
                'hmac' => array('type' => 'hidden', 'value' => $this->getHMAC()),
            ),
        );
    }

    public function checkedOutHistoryAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $currentLimit = $this->params()->fromQuery('limit');
        if (!isset($currentLimit)) {
            $currentLimit = 50;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Get history:
        $result = $catalog->getMyHistory($patron, $currentLimit);

        $transactions = array();
        foreach ($result as $current) {
            // Build record driver:
            $transactions[] = $this->getDriverForILSRecord($current);
        }

        $view = $this->createViewModel(
                array(
                    'transactions' => $transactions
                )
        );
        $view->history = true;
        $view->currentLimit = $currentLimit;
        $view->limitList = array(50, 100, 200);
        $this->addViews($view);

        $this->flashExceptions($this->flashMessenger());
        return $view;
    }

    public function profileAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $view = parent::profileAction();
        if ($view) {
            $catalog = $this->getILS();
            $view->profileChange = $catalog->checkCapability('changeUserRequest');
        }

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
            } else if ($op == 'password') {
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
                'label'         => 'change_' . $op,
                'hmac'          => $this->getHMAC(),
                'profileChange' => true,
                'op'            => $op,
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

    protected function getHMAC()
    {
        $config = $this->getConfig();
        $hmacKey = $config->Security->HMACkey;
        return hash_hmac('md5', session_id(), $hmacKey);
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
        } else if (!empty($lastView)) {
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
}
