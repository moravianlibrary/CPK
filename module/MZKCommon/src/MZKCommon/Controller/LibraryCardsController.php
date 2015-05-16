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

use VuFind\Controller\LibraryCardsController as LibraryCardsControllerBase, MZKPortal\Auth\ShibbolethWithWAYF as ShibbolethWithWAYF;
use Zend\Mvc\Controller\Plugin\Redirect;

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

    /**
     * Send user's library cards to the view
     *
     * @return mixed
     */
    public function homeAction()
    {
        return parent::homeAction();
    }

    /**
     * Creates a confirmation box to delete or not delete the current list
     *
     * @return mixed
     */
    public function deleteCardAction()
    {
        return parent::deleteCardAction();
    }

    /**
     * Activates a library card
     *
     * @return \Zend\Http\Response
     */
    public function selectCardAction()
    {
        return parent::selectCardAction();
    }

    /**
     * Send user's library card to the edit view
     *
     * @return mixed
     */
    public function editCardAction()
    {
        // User must be logged in to edit library cards
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Process form submission
        if ($this->formWasSubmitted('submit')) {
            if ($redirect = $this->processEditLibraryCard($user)) {
                return $redirect;
            }
        }

        $id = $this->params()->fromRoute('id', $this->params()
            ->fromQuery('id'));
        $card = $user->getLibraryCard($id == 'NEW' ? null: $id);

        if ($id == 'NEW') {
            $isAuthorized = false;

            // Check if the user is already redirected from Shibboleth IdP
            foreach ($_SERVER as $attribute => $value) {
                if ($attribute == "REDIRECT_Shib-Identity-Provider") {
                    $isAuthorized = true;
                    break;
                }
            }

            if (! $isAuthorized) {
                // Redirect to Shibboleth IdP
                $authManager = $this->getAuthManager();

                // Redirect back here
                $sessionInitiators = $authManager->getSessionInitiators('/LibraryCards/editCard/NEW');

                // TODO: Let user decide which authentication he wants
                $userWants = 'Shib-NCIP-DS';
                $redirectionLink = $sessionInitiators[$userWants];

                // Check it exists
                if(empty($redirectionLink))
                    $redirectionLink = $sessionInitiators[array_keys($sessionInitiators)[0]]; // Choose first if not

                // Redirect to shibboleth
                header('Location: ' . $redirectionLink, true, 303);
                die();
            } else {
                // Fetch target & username
                foreach ($_SERVER as $attribute => $value) {
                    if ($attribute == "REDIRECT_userId") {
                        $username = $value;
                    } else if ($attribute == "REDIRECT_homeLib") {
                        $target = $value;
                    }
                    if ($username != null && $target != null)
                        break;
                }

                if ($username == null || $target == null)
                    throw new LibraryCard("Shibboleth did not return userId or homeLib");

                // TODO: Check here if recieved userId already exists with provided homeLib (we can't know what user choosed at the Discovery Service)
                // Throw another error if yes
            }
        } else {
            // Being here means user wants to edit the card name
                $target = null;
                $username = $card->cat_username;
                $targets = null;
                $defaultTarget = null;
                // Connect to the ILS and check if multiple target support is available:
                $catalog = $this->getILS();
                if ($catalog->checkCapability('getLoginDrivers')) {
                    $targets = $catalog->getLoginDrivers();
                    $defaultTarget = $catalog->getDefaultLoginDriver();
                    if (strstr($username, '.')) {
                        list ($target, $username) = explode('.', $username, 2);
                    }
                }
                $cardName = $this->params()->fromPost('card_name', $card->card_name);
                $username = $this->params()->fromPost('username', $username);
                $target = $this->params()->fromPost('target', $target);
            }
        // Send the card to the view:
        return $this->createViewModel([
            'card' => $card,
            'cardName' => $cardName,
            'target' => $target,
            'username' => $username
        ]);
    }

    /**
     * Process the "edit library card" submission.
     *
     * @param \VuFind\Db\Row\User $user
     *            Logged in user
     *
     * @return object|bool Response object if redirect is
     *         needed, false if form needs to be redisplayed.
     */
    protected function processEditLibraryCard($user)
    {
        // FIXME: Need refactoring .. most of the code is not neccessary
        $cardName = $this->params()->fromPost('card_name', '');
        $target = $this->params()->fromPost('target', '');
        $username = $this->params()->fromPost('username', '');

        if (! $username ) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage('authentication_error_blank');
            return false;
        }

        if ($target) {
            $username = "$target.$username";
        }

        // Connect to the ILS and check that the credentials are correct:
        $catalog = $this->getILS();
        $patron = $catalog->patronLogin($username, null);
        if (! $patron) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage('authentication_error_invalid');
            return false;
        }

        $id = $this->params()->fromRoute('id', $this->params()
            ->fromQuery('id'));
        try {
            $user->saveLibraryCard($id == 'NEW' ? null : $id, $cardName, $username, $password);
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage($e->getMessage());
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }
}
