<?php
/**
 * Table Definition for UserSettings
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
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
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use CPK\Db\Table\Gateway,
    Zend\Config\Config,
    Zend\Db\Sql\Update,
    Zend\Db\Sql\Select;

/**
 * Table Definition for UserSettings
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class UserSettings extends Gateway
{
    /**
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        parent::__construct('user_settings', 'CPK\Db\Row\UserSettings');
    }

    /**
     * Returns array of user settings from user_settings table
     *
     * @return array
     */
    public function getSettings(\CPK\Db\Row\User $user)
    {
        return $this->select(['user_id' => $user['id']])->toArray();
    }

    /**
     * Returns citation style for user from user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     *
     * @return string
     */
    public function getUserCitationStyle(\CPK\Db\Row\User $user)
    {
        $select = new Select($this->table);
        $select->columns([
            'citation_style'
        ]);
        $select->limit(1);

        $condition = 'user_id="'.$user['id'].'" AND citation_style IS NOT NULL';
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result['citation_style'];
    }

    /**
     * Returns whether user has a row in user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     *
     * @return bool
     */
    public function hasUserSettings(\CPK\Db\Row\User $user)
    {
        $select = new Select($this->table);
        $select->limit(1);

        $condition = 'user_id="'.$user['id'].'"';
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        if (! empty($result))
            return true;

        return false;
    }

    /**
     * Set preferred citation style into user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     * @param string $citationStyleValue
     *
     * @return void
     */
    public function setCitationStyle(\CPK\Db\Row\User $user, $citationStyleValue)
    {
        $hasUserSettingsAlready = $this->hasUserSettings($user);

        // insert new setting if not already set
        if (! $hasUserSettingsAlready) {

            $this->getDbConnection()->beginTransaction();
            $this->getDbTable($this->table)->insert([
                'user_id' => $user->id,
                'citation_style' => $citationStyleValue
            ]);
            $this->getDbConnection()->commit();

        } else { // update setting if already set

            $update = new Update($this->table);
            $update->set([
                'citation_style' => $citationStyleValue
            ]);
            $update->where([
                'user_id' => $user->id
            ]);

            $this->getDbConnection()->beginTransaction();
            $this->sql->prepareStatementForSqlObject($update)->execute();
            $this->getDbConnection()->commit();
        }
    }

    /**
     * Set preferred amount of records per page for user into user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     * @param tinyInt $recordsPerPage
     *
     * @return void
     */
    public function setRecordsPerPage(\CPK\Db\Row\User $user, $recordsPerPage)
    {
        $hasUserSettingsAlready = $this->hasUserSettings($user);

        // insert new setting if not already set
        if (! $hasUserSettingsAlready) {

            $this->getDbConnection()->beginTransaction();
            $this->getDbTable($this->table)->insert([
                'user_id' => $user->id,
                'records_per_page' => $recordsPerPage
            ]);
            $this->getDbConnection()->commit();

        } else { // update setting if already set

            $update = new Update($this->table);
            $update->set([
                'records_per_page' => $recordsPerPage
            ]);
            $update->where([
                'user_id' => $user->id
            ]);

            $this->getDbConnection()->beginTransaction();
            $this->sql->prepareStatementForSqlObject($update)->execute();
            $this->getDbConnection()->commit();
        }
    }

    /**
     * Returns records per page for user from user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     *
     * @return tinyInt
     */
    public function getRecordsPerPage(\CPK\Db\Row\User $user)
    {
        $select = new Select($this->table);
        $select->columns([
            'records_per_page'
        ]);
        $select->limit(1);

        $condition = 'user_id="'.$user['id'].'" AND records_per_page IS NOT NULL';
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result['records_per_page'];
    }

    /**
     * Returns sorting of user settings from user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     *
     * @return string
     */
    public function getSorting(\CPK\Db\Row\User $user)
    {
        $select = new Select($this->table);
        $select->columns([
            'sorting'
        ]);
        $select->limit(1);

        $condition = 'user_id="'.$user['id'].'" AND sorting IS NOT NULL';
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result['sorting'];
    }

    /**
     * Set preferred sorting for user into user_settings table
     *
     * @param \VuFind\Db\Row\User $user
     * @param string $preferredSorting
     *
     * @return void
     */
    public function setPreferredSorting(\CPK\Db\Row\User $user, $preferredSorting)
    {
        $hasUserSettingsAlready = $this->hasUserSettings($user);

        // insert new setting if not already set
        if (! $hasUserSettingsAlready) {

            $this->getDbConnection()->beginTransaction();
            $this->getDbTable($this->table)->insert([
                'user_id' => $user->id,
                'sorting' => $preferredSorting
            ]);
            $this->getDbConnection()->commit();

        } else { // update setting if already set

            $update = new Update($this->table);
            $update->set([
                'sorting' => $preferredSorting
            ]);
            $update->where([
                'user_id' => $user->id
            ]);

            $this->getDbConnection()->beginTransaction();
            $this->sql->prepareStatementForSqlObject($update)->execute();
            $this->getDbConnection()->commit();
        }
    }

    /**
     * Save chosen institutions to DB
     *
     * @param \VuFind\Db\Row\User $user
     * @param array $institutions
     *
     * @return void
     */
    public function saveTheseInstitutions(\CPK\Db\Row\User $user, array $institutions = [])
    {
        $data = "";
        foreach($institutions as $institution) {
            $data .= $institution.';';
        }
        $data = trim($data, ";");

        $hasUserSettingsAlready = $this->hasUserSettings($user);

        // insert new setting if not already set
        if (! $hasUserSettingsAlready) {

            $this->getDbConnection()->beginTransaction();
            $this->getDbTable($this->table)->insert([
                'user_id' => $user->id,
                'saved_institutions' => $data
            ]);
            $this->getDbConnection()->commit();

        } else { // update setting if already set

            $update = new Update($this->table);
            $update->set([
                'saved_institutions' => $data
            ]);
            $update->where([
                'user_id' => $user->id
            ]);

            $this->getDbConnection()->beginTransaction();
            $this->sql->prepareStatementForSqlObject($update)->execute();
            $this->getDbConnection()->commit();
        }
    }

    /**
     * Get saved institutions
     *
     * @param \VuFind\Db\Row\User $user
     *
     * @return array
     */
    public function getSavedInstitutions(\CPK\Db\Row\User $user)
    {
        $select = new Select($this->table);
        $select->columns([
            'saved_institutions'
        ]);
        $select->limit(1);

        $condition = 'user_id="'.$user['id'].'" AND saved_institutions IS NOT NULL';
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return explode(";", $result['saved_institutions']);
    }

    /**
     * Remove saved institutions
     *
     * @param \VuFind\Db\Row\User $user
     * @param string $institutionToRemove
     *
     * @return string
     */
    public function removeSavedInstitution(\CPK\Db\Row\User $user, $institutionToRemove)
    {
        $institutions = $this->getSavedInstitutions($user);
        if (! empty($institutions)) {
            foreach ($institutions as $key => $institution) {
                if ($institution == $institutionToRemove) {
                    unset($institutions[$key]);
                }
            }
            $this->saveTheseInstitutions($user, $institutions);
        }
    }
    /**
     * Save institution
     *
     * @param \VuFind\Db\Row\User $user
     * @param string $institutionToSave
     *
     * @return void
     */
    public function saveInstitution(\CPK\Db\Row\User $user, $institutionToSave)
    {
        $institutions = $this->getSavedInstitutions($user);

        $institutions[] = $institutionToSave;
        $this->saveTheseInstitutions($user, $institutions);
    }
}