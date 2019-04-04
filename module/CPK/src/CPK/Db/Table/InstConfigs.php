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

use Zend\Config\Config;

/**
 * This database table is supposed to fulfill the needs of having a temporary
 * storage of institution's translations added by administrator
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
     * @return bool
     */
    public function setNewConfig($source, array $config)
    {
        $activeConfig = $this->getConfig($source);

        $config = $this->diffConfigs($config, $activeConfig);

        $timestamp = date('Y-m-d H:i:s');

        // This will prevent autocommit to Db
        $this->getDbConnection()->beginTransaction();

        foreach ($config as $section => $keyValues) {
            foreach ($keyValues as $key => $value) {
                if (isset($activeConfig[$section]) && in_array($key, array_keys($activeConfig[$section]))) {
                    $this->update([
                        'value' => $value,
                    ], [
                        'source' => $source,
                        'section' => $section,
                        'key' => $key
                    ]);
                    continue;
                }

                $row = $this->createRow();
                $row->source = $source;
                $row->section = $section;
                $row->key = $key;
                $row->value = $value;
                $row->timestamp = $timestamp;
                $row->save();
            }
        }

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        return true;
    }

    /**
     * Compares new configuration added by admin with active configuration in database and returns difference.
     *
     * @param $newConfig
     * @param $oldConfig
     * @return mixed
     */
    public function diffConfigs($newConfig, $oldConfig) {
        foreach ($newConfig as $section => $values) {
            if (!isset($oldConfig[$section])) {
                continue;
            }

            $diff = array_diff_assoc($values, $oldConfig[$section]);

            if(empty($diff) && !is_null($diff)) {
                unset($newConfig[$section]);
            } else {
                $newConfig[$section] = $diff;
            }
        }

        return $newConfig;
    }

    /**
     * Retrieves latest config specified by a source with latest timestampType.
     *
     * Returns false if no configuration found for an institution
     *
     * @param string $source
     * @param boolean $stayRaw
     * @return array|bool|mixed
     * @internal param string $timestampType
     */
    public function getConfig($source, $stayRaw = false)
    {
        $dbConfig = $this->select([
            'source' => $source
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