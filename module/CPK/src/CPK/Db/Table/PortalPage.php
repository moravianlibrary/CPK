<?php
/**
 * Table Definition for PortalPage
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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

use VuFind\Db\Table\Gateway,
    Zend\Config\Config,
    Zend\Db\Sql\Select;

/**
 * Table Definition for PortalPage
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class PortalPage extends Gateway
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
        $this->table = 'portal_pages';
        $this->rowClass = 'CPK\Db\Row\PortalPage';
        parent::__construct($this->table, $this->rowClass);
    }
    
    /**
     * Executes any Select
     *
     * @param Zend\Db\Sql\Select $select
     *
     * @return Zend\Db\Adapter\Driver\ResultInterface $result
     */
    protected function executeAnyZendSQLSelect(Select $select)
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        return $statement->execute();
    }
    
    /**
     * Executes any Update
     *
     * @param Zend\Db\Sql\Update $update
     *
     * @return Zend\Db\Adapter\Driver\ResultInterface $result
     */
    protected function executeAnyZendSQLUpdate(Update $update)
    {
        $statement = $this->sql->prepareStatementForSqlObject($update);
        return $statement->execute();
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
     * Returns all rows from portal_pages table
     * 
     * @param string    $languageCode, e.g. "en-cpk"
     * @param boolean   $publishedOnly Set to false to get all pages
     *
     * @return array
     */
    public function getAllPages($languageCode = '*', $publishedOnly = true)
    {       
        $select = new Select($this->table);
        
        $condition = '';
        if ($languageCode != '*') {
            $condition = "language_code='$languageCode'";
        }
        
        if ($publishedOnly) {
            if (! empty($condition)) {
                $condition .= ' AND published="1"';
            } else {
                $condition = 'published="1"';
            }
            
        }
        if (! empty($condition)) {
            $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
            $select->where($predicate);
        }
        
        $results= $this->executeAnyZendSQLSelect($select);
        
        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);
        
        return $resultSet->toArray();
    }
    
    /**
     * Return row from table
     *
     * @param string $prettyUrl
     * @param string    $languageCode, e.g. "en-cpk"
     *
     * @return array
     */
    public function getPage($prettyUrl, $languageCode)
    {
        $select = new Select($this->table);
        
        $subSelect = "SELECT `group` FROM `portal_pages` "
            ."WHERE `pretty_url`='$prettyUrl'";
        
        $condition = "`language_code`='$languageCode' "
            ."AND `group` IN ($subSelect)";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);
        
        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }
}