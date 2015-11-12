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
    use ExceptionsTrait;

    /**
     * Send user's library cards to the view
     *
     * @return mixed
     */
    public function homeAction()
    {
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
            $user->disconnectIdentity($cardID);

            // Success Message
            $this->flashMessenger()
                ->setNamespace('success')
                ->addMessage('Identity disconnected');
            // Redirect to MyResearch library cards
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
}
