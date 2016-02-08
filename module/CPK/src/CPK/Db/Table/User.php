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

use VuFind\Db\Table\User as BaseUser, CPK\Db\Row\User as UserRow, Zend\Db\Sql\Select, Zend\Db\Sql\Update, Zend\Db\Adapter\Driver\Mysqli\Result;

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
     * Returns UserRow object with known token from session table.
     *
     * @param string $token
     * @param int $secondsToExpire
     *
     * @return UserRow
     */
    public function getUserFromConsolidationToken($token, $secondsToExpire = 900)
    {
        $select = new Select('session');
        $select->columns([
            'data',
            'created' => new \Zend\Db\Sql\Expression('UNIX_TIMESTAMP(created)')
        ]);

        $select->where([
            'last_used' => '-1',
            'session_id' => $token
        ]);

        $result = $this->executeAnyZendSQLSelect($select)->current();

        if ($result == null || empty($result['data']) || empty($result['created']))
            return false;

        $this->deleteConsolidationToken($token);
        $clearedTokens = $this->clearAllExpiredTokens($secondsToExpire);

        $isExpired = time() >= intval($result['created']) + $secondsToExpire;

        if ($isExpired)
            throw new \VuFind\Exception\Auth('Consolidation token has expired.');

        return $this->getUserByRowId($result['data']);
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

        $select = new Select('user');
        $select->columns([
            'username'
        ]);
        $select->order('created DESC');
        $select->limit(1);

        $predicate = new \Zend\Db\Sql\Predicate\Expression("username RLIKE '$username;[0-9]+'");
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();

        if (! $result || empty($result['username']))
            return 0;

        $splittedResult = explode(';', $result['username']);
        if (count($splittedResult) === 2) {
            return intval($splittedResult[1]);
        } else
            return 0;
    }

    /**
     * Retrieve a user object from the database based on eduPersonPrincipalName from his libCards.
     *
     * Returns false if not found anything
     *
     * @param string $eppn
     *            eduPersonPrincipalName to use for retrieval.
     *
     * @return mixed UserRow | false
     */
    public function getUserRowByEppn($eppn)
    {
        // FIXME: Process of retrieving User from eppn would be faster if made in one query ..
        $rowId = $this->getUserRowIdByEppn($eppn);

        if ($rowId) {
            // Now get UserRow object using TableGateway
            $userRow = $this->getUserByRowId($rowId);

            return $userRow;
        } else
            return false;
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

        $this->mergeUserRows($from, $into);

        /**
         * Table names which contain user_id as a relation to user.id foreign key
         */
        $tablesToUpdateUserId = [
            "comments",
            "user_resource",
            "user_list",
            "search"
        ];

        // This will prevent autocommit to Db
        $this->getDbConnection()->beginTransaction();

        foreach ($tablesToUpdateUserId as $table) {

            $update = new Update($table);
            $update->set([
                'user_id' => $into->id
            ]);
            $update->where([
                'user_id' => $from->id
            ]);

            $this->executeAnyZendSQLUpdate($update);
        }

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        // Perform User deletion
        $from->delete();
    }

    /**
     * Saves user's connection token to session table in order to connect user's accounts later.
     *
     * @param string $token
     * @param number $userRowId
     * @return boolean $succeeded
     */
    public function saveUserConsolidationToken($token, $userRowId)
    {
        if (empty($userRowId))
            return false;

        $created = date('Y-m-d H:i:s');

        $generatedValue = $this->getDbTable('session')->insert([
            'session_id' => $token,
            'data' => $userRowId,
            'last_used' => '-1',
            'created' => $created
        ]);

        return $generatedValue === 1;
    }

    /**
     * Clears all expired tokens.
     *
     * @return number clearedTokens
     */
    protected function clearAllExpiredTokens($secondsToExpire)
    {
        $dateTime = date('Y-m-d H:i:s', time() - $secondsToExpire);

        return $this->getDbTable('session')->delete([
            'last_used' => '-1',
            'created <= ?' => $dateTime
        ]);
    }

    /**
     * Deletes token from the session table & returns either 1 on success
     * or 0 on failure.
     *
     * @param string $token
     */
    protected function deleteConsolidationToken($token)
    {
        $this->getDbTable('session')->delete([
            'last_used' => '-1',
            'session_id' => $token
        ]);
    }

    /**
     * Executes any Select
     *
     * @param Select $select
     *
     * @return Result $result
     */
    protected function executeAnyZendSQLSelect(Select $select)
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        return $statement->execute();
    }

    /**
     * Executes any Update
     *
     * @param Update $update
     *
     * @return Result $result
     */
    protected function executeAnyZendSQLUpdate(Update $update)
    {
        $statement = $this->sql->prepareStatementForSqlObject($update);
        return $statement->execute();
    }

    /**
     * Returns database connection.
     *
     * @return \Zend\Db\Adapter\Driver\Mysqli\Connection $conn
     */
    protected function getDbConnection()
    {
        return $this->getAdapter()->driver->getConnection();
    }

    /**
     * Retrieve an UserRow id based on eduPersonPrincipalName from his libCards
     *
     * @param string $eppn
     *            eduPersonPrincipalName to use for retrieval.
     *
     * @return number user_id
     */
    protected function getUserRowIdByEppn($eppn)
    {
        // First find out if there is any eppn like this one
        $select = new Select();
        $select->columns([
            'user_id'
        ]);
        $select->from('user_card');
        $select->where([
            'eppn' => $eppn
        ]);

        $result = $this->executeAnyZendSQLSelect($select)->current();

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
    protected function getUserByRowId($rowId)
    {
        return $this->select([
            'id' => $rowId
        ])->current();
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
    protected function mergeUserRows(UserRow $from, UserRow $into)
    {
        $mergedSomething = false;

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
                $mergedSomething = true;
            }
        }

        $basicMerge = [
            "major",
            "college"
        ];

        foreach ($basicMerge as $column) {
            // Merge not needed if the columns are identical
            if (! empty($from->$column) && $into->$column !== $from->$column) {

                // Replace current value only if it is empty
                if (empty($into->$column)) {
                    $into->$column = $from->$column;
                    $mergedSomething = true;
                } else {
                    // If are both set, then merge those using ';' delimiter
                    $into->$column .= ';' . $from->$column;
                    $mergedSomething = true;
                }
            }
        }

        if ($mergedSomething)
            $into->save();
    }
    
    /**
     * Returns rows from user and user_card tables, where user.major is not empty.
     * 
     * @return array
     */
    public function getUsersWithPermissions()
    {
        $select = new Select('user_card');
        $select->columns(['eppn', 'major']);
        $select->where("`major` IS NOT NULL AND `major` <> ''");
        
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);
        $resultsArray = $resultSet->toArray();
        
        return $resultsArray;
    }
    
    /**
     * Sets permissions to user
     * 
     * @param string $eppn EduPersonPrincipalName
     * @param string $major Major permissions
     */
    public function saveUserWithPermissions($eppn, $major)
    {
        $update = new Update('user_card');
        $update->set([
            'major' => $major
        ]);
        $update->where(['eppn' => $eppn]);

        return $this->executeAnyZendSQLUpdate($update);
    }
}