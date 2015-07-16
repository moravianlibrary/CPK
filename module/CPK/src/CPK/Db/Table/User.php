<?php
/**
 * Table Definition for user
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use VuFind\Db\Table\User as BaseUser, Zend\Db\Sql\Select, CPK\Db\Row\User as UserRow;

/**
 * Table Definition for user
 *
 * @category VuFind2
 * @package Db_Table
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class User extends BaseUser
{

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->table = 'user';
        $this->rowClass = 'CPK\Db\Row\User';
        $this->config = $config;
    }

    /**
     * Retrieve a user object from the database based on eppn which is in user_card table
     *
     * @param string $eppn
     *            eduPersonPrincipalName to use for retrieval.
     *
     * @return UserRow
     */
    public function getByEppn($eppn)
    {
        $rowId = $this->getUserRowIdByEppn($eppn);

        if ($rowId) {
            // Now get UserRow object using TableGateway
            $userRow = $this->getUserByRowId($rowId);

            return $userRow;
        } else
            return false;
    }

    public function getUserRowIdByEppn($eppn)
    {
        // First find out if there is any eppn like this one
        $sql = "SELECT user_id FROM user_card WHERE eppn = '$eppn'";

        $result = $this->executeAnySQLCommand($sql)->current();

        if ($result == null || empty($result['user_id']))
            return false;

        return $result['user_id'];
    }

    /**
     * Returns UserRow object with known row id.
     *
     * @param integer $rowId
     * @return UserRow
     */
    public function getUserByRowId($rowId)
    {
        return $this->select([
            'id' => $rowId
        ])->current();
    }

    public function mergeIntoThisUser(User $user)
    {
        // TODO ...
        return true;
    }

    /**
     * Saves user's connection token to session table in order to connect user's accounts later.
     *
     * @param string $token
     * @param string $eppn
     * @return mixed string $tokenRowId | false $succeeded
     */
    public function saveUserConsolidationToken($token, $eppn)
    {
        $userRowId = $this->getUserRowIdByEppn($eppn);

        if (! $userRowId)
            return false;

        $last_used = time();
        $created = date('Y-m-d H:i:s');

        // 128 chars max ..
        $session_id = $token;

        $sql = "INSERT INTO vufind.session (session_id, data, last_used, created) VALUES ('" . $session_id . "', '" . $userRowId . "', '" . $last_used . "', '" . $created . "');";

        $result = $this->executeAnySQLCommand($sql);
        $generatedValue = $result->getGeneratedValue();

        return $generatedValue === null ? false : $generatedValue;
    }

    /**
     * Returns UserRow object with known token from session table.
     *
     * @param string $token
     * @param int $secondsToExpire
     *
     * @return UserRow
     */
    public function getUserFromConsolidationToken($token, $secondsToExpire = 60*15)
    {
        $sql = "SELECT data, last_used FROM session WHERE session_id = '" . $token . "';";

        $result = $this->executeAnySQLCommand($sql)->current();

        if ($result == null || empty($result['data']))
            return false;

        $this->deleteConsolidationToken($token);
        $clearedTokens = $this->clearAllExpiredTokens($secondsToExpire);

        // enforce lifetime of this session data
        if (! empty($result->last_used) && $result->last_used + $secondsToExpire <= time()) {
            throw new \VuFind\Exception\Auth('Consolidation token has expired.');
        }

        return $this->getUserByRowId($result['data']);
    }

    /**
     * Clears all expired tokens.
     *
     * @return number clearedTokens
     */
    protected function clearAllExpiredTokens($secondsToExpire = 60*15)
    {
        $dateTime = date('Y-m-d H:i:s', time() - $secondsToExpire);
        $sql = "DELETE FROM session WHERE created < '$dateTime';";
        return $this->executeAnySQLCommand($sql)->getAffectedRows();
    }

    protected function deleteConsolidationToken($token)
    {
        $sql = "DELETE FROM session WHERE session_id = '" . $token . "';";
        $this->executeAnySQLCommand($sql);
    }

    /**
     * Executes any sql command.
     *
     * This was implemented because of the need of looking into user_card table.
     *
     * @return \Zend\Db\Adapter\Driver\Mysqli\Result $result
     */
    protected function executeAnySQLCommand($sql)
    {
        return $this->getAdapter()->driver->getConnection()->execute($sql);
    }
}