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

use VuFind\Controller\LibraryCardsController as LibraryCardsControllerBase, CPK\Db\Row\User as UserRow;

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

        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Check for "delete card" request; parameter may be in GET or POST depending
        // on calling context.
        $deleteId = $this->params()->fromPost(
            'delete', $this->params()->fromQuery('delete')
        );
        if ($deleteId) {
            // If the user already confirmed the operation, perform the delete now;
            // otherwise prompt for confirmation:
            $confirm = $this->params()->fromPost(
                'confirm', $this->params()->fromQuery('confirm')
            );
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
                'libraryCards' => $user->getAllLibraryCards(),
                'multipleTargets' => $catalog->checkCapability('getLoginDrivers')
            ]
        );
    }

    /**
     * Redirects to librarycards-home
     *
     * @return mixed
     */
    public function deleteCardAction()
    {
        return $this->redirect()->toRoute('librarycards-home');
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

        $id = $this->params()->fromRoute('id');
        if (!intval($id))
            return $this->redirect()->toRoute('librarycards-home');

        $card = $user->getLibraryCard($id);

        // Send the card to the view:
        return $this->createViewModel([
            'card' => $card
        ]);
    }

    /**
     * Process the "edit library card" submission.
     *
     * @param UserRow $user
     *            Logged in user
     *
     * @return object|bool Response object if redirect is
     *         needed, false if form needs to be redisplayed.
     */
    protected function processEditLibraryCard(UserRow $user)
    {
        $cardName = $this->params()->fromPost('card_name', '');

        if (! trim($cardName)) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage('Card name cannot be empty');
            return false;
        }

        $id = $this->params()->fromRoute('id', $this->params()
            ->fromQuery('id'));
        try {
            $user->editLibraryCardName($id, $cardName);
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage($e->getMessage());
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }
}
