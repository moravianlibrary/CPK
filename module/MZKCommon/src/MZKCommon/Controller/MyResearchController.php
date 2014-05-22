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

    /**
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        return parent::loginAction();
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
        $view = $this->addViews($view);
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
        return $view;
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
        $view->view = array('selected' => $selectedView, 'views' => $views);

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
