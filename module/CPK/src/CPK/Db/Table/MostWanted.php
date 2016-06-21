<?php
/**
 * Table Definition for MostWanted
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
 * Table Definition for MostWanted
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class MostWanted extends Gateway
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
        parent::__construct('most_wanted', 'CPK\Db\Row\MostWanted');
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
     * Returns array of MostWanted records from table
     *
     * @param   int|false   $randomLimit
     *
     * @return array
     */
    public function getRecords($randomLimit = false)
    {
        $select = new Select($this->table);

        if ($randomLimit) {
            $select->limit($randomLimit);
        }

        $select->order(new Expression('RAND()'));

        $results= $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }

    /**
     * Replace table content with new records
     *
     * @param array $records
     *
     * @return void
     */
    public function saveRecords($records)
    {
        /* Truncate table */
        $delete = new Delete($this->table);

        $delete->where([
            "1=1"
        ]);

        $this->executeAnyZendSQLDelete($delete);

        /* Insert new records */
        $arrayOfValues = [];

        foreach($records as $record) {
            $title = $record->getTitle();

            $authors = $record->getDeduplicatedAuthors();
            $author = $authors['main'];

            $recordId = $record->getUniqueID();

            $arrayOfValues = [
                'record_id' => $recordId,
                'title' => $title,
                'author' => $author
            ];

            /* @FIXME: Rewrite this to INSERT all rows at once */
            $insert = new Insert($this->table);
            $insert->values($arrayOfValues);
            $this->executeAnyZendSQLInsert($insert);
        }
    }
}