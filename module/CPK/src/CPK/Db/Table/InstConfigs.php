<?php
/**
 * Table Definition for Institutions Configurations
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
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use CPK\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select;

/**
 * This database table is supposed to fulfill the needs of having a temporary
 * storage of institution's translations requested by their administrators
 * before those are approved into production
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
class InstConfigs extends Gateway
{

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Mini-cache
     *
     * @var array
     */
    protected $cache;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     *
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->table = 'inst_configs';
        $this->rowClass = 'CPK\Db\Row\InstConfigs';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Creates new configuration key value pairs for an institution specified by $source
     *
     * @param string $source
     * @param array $config
     *
     * @return int $countKeysApproved
     */
    public function createNewConfig($source, array $config)
    {
        $timestamp = date('Y-m-d H:i:s');

        // This will prevent autocommit to Db
        $this->getDbConnection()->beginTransaction();

        $this->deleteLastRequestConfig($source);

        $countKeysApproved = 0;

        foreach ($config as $section => $keyValues) {
            foreach ($keyValues as $key => $value) {
                $row = $this->createRow();

                $row->source = $source;
                $row->section = $section;
                $row->key = $key;
                $row->value = $value;
                $row->timestamp_requested = $timestamp;
                $row->user_requested = $_SESSION['Account']['userId'];

                $row->save();

                ++$countKeysApproved;
            }
        }

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        return $countKeysApproved;
    }

    /**
     * Flags an configuration within this table as approved
     *
     * @param string $source
     *
     * @return array
     */
    public function approveConfig($config, $source)
    {
        $timestamp = date('Y-m-d H:i:s');

        // This will prevent autocommit to Db
        $this->getDbConnection()->beginTransaction();

        $requestedConfig = $this->getRawRequestedConfig($source);

        foreach ($requestedConfig as $row) {

            $key = $row->key;
            $section = $row->section;

            // Change it if portal admin changed it during approval
            if (isset($config[$section][$key]) && $config[$section][$key] !== $row->value) {
                $row->value = $config[$section][$key];
            }

            $row->timestamp_approved = $timestamp;
            $row->user_approved = $_SESSION['Account']['userId'];

            $row->save();
        }

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        return $requestedConfig;
    }

    /**
     * Retrieves all the configurations not approved yet.
     *
     * @return array
     */
    public function getAllRequestConfigsWithActive()
    {
        $configs = [];

        $allRequested = $this->select('timestamp_approved IS NULL');

        foreach ($allRequested as $rowRequested) {

            $source = $rowRequested->source;
            $section = $rowRequested->section;
            $key = $rowRequested->key;
            $value = $rowRequested->value;

            $configs[$source]['requested'][$section][$key] = $value;
        }

        foreach ($configs as $source => $sourceRequestedConfig) {
            $configs[$source]['active'] = $this->getApprovedConfig($source);

            if ($configs[$source]['active'] === false)
                $configs[$source]['active'] = [];
        }

        return $configs;
    }

    /**
     * Removes last requested config associated with institution identified by $source
     *
     * @param string $source
     *
     * @return number
     */
    public function deleteLastRequestConfig($source)
    {
        /**
         * This where clausule uses this suggestion of updating value being selected within a subquery:
         * http://stackoverflow.com/questions/45494/mysql-error-1093-cant-specify-target-table-for-update-in-from-clause#answer-9843719
         */
        return $this->delete([
            "timestamp_requested IN (SELECT timestamp_requested FROM (SELECT DISTINCT MAX(timestamp_requested) FROM $this->table WHERE source = ? AND timestamp_approved IS NULL) as a) AND source = ? AND timestamp_approved IS NULL" => [
                $source,
                $source
            ]
        ]);
    }

    /**
     * Retrieves latest approved config specified by a source
     *
     * @param string $source
     *
     * @return mixed array|false
     */
    public function getApprovedConfig($source)
    {
        return $this->getConfig($source, 'approved');
    }

    /**
     * Retrieves latest requested config specified by a source
     *
     * @param string $source
     *
     * @return mixed array|false
     */
    public function getRequestedConfig($source)
    {
        return $this->getConfig($source, 'requested');
    }

    /**
     * Retrieves latest approved logo from within an institution.
     *
     * Returns false if no logo found.
     *
     * @param string $source
     * @return string|boolean
     */
    public function getLatestApprovedLogo($source)
    {
        $select = $this->sql->select();

        $select->columns(['id','value']);

        $select->where(['source' => $source, 'section' => 'Catalog', 'key' => 'logo']);

        $select->order('timestamp_approved DESC');
        $select->limit(1);

        $lastLogoRow = $this->selectWith($select)->current();

        if (isset($lastLogoRow['value'])) {
            return $lastLogoRow['value'];
        }

        return false;
    }

    /**
     * Retrieves configuration from the table but does not rearrange it into object.
     *
     * It rather stays in a format of multiple Rows capable of editing themselves using the Zend API.
     *
     * @param string $source
     *
     * @return array|false
     */
    protected function getRawRequestedConfig($source)
    {
        return $this->getConfig($source, 'requested', true);
    }

    /**
     * Retrieves latest config specified by a source with latest timestampType.
     *
     * Returns false if no cofiguration found for an institution
     *
     * @param string $source
     * @param string $timestampType
     * @param boolean $stayRaw
     *
     * @return mixed|boolean|array
     */
    protected function getConfig($source, $timestampType, $stayRaw = false)
    {
        $sqlAppendix = '';
        if ($timestampType === 'requested')
            $sqlAppendix = 'AND timestamp_approved IS NULL';

        $dbConfig = $this->select([
            "timestamp_$timestampType IN (SELECT DISTINCT MAX(timestamp_$timestampType) FROM $this->table WHERE source = ? $sqlAppendix) AND source = ? $sqlAppendix" => [
                $source,
                $source
            ]
        ]);

        if ($dbConfig->count() === 0) {
            return false;
        }

        if ($stayRaw)
            return $dbConfig;

        $config = [];
        foreach ($dbConfig as $dbConfigRow) {

            $section = $dbConfigRow['section'];

            $key = $dbConfigRow['key'];

            $value = $dbConfigRow['value'];

            $config[$section][$key] = $value;
        }

        return $config;
    }
}