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

class InstTranslations extends Gateway
{

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

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
     * @param string $csTranslated
     * @param string $enTranslated
     *
     * @throws \Exception
     *
     * @return \CPK\Db\Row\InstTranslations
     */
    public function createNewTranslation($source, $key, $csTranslated, $enTranslated)
    {
        if (array_search(true, array_map('empty', [
            $source,
            $key,
            $csTranslated,
            $enTranslated
        ])) !== false) {
            throw new \Exception('Cannot create new translation with empty value');
        }

        $row = $this->createRow();

        $row->source = $source;
        $row->key = $key;
        $row->cs_translated = $csTranslated;
        $row->en_translated = $enTranslated;

        $row->save();

        return $row;
    }

    /**
     * Alters an specified translation row, which if found by matching $source & $key
     *
     * @param string $source
     * @param string $key
     * @param string $csTranslated
     * @param string $enTranslated
     *
     * @throws \Exception
     *
     * @return \CPK\Db\Row\InstTranslations
     */
    public function alterTranslation($source, $key, $csTranslated, $enTranslated)
    {
        // One of csTranslated & enTranslated may be empty ..
        if (array_search(true, array_map('empty', [
            $source,
            $key,
            $csTranslated . $enTranslated
        ])) !== false) {
            throw new \Exception('Cannot alter translation with empty value');
        }

        $row = $this->getTranslation($source, $key);

        if (! empty($csTranslated))
            $row->cs_translated = $csTranslated;

        if (! empty($enTranslated))
            $row->en_translated = $enTranslated;

        $row->save();

        return $row;
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
        return $this->select([
            'source' => $source
        ]);
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