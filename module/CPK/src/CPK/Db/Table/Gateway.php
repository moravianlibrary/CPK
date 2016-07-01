<?php
/**
 * Generic VuFind table gateway.
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
*  @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;
use VuFind\Db\Table\Gateway as ParentGateway,
    Zend\Db\Sql\Select,
    Zend\Db\Sql\Update,
    Zend\Db\Sql\Delete,
    Zend\Db\Sql\Insert,
    Zend\Db\Sql\Expression;

/**
 * Generic VuFind table gateway.
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class Gateway extends ParentGateway
{
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
     * @param Zend\Db\Sql\Select $select
     *
     * @return Zend\Db\Adapter\Driver\ResultInterface $result
     */
    protected function executeAnyZendSQLSelect(\Zend\Db\Sql\Select $select)
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
    protected function executeAnyZendSQLUpdate(\Zend\Db\Sql\Update $update)
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
    protected function executeAnyZendSQLInsert(\Zend\Db\Sql\Insert $insert)
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
    protected function executeAnyZendSQLDelete(\Zend\Db\Sql\Delete $delete)
    {
        $statement = $this->sql->prepareStatementForSqlObject($delete);
        return $statement->execute();
    }

    /**
     * Return array of specific objects created from DB results
     *
     * @param   Zend\Db\Adapter\Driver\Mysqli\Result    $results
     * @param   string                                  $class  E.g.: '\CPK\Widgets\Widget'
     *
     * @return  array
     */
    protected function resultsToArrayOfSpecifiObjects(
        \Zend\Db\Adapter\Driver\Mysqli\Result $results,
        $class
        ) {
        $resultSet = new \Zend\Db\ResultSet\HydratingResultSet(
            new \Zend\Stdlib\Hydrator\Reflection,
            new $class
            );
        $resultSet->initialize($results);

        $array = [];
        foreach($resultSet as $object) {
            $array[] = $object;
        }

        return $array;
    }

    /**
     * Checks if table already contains data going to be inserted.
     *
     * @param   Zend\Db\Sql\Insert | Zend\Db\Sql\Update   $row
     *
     * @return  boolean
     */
    protected function rowExist($row) {
        $state = $row->getRawState();
        $selecet = new Select($state['table']);
        if ($row instanceof \Zend\Db\Sql\Update) {
            $selecet->where($state['set']);
        }
        else {
            $selecet->where(array_combine($state['columns'], $state['values']));
        }
        $check = $this->executeSelect($selecet);
        return (count($check->toArray()) > 0) ? true : false;
    }

}
