<?php
/**
 * Table Definition for CitationStyle
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
 * Table Definition for citation style
 *
 * @category VuFind2
 * @package Db_Table
 * @author Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class CitationStyle extends Gateway
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
       // $this->table = ;
        //$this->rowTable = ';
        //parent::__construct($this->table, $this->rowTable);
        parent::__construct('citation_style', 'CPK\Db\Row\CitationStyle');
    }
    
    /**
     * Construct the prototype for rows.
     *
     * @return object
     */
    protected function initializeRowPrototype()
    {
        $prototype = parent::initializeRowPrototype();
        $prototype->setConfig($this->config);
        return $prototype;
    }

    /**
     * Returns array of settings from citation_style table
     *
     * @return array
     */
    public function getAllStyles()
    {       
       /* $select = new Select($this->table);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        
        return $result;*/
        
        return $this->select()->current();
    }
    
    /**
     * Returns database connection
     *
     * @return \Zend\Db\Adapter\Driver\Mysqli\Connection
     */
    protected function getDbConnection()
    {
        return $this->getAdapter()->driver->getConnection();
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