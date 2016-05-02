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

use VuFind\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select;

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
     * @param string $key
     * @param array $languageTranslations
     *
     * @return \CPK\Db\Row\InstTranslations
     */
    public function createNewConfig($source, $userId, array $config)
    {
        // This will prevent autocommit to Db

        $timestamp = date('Y-m-d H:i:s');

        $this->getDbConnection()->beginTransaction();

        foreach ($config as $section => $keyValues) {
            foreach ($keyValues as $key => $value) {
                $row = $this->createRow();

                $row->source = $source;
                $row->section = $section;
                $row->key = $key;
                $row->value = $value;
                $row->timestamp = $timestamp;
                $row->user_id = $userId;

                $row->save();
            }
        }

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        return $row;
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
}