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

use VuFind\Db\Row\User as BaseUser, VuFind\Exception\Auth as AuthException, VuFind\Db\Row\UserCard, CPK\Auth\ShibbolethIdentityManager;

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

    const COLUMN_MAJOR_GLUE = ';';
    
    /**
     * Holds all User's libCards.
     *
     * @var \Zend\Db\ResultSet\AbstractResultSet
     */
    protected $allLibCards;
    
    /**
     * Holds all User's nonDummy libCards.
     * 
     * @var \Zend\Db\ResultSet\AbstractResultSet
     */
    protected $nonDummyLibCards;

    /**
     * Activates best library card.
     *
     * There is one possibility of activating no card - it is only when there
     * are no other nonDummy cards & dummy card is already activated.
     *
     * @param array $libCards
     */
    public function activateBestLibraryCard(array $libCards = null)
    {
        if (empty($libCards))
            $libCards = $this->getAllUserLibraryCards();

        $firstLibCard = reset($libCards);

        // If this is the first library card or no credentials are currently set,
        // activate the card now
        if (count($libCards) === 1) {
            $this->activateLibraryCardRow($firstLibCard);
        } else {

            $realCards = $this->parseRealCards($libCards);

            if (! $realCards) {
                if ($this->home_library !== 'Dummy')
                    // If User have left no realCard & has not active Dummy account, activate any dummy
                    $this->activateLibraryCardRow($firstLibCard);
            } else {
                // Activate any realCard
                $this->activateLibraryCardRow($realCards[0]);
            }
        }
    }

    /**
     * Activate a library card using UserCard row object.
     *
     * This method is basically the same as activateLibraryCard($id),
     * except it doesn't execute not needed sql command to parse the card.
     *
     * @param UserCard $libCard
     *
     * @return void
     */
    public function activateLibraryCardRow(UserCard $libCard)
    {
        if (! empty($libCard)) {
            $this->cat_username = $libCard->cat_username;
            $this->cat_password = $libCard->cat_password;
            $this->cat_pass_enc = $libCard->cat_pass_enc;
            $this->home_library = $libCard->home_library;
            $this->save();
        }
    }

    /**
     * Creates library card for User with $cat_username & $prefix identified by $eppn.
     *
     * eduPersonPrincipalName is later used to identify loggedin user.
     *
     * Returns library card id on success. Otherwise throws an AuthException.
     *
     * @param string $cat_username
     * @param string $prefix
     * @param string $eppn
     * @param string $email
     * @param array $canConsolidateMoreTimes
     *
     * @return mixed int | boolean
     * @throws AuthException
     */
    public function createLibraryCard($cat_username, $prefix, $eppn, $email,
        $canConsolidateMoreTimes)
    {
        try {
            if (empty($eppn))
                throw new AuthException("Cannot create library card with empty eppn");

            if (empty($this->id))
                throw new AuthException(
                    "Cannot create library card with empty user row id");

            if (empty($email))
                $email = '';

            return $this->saveLibraryCard(null, $email, $cat_username, null, $prefix,
                $eppn, $canConsolidateMoreTimes);
        } catch (\VuFind\Exception\LibraryCard $e) {
            throw new AuthException($e->getMessage());
        }
    }

    /**
     * Delete library card.
     *
     * Note that if you supply UserCard row to this method, it will delete it
     * no matter if it is last.
     *
     * @param
     *            mixed (int $id | UserCard $userCard)
     *
     * @param boolean $doNotDeleteIfLast
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function deleteLibraryCard($id, $doNotDeleteIfLast = false,
        $activateAnother = true)
    {
        if (! $this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        if ($id instanceof UserCard)
            return $this->deleteLibraryCardRow($id, $activateAnother);

        $allLibCards = $this->getAllUserLibraryCards();

        $found = false;
        foreach ($allLibCards as $key => $libCard) {
            if ($libCard->id === intval($id)) {
                $found = $key;
                break;
            }
        }

        if ($found === false) {
            throw new \Exception('Library card not found');
        }

        if ($doNotDeleteIfLast && count($allLibCards) === 1) {
            throw new \VuFind\Exception\LibraryCard(
                'Cannot disconnect the last identity');
        }

        $allLibCards[$found]->delete();

        // To prevent major transitivity based on identities we need to delete
        // major access connected with current identity being disconnected
        $this->deleteMajorAccess($allLibCards[$found]->home_library);

        if ($activateAnother &&
             $allLibCards[$found]->cat_username == $this->cat_username) {
            // Activate another card (if any) or remove cat_username and cat_password

            // The method needs up-to-date $allLibCards .. thus unset the deleted one
            unset($allLibCards[$found]);

            $this->activateBestLibraryCard($allLibCards);
        }
    }

    /**
     * This method deletes UserCard row.
     * If it was active card, then is activated another
     * using activateBestLibraryCard method.
     *
     * @param UserCard $libCard
     * @return number $affectedRows
     */
    public function deleteLibraryCardRow(UserCard $libCard, $activateAnother = true)
    {
        $affectedRows = $libCard->delete();

        if ($activateAnother && $libCard->cat_username == $this->cat_username) {
            // Activate another card (if any) or remove cat_username and cat_password
            $this->activateBestLibraryCard();
        }

        return $affectedRows;
    }

    /**
     * Disconnect desired identity.
     *
     * It is an alias for deleteLibraryCard($id, true, true)
     *
     * @param int $id
     *            Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function disconnectIdentity($id)
    {
        return $this->deleteLibraryCard($id, true, true);
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
     * Gets all library cards including Dummy cards.
     *
     * @return array of UserCard
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getAllUserLibraryCards()
    {
        $libCards = $this->getLibraryCards(true);

        // We need an array instead of ResultSet ...
        $toReturn = [];
        foreach ($libCards as $libCard) {
            $toReturn[] = $libCard;
        }
        return $toReturn;
    }

    /**
     * Returns IdPLogos section from config.ini with institutions mapping to their logos.
     */
    public function getIdentityProvidersLogos()
    {
        return $this->config->IdPLogos;
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
        if (!$this->libraryCardsEnabled()) {
            return new \Zend\Db\ResultSet\ResultSet();
        }
        
        if ($includingDummyCards) {
            
            if ($this->allLibCards == null) {
                $this->allLibCards = $this->getDbTable( 'UserCard' )->select( 
                        [
                            'user_id' => $this->id
                        ] );
            }
            
            return $this->allLibCards;
        } elseif ($this->nonDummyLibCards == null) {
            
            $this->nonDummyLibCards = $this->getDbTable( 'UserCard' )->select( 
                    [
                        'user_id' => $this->id,'home_library != ?' => 'Dummy'
                    ] );
        }
        
        return $this->nonDummyLibCards;
    }

    /**
     * Get all User's non-dummy connected institutions.
     *
     * @return array
     */
    public function getNonDummyInstitutions()
    {
        $institutes = [];

        $libCards = $this->getLibraryCards(false);

        foreach ($libCards as $libCard) {
            if (isset($libCard['home_library']))
                $institutes[] = $libCard['home_library'];
        }

        return array_unique($institutes);
    }

    /**
     * Converts libraryCard into 'patron' array used by major part of VuFind.
     *
     * @param VuFind\Db\Row\UserCard $libCard
     * @return array
     */
    public function libCardToPatronArray(\VuFind\Db\Row\UserCard $libCard)
    {
        $patron['cat_username'] = $libCard->cat_username;
        $patron['mail'] = $libCard->card_name;
        $patron['eppn'] = $libCard->eppn;

        $patron['id'] = $patron['cat_username'];
        return $patron;
    }

    /**
     * Checks if specified UserCard row id owns current User.
     *
     * @param int $id
     * @return boolean $hasThisLibraryCard
     */
    public function hasThisLibraryCard($id)
    {
        $userCard = $this->getDbTable('UserCard');
        return $userCard->select(
            [
                'id' => $id,
                'user_id' => $this->id
            ])->count() !== 0;
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
     * @param string $eppn
     * @param array $canConsolidateMoreTimes
     *
     * @return int Card ID
     * @throws \VuFind\Exception\LibraryCard
     */
    public function saveLibraryCard($id, $cardName, $cat_username = '',
        $cat_password = '', $home_library = '', $eppn = '', $canConsolidateMoreTimes = [])
    {
        if (! $this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        // Only one SQL query should speed up the process ...
        $libCards = $this->getAllUserLibraryCards();

        $eppnScope = explode('@', $eppn)[1];

        // Check that the user has only one institution account unless his organization is in $canConsolidateMoreTimes
        if (! in_array($eppnScope, $canConsolidateMoreTimes)) {
            $hasAccountAlready = false;
            if ($home_library !== 'Dummy') {

                // Not being Dummy we know home_library is unique across institutions
                foreach ($libCards as $libCard) {
                    // Allow connecting two different accounts from one institution only if those have identical cat_username
                    if ($libCard->home_library === $home_library &&
                         $libCard->cat_username !== $cat_username) {
                        $hasAccountAlready = true;
                        break;
                    }
                }
            } else {

                // Dummy account can be created even from connected institute (if IdP doesn't provide userLibraryId)
                // We allow creation of more identities in this case (IdP must be defined in shibboleth.ini)
                $cat_username_unscoped = explode(
                    ShibbolethIdentityManager::SEPARATOR, $cat_username)[1];
                if ($cat_username_unscoped === 'Dummy') {

                    // We need to find out the same user's Dummy institution if any, thus compare eppnScope to user's eppns
                    foreach ($libCards as $libCard) {
                        if (explode('@', $libCard->eppn)[1] === $eppnScope) {
                            $hasAccountAlready = true;
                            break;
                        }
                    }
                }
            }

            if ($hasAccountAlready) {
                throw new \VuFind\Exception\LibraryCard(
                    'Cannot connect two accounts from the same institution');
            }
        }

        if ($id !== null) {
            $row = false;

            foreach ($libCards as $libCard) {
                if ($libCard->id === intval($id)) {
                    $row = $libCard;
                    break;
                }
            }

            if (! $row)
                throw new \VuFind\Exception\LibraryCard(
                    'Cannot modify non-existing UserCard row');
        } else {
            // Row Id not provided, thus we'll create a new libCard

            if (empty($cat_username))
                throw new \VuFind\Exception\LibraryCard(
                    'Cannot create library card without cat_username');

            if (empty($home_library))
                throw new \VuFind\Exception\LibraryCard(
                    'Cannot create library card without home_library');

            if (empty($eppn))
                throw new \VuFind\Exception\LibraryCard(
                    'Cannot create library card without eppn');

            $row = $this->getDbTable('UserCard')->createRow();
            $row->user_id = $this->id;
            $row->created = date('Y-m-d H:i:s');
        }

        $row->card_name = $cardName;

        // Not empty checks serves to don't update the field unless desired
        if (! empty($cat_username)) {
            $row->cat_username = $cat_username;
        }

        if (! empty($home_library)) {
            $row->home_library = $home_library;
        }

        if (! empty($eppn)) {
            if (substr($eppn, 0, 4) === 'DEL_')
                $row->eppn = substr($eppn, 4);
            else
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

        // id being null means we have created new libCard, thus we need to update $libCards
        if ($id === null) {
            $libCards[] = $row;
        }

        $this->activateBestLibraryCard($libCards);

        return $row->id;
    }

    /**
     * Upgrades library card specified by eppn to non Dummy account with
     * new_cat_username & new_home_library.
     *
     * This method also calles the activateBestLibraryCard method.
     *
     * @param string $eppn
     * @param string $new_cat_username
     * @param string $new_home_library
     *
     * @return void
     * @throws AuthException
     */
    public function upgradeLibraryCardFromDummy($eppn, $new_cat_username,
        $new_home_library)
    {
        if (empty($new_cat_username))
            throw new AuthException(
                'Cannot upgrade library card from Dummy with empty new_cat_username');

        if (empty($new_home_library))
            throw new AuthException(
                'Cannot upgrade library card from Dummy with empty new_home_library');

        $libCardWithDesiredEppn = false;

        $libCards = $this->getAllUserLibraryCards();
        foreach ($libCards as $libCard) {
            if ($libCard->eppn === $eppn) {
                $libCardWithDesiredEppn = $libCard;
                break;
            }
        }

        $realCards = $this->parseRealCards($libCards);
        foreach ($realCards as $realCard) {
            // Allow connecting two different accounts from one institution only if those have identical cat_username
            if ($realCard->home_library === $new_home_library &&
                 $realCard->cat_username !== $new_cat_username) {
                throw new AuthException(
                    'Cannot upgrade library card from Dummy while you have active non-dummy card from the same institution');
            }
        }

        if ($libCardWithDesiredEppn) {
            $libCardWithDesiredEppn->cat_username = $new_cat_username;
            $libCardWithDesiredEppn->home_library = $new_home_library;
            $libCardWithDesiredEppn->save();

            $this->activateBestLibraryCard($libCards);
        } else
            throw new AuthException("Couldn't find UserCard with eppn '$eppn'");
    }

    /**
     * Checks for UserRow->major value to remove access connected with passed $home_library.
     *
     * @param string $home_library
     *
     * @return void
     */
    protected function deleteMajorAccess($home_library)
    {
        if (! empty($home_library) && $home_library !== 'Dummy' &&
             ! empty($this->major)) {

            $allowed = [];

            $currentAccesses = explode(static::COLUMN_MAJOR_GLUE, $this->major);
            foreach ($currentAccesses as $currentAccess) {
                $prefix = explode(ShibbolethIdentityManager::SEPARATOR,
                    $currentAccess)[0];

                // We want to delete access connected with home_library being disconnected
                if ($prefix !== $home_library) {
                    $allowed[] = $currentAccess;
                }
            }

            $currentAccesses = implode(static::COLUMN_MAJOR_GLUE, $allowed);

            // Do not query SQL until neccessary
            if ($currentAccesses !== $this->major) {

                $this->major = $currentAccesses;
                $this->save();
            }
        }
    }
}