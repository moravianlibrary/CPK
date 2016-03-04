<?php
/**
 * Table Definition for Notifications
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

use VuFind\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select, CPK\Db\Row\Notifications;

class Notifications extends Gateway
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
        $this->table = 'notifications';
        $this->rowClass = 'CPK\Db\Row\Notifications';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Retrieves Notifications for a user.
     *
     * @param string $cat_username            
     *
     * @return Notifications
     */
    public function getNotifications($cat_username)
    {
        $select = new Select();
        $select->columns([
            'id'
        ]);
        $select->from('user_card');
        $select->where([
            'cat_username' => $cat_username
        ]);
        
        $userCard = $this->executeAnyZendSQLSelect($select)->current();
        
        return $this->select([
            'id' => $userCard['id']
        ])->current();
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
}