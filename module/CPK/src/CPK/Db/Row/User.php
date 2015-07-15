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
     * @param string $username
     *            Username
     * @param string $password
     *            Password
     * @param string $homeLib
     *            Home Library
     *
     * @return int Card ID
     * @throws \VuFind\Exception\LibraryCard
     */
    public function saveLibraryCard($id, $cardName, $username, $password, $homeLib = '', $eppn = '')
    {
        if (! $this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $userCard = $this->getDbTable('UserCard');

        // Check that the username is not already in use in another card
        $row = $userCard->select([
            'user_id' => $this->id,
            'cat_username' => $username
        ])->current();
        if (! empty($row) && ($id === null || $row->id != $id)) {
            throw new \VuFind\Exception\LibraryCard('Username is already in use in another library card');
        }

        $row = null;
        if ($id !== null) {
            $row = $userCard->select([
                'user_id' => $this->id,
                'id' => $id
            ])->current();
        }
        if (empty($row)) {
            $row = $userCard->createRow();
            $row->user_id = $this->id;
            $row->created = date('Y-m-d H:i:s');
        }
        $row->card_name = $cardName;
        $row->cat_username = $username;
        if (! empty($homeLib)) {
            $row->home_library = $homeLib;
        }

        // eduPersonPrincipalName is the only reason of customizing VuFind\Db\Row\User ...
        if (! empty($eppn)) {
            $row->eppn = $eppn;
        }

        if ($this->passwordEncryptionEnabled()) {
            $row->cat_password = null;
            $row->cat_pass_enc = $this->encryptOrDecrypt($password, true);
        } else {
            $row->cat_password = $password;
            $row->cat_pass_enc = null;
        }

        $row->save();

        // If this is the first library card or no credentials are currently set,
        // activate the card now
        if ($this->getLibraryCards()->count() == 1 || empty($this->cat_username)) {
            $this->activateLibraryCard($row->id);
        }

        return $row->id;
    }
}