<?php
/**
 * Table Definition for Institutions Translations
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
class InstTranslations extends Gateway
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
        $this->table = 'inst_translations';
        $this->rowClass = 'CPK\Db\Row\InstTranslations';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Creates new translation for an institution specified by $source
     *
     * @param string $source
     * @param string $key
     * @param array $languageTranslations
     *
     * @return \CPK\Db\Row\InstTranslations
     */
    public function createNewTranslation($source, $key, $languageTranslations)
    {
        if (empty($key) || empty($languageTranslations)) {
            return false;
        }

        $row = $this->createRow();

        $row->source = $source;
        $row->key = $key;

        foreach ($languageTranslations as $lang => $value) {
            $row[$lang . '_translated'] = $value;
        }

        $row->save();

        return $row;
    }

    /**
     * Deletes all translations associated with an institution identified by $source
     *
     * @param string $source
     *
     * @return number
     */
    public function deleteInstitutionTranslations($source)
    {
        return $this->delete([
            'source' => $source
        ]);
    }

    /**
     * Retrieves all institution translations identified by $source
     *
     * @param string $source
     *
     * @return array
     */
    public function getInstitutionTranslations($source)
    {
        if (isset($cache[$source]))
            return $cache[$source];

        $cache[$source] = $this->select([
            'source' => $source
        ]);

        return $cache[$source];
    }

    /**
     * Retrieves all institution translations
     *
     * @return array
     */
    public function getAllTranslations()
    {
        return $this->select()->toArray();
    }

    /**
     *
     * @param string $source
     * @param string $key
     *
     * @return \CPK\Db\Row\InstTranslations
     */
    protected function getTranslation($source, $key)
    {
        return $this->select([
            'source' => $source,
            'key' => $key
        ])->current();
    }
}