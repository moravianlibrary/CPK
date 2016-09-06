<?php
/**
 * Table Definition for Frontend
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
 * Table Definition for Frontend
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class Frontend extends Gateway
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
        parent::__construct('frontend', 'CPK\Db\Row\Frontend');
    }

    /**
     * Returns homepage widgets
     *
     * @return array
     */
    public function getHomepageWidgets()
    {
        $select = new Select($this->table);
        $select->columns(['first_homepage_widget', 'second_homepage_widget', 'third_homepage_widget']);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }

    /**
     * Returns inspiration widgets
     *
     * @return array
     */
    public function getInspirationWidgets()
    {
        $select = new Select($this->table);
        $select->columns([
            'first_inspiration_widget',
            'second_inspiration_widget',
            'third_inspiration_widget',
            'fourth_inspiration_widget',
            'fifth_inspiration_widget',
            'sixth_inspiration_widget'
        ]);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }

    /**
     * Save frontend widgets
     *
     * @param array $data
     *
     * @return void
     */
    public function saveFrontendWidgets(array $data)
    {
        $update = new Update($this->table);

        $update->set($data);

        $this->executeAnyZendSQLUpdate($update);
    }
}