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

    /**
     * This method basically replaces all occurrences of $from->id (UserRow id) in tables
     * comments, user_resource, user_list & search with $into->id in user_id column.
     *
     * @param UserRow $from
     * @param UserRow $into
     * @throws AuthException
     * @return void
     */
    public function mergeUserInAllTables(UserRow $from, UserRow $into)
    {
        if (empty($into->id) || empty($from->id)) {
            throw new AuthException("Cannot merge two UserRows without knowlegde of both UserRow ids");
        }

        $this->updateUserRows($from, $into);

        /**
         * Table names which contain user_id as a relation to user.id foreign key
         */
        $tablesToUpdateUserId = [
            "comments",
            "user_resource",
            "user_list",
            "search"
        ];

        $sqlCommands = [];
        foreach ($tablesToUpdateUserId as $table) {
            $sqlCommands[] = "UPDATE $table t SET t.user_id = '$into->id' WHERE t.user_id = '$from->id';";
        }

        $this->executeAnySQLTransaction($sqlCommands);

        // Perform User deletion
        $from->delete();
    }

    /**
     * This method picks up non-empty values from $from into $into UserRow, which are
     * probably more appreciated than current values within $into UserRow.
     *
     * This method <b>DOES NOT DELETE</b> any UserRow.
     *
     * It also doesn't handle cat_username neither home_library as those were previously
     * set by UserRow::activateBestLibraryCard method.
     *
     * @param UserRow $from
     * @param UserRow $into
     * @return void
     */
    protected function updateUserRows(UserRow $from, UserRow $into)
    {
        $basicNonEmptyMerge = [
            "firstname",
            "lastname",
            "email",
            "password",
            "pass_hash",
            "verify_hash"
        ];

        foreach ($basicNonEmptyMerge as $column) {
            // Replace current value only if it is empty
            if (empty($into->$column) && ! empty($from->$column)) {
                $into->$column = $from->$column;
            }
        }

        $basicMerge = [
            "major",
            "college"
        ];

        foreach ($basicMerge as $column) {
            if (! empty($from->$column)) {

                // Replace current value only if it is empty
                if (empty($into->$column)) {
                    $into->$column = $from->$column;
                } else {
                    // If are both set, then merge those using ';' delimiter
                    $into->$column .= ';' . $from->$column;
                }
            }
        }

        $into->save();
    }

    /**
     * Searches for count of '$username;[0-9]+' regex matches in username column of user table.
     *
     * @param string $username
     * @return number
     */
    public function getUsernameRank($username)
    {
        if (empty($username))
            return 0;

        $sql = "SELECT username FROM user WHERE username RLIKE '$username;[0-9]+' ORDER BY created DESC LIMIT 1";

        $result = $this->executeAnySQLCommand($sql)->current();

        if (empty($result['username']))
            return 0;

        $splittedResult = split(';', $result['username']);
        if (count($splittedResult) === 2) {
            return intval($splittedResult[1]);
        } else
            return 0;
    }

    /**
     * Saves user's connection token to session table in order to connect user's accounts later.
     *
     * @param string $token
     * @param number $userRowId
     * @return mixed string $tokenRowId | false $succeeded
     */
    public function saveUserConsolidationToken($token, $userRowId)
    {
        if (empty($userRowId))
            return false;

        $created = date('Y-m-d H:i:s');

        // 128 chars max ..
        $session_id = $token;

        $sql = "INSERT INTO vufind.session (session_id, data, last_used, created) VALUES ('" . $session_id . "', '" . $userRowId . "', '-1', '" . $created . "');";

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
        $sql = "SELECT data, UNIX_TIMESTAMP(created) AS created FROM session WHERE last_used = '-1' AND session_id = '" . $token . "';";

        $result = $this->executeAnySQLCommand($sql)->current();

        if ($result == null || empty($result['data']) || empty($result['created']))
            return false;

        $this->deleteConsolidationToken($token);
        $clearedTokens = $this->clearAllExpiredTokens($secondsToExpire);

        $isExpired = $time >= intval($result->created) + $secondsToExpire;

        if ($isExpired)
            throw new \VuFind\Exception\Auth('Consolidation token has expired.');

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
        $sql = "DELETE FROM session WHERE last_used = '-1' AND created <= '$dateTime';";
        return $this->executeAnySQLCommand($sql)->getAffectedRows();
    }

    /**
     * Deletes token from the session table & returns either 1 on success
     * or 0 on failure.
     *
     * @param
     *            number clearedTokens
     */
    protected function deleteConsolidationToken($token)
    {
        $sql = "DELETE FROM session WHERE last_used = '-1' AND session_id = '" . $token . "';";
        $this->executeAnySQLCommand($sql)->getAffectedRows();
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
        return $this->getDbConnection()->execute($sql);
    }

    /**
     * Executes all supplied sqlCommands in a SQL transaction.
     *
     * @param array $sqlCommands
     * @return void
     */
    protected function executeAnySQLTransaction(array $sqlCommands)
    {
        if (count($sqlCommands) > 0) {
            try {
                $conn = $this->getDbConnection();

                $conn->beginTransaction();

                foreach ($sqlCommands as $sql) {
                    if (! empty($sql))
                        $result = $conn->execute($sql);
                }

                $conn->commit();
            } catch (\Exception $e) {
                $conn->rollback();
                throw $e;
            }
        }
    }

    /**
     *
     * @return \Zend\Db\Adapter\Driver\Mysqli\Connection $conn
     */
    protected function getDbConnection()
    {
        return $this->getAdapter()->driver->getConnection();
    }
}