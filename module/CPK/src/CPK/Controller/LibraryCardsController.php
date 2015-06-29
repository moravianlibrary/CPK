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

use VuFind\Controller\LibraryCardsController as LibraryCardsControllerBase;
use Zend\Mvc\Controller\Plugin\Redirect;
use Zend\XmlRpc\Value\Integer;

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
     * @param \VuFind\Db\Row\User $user
     *            Logged in user
     *
     * @return object|bool Response object if redirect is
     *         needed, false if form needs to be redisplayed.
     */
    protected function processEditLibraryCard($user)
    {
        $cardName = $this->params()->fromPost('card_name', '');

        if (! trim($cardName)) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage('cardname_empty_error');
            return false;
        }

        $id = $this->params()->fromRoute('id', $this->params()
            ->fromQuery('id'));
        try {
            $user->saveLibraryCard($id, $cardName, $user['cat_username'], null);
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()
                ->setNamespace('error')
                ->addMessage($e->getMessage());
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }
}
