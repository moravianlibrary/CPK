<?php
/**
 * Row Definition for user
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Row;

use VuFind\Db\Row\User as BaseUser;

/**
 * Row Definition for user
 *
 * @category VuFind2
 * @package Db_Row
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class User extends BaseUser
{

    /**
     * Save library card with the given information
     *
     * @param int $id
     *            Card ID
     * @param string $cardName
     *            Card name
     * @param string $cat_username
     *            Username
     * @param string $cat_password
     *            Password
     * @param string $home_library
     *            Home Library
     *
     * @return int Card ID
     * @throws \VuFind\Exception\LibraryCard
     */
    public function saveLibraryCard($id, $cardName, $cat_username = '', $cat_password = '', $home_library = '', $eppn = '')
    {
        if (! $this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $userCard = $this->getDbTable('UserCard');

        if (! empty($cat_username)) {
            // Check that the username is not already in use in another card
            $row = $userCard->select([
                'user_id' => $this->id,
                'cat_username' => $cat_username
            ])->current();

            if (! empty($row) && ($id === null || $row->id != $id)) {
                throw new \VuFind\Exception\LibraryCard('Catalog username is already in use in another library card');
            }
        }

        $row = null;
        if ($id !== null) {
            $row = $userCard->select([
                'user_id' => $this->id,
                'id' => $id
            ])->current();
        }

        if (empty($row)) {

            if (empty($cat_username))
                throw new \VuFind\Exception\LibraryCard('Cannot create library card without cat_username');

            if (empty($home_library))
                throw new \VuFind\Exception\LibraryCard('Cannot create library card without home_library');

            if (empty($eppn))
                throw new \VuFind\Exception\LibraryCard('Cannot create library card without eppn');

            $row = $userCard->createRow();
            $row->user_id = $this->id;
            $row->created = date('Y-m-d H:i:s');
        }

        // TODO: if card_name is empty, then parse defined one from configuration based on eppn
        $row->card_name = $cardName;

        // Not empty checks serves to don't update the field unless desired
        if (! empty($cat_username)) {
            $row->cat_username = $cat_username;
        }

        if (! empty($home_library)) {
            $row->home_library = $home_library;
        }

        if (! empty($eppn)) {
            $row->eppn = $eppn;
        }

        if (! empty($cat_password)) {
            if ($this->passwordEncryptionEnabled()) {
                $row->cat_password = null;
                $row->cat_pass_enc = $this->encryptOrDecrypt($cat_password, true);
            } else {
                $row->cat_password = $cat_password;
                $row->cat_pass_enc = null;
            }
        }

        $row->save();

        $this->activateBestLibraryCard();

        return $row->id;
    }

    /**
     * Changes specified card's name to provided one.
     *
     * @param number $id
     * @param string $cardName
     */
    public function editLibraryCardName($id, $cardName)
    {
        $this->saveLibraryCard($id, $cardName);
    }

    /**
     * Get all library cards associated with the user.
     * By default there are ommited all Dummy cards.
     *
     * If you wish to retrieve also Dummy cards, pass true to $includingDummyCards.
     *
     * @param boolean $includingDummyCards
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCards($includingDummyCards = false)
    {
        if (! $this->libraryCardsEnabled()) {
            return new \Zend\Db\ResultSet\ResultSet();
        }
        $userCard = $this->getDbTable('UserCard');
        if ($includingDummyCards)
            return $userCard->select([
                'user_id' => $this->id
            ]);
        return $userCard->select([
            'user_id' => $this->id,
            'home_library != ?' => 'Dummy'
        ]);
    }

    /**
     * Gets all library cards including Dummy cards.
     *
     * It is an alias for getLibraryCards(true)
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getAllLibraryCards()
    {
        return $this->getLibraryCards(true);
    }

    /**
     * Delete library card
     *
     * @param int $id
     *            Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function deleteLibraryCard($id)
    {
        if (! $this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select([
            'id' => $id,
            'user_id' => $this->id
        ])->current();

        if (empty($row)) {
            throw new \Exception('Library card not found');
        }
        $row->delete();

        if ($row->cat_username == $this->cat_username) {
            // Activate another card (if any) or remove cat_username and cat_password
            $cards = $this->getLibraryCards(true); // We need all library cards here I suppose?
            if ($cards->count() > 0) {
                $this->activateLibraryCard($cards->current()->id);
            } else {
                $this->cat_username = null;
                $this->cat_password = null;
                $this->cat_pass_enc = null;
                $this->save();
            }
        }
    }

    /**
     * Activates best library card. The algorithm chooses first available card,
     * if it is the only user's card. If user has more than one cards, it checks
     * for any not Dummy card & activates that one if finds any.
     *
     * If from all the cards doesn't find any non-Dummy card, nothing will happen
     * keeping in mind there has already been activated first Dummy card.
     */
    public function activateBestLibraryCard() {
        $libCards = $this->getAllLibraryCards();

        // If this is the first library card or no credentials are currently set,
        // activate the card now
        if ($libCards->count() == 1) {
            $this->activateLibraryCard($row->id);
        } else {

            $realCards = $this->parseRealCards($libCards);

            // Activate any realCard if current UserRow's home_library is Dummy
            if ($realCards && $this->home_library === 'Dummy') {
                $firstRealLibCardId = $realCards[0]->id;
                $this->activateLibraryCard($firstRealLibCardId);
            }
        }
    }

    /**
     * Filters out the dummy cards from passed $libCards array
     * of libCards.
     *
     * If no realCard found, returns false.
     *
     * @param array $libCards
     * @return mixed $realCards | false
     */
    public function parseRealCards($libCards)
    {
        $realCards = [];

        try {
            foreach ($libCards as $libCard) {
                if ($libCard->home_library !== 'Dummy')
                    $realCards[] = $libCard;
            }
        } catch (\Exception $e) {
            return false;
        }

        return sizeof($realCards) > 0 ? $realCards : false;
    }
}