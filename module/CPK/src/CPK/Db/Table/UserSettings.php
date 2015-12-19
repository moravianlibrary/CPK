<?php
/**
 * Table Definition for UserSettings
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
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use Zend\Db\Sql\Select, 
    Zend\Db\Sql\Update, 
    Zend\Db\Adapter\Driver\Mysqli\Result,
    VuFind\Db\Table\Gateway;

/**
 * Table Definition for UserSettings
 *
 * @category VuFind2
 * @package Db_Table
 * @author Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
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
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
        parent::__construct('user_preference', 'CPK\Db\Row\UserSettings');
    }

    /**
     * Returns array of settings from citation_style table
     *
     * @return array
     */
    public function getSettings(\CPK\Db\Row\User $user)
    {       
        return $this->select(['user_id' => $user['id']])->toArray()[0];
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