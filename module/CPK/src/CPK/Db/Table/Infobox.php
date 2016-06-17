<?php
/**
 * Table Definition for Infobox
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

use VuFind\Db\Table\Gateway,
    Zend\Config\Config,
    Zend\Db\Sql\Update,
    Zend\Db\Sql\Insert,
    Zend\Db\Sql\Delete,
    Zend\Db\Sql\Select,
    Zend\Db\Sql\Expression;

/**
 * Table Definition for Infobox
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class Infobox extends Gateway
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
        parent::__construct('infobox', 'CPK\Db\Row\Infobox');
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
     * Executes any Insert
     *
     * @param Zend\Db\Sql\Insert $insert
     *
     * @return Zend\Db\Adapter\Driver\StatementInterface
     */
    protected function executeAnyZendSQLInsert(Insert $insert)
    {
        $statement = $this->sql->prepareStatementForSqlObject($insert);
        return $statement->execute();
    }

    /**
     * Executes any Delete
     *
     * @param Zend\Db\Sql\Delete $delete
     *
     * @return Zend\Db\Adapter\Driver\StatementInterface
     */
    protected function executeAnyZendSQLDelete(Delete $delete)
    {
        $statement = $this->sql->prepareStatementForSqlObject($delete);
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
     * Returns all infobox items
     *
     * @return array
     */
    public function getItems()
    {
        $select = new Select($this->table);

        $results= $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }

    /**
     * Return row from table
     *
     * @param int $id
     *
     * @return array
     */
    public function getItem($id)
    {
        $select = new Select($this->table);
        $condition = "`id` = '$id'";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }

    /**
     * Returns actual infobox items
     *
     * @param   int|false   $randomLimit
     *
     * @return array
     */
    public function getActualItems($randomLimit = false)
    {
        $select = new Select($this->table);

        $condition = "NOW() BETWEEN date_from AND date_to";

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        if ($randomLimit) {
            $select->limit($randomLimit);
        }

        $results = $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }

    /**
     * Insert a new row to table
     *
     * @param array $data
     *
     * @return void
     */
    public function addItem(array $data)
    {
        $insert = new Insert($this->table);

        $insert->values([
            'title_cs' => $data['title_cs'],
            'title_en' => $data['title_en'],
            'text_cs' => $data['text_cs'],
            'text_en' => $data['text_en'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to']
        ]);

        $this->executeAnyZendSQLInsert($insert);
    }

    /**
     * Save edited row to table by id
     *
     * @param array $data
     *
     * @return void
     */
    public function saveItem(array $data)
    {
        $update = new Update($this->table);

        $update->set([
            'title_cs' => $data['title_cs'],
            'title_en' => $data['title_en'],
            'text_cs' => $data['text_cs'],
            'text_en' => $data['text_en'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to']
        ]);
        $update->where([
            'id' => $data['id']
        ]);

        $this->executeAnyZendSQLUpdate($update);
    }

    /**
     * Remove row from table by id
     *
     * @param int $id
     *
     * @return array
     */
    public function removeItem($id)
    {
        $update = new Delete($this->table);

        $update->where([
            'id' => $id
        ]);

        $this->executeAnyZendSQLDelete($update);
    }
}