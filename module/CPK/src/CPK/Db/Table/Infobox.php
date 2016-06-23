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

use CPK\Db\Table\Gateway,
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
     * Returns all infobox items
     *
     * @return array
     */
    public function getItems()
    {
        $select = new Select($this->table);

        $results= $this->executeAnyZendSQLSelect($select);

        $items = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\InfoboxItem'
        );

        return $items;
    }

    /**
     * Return row from table
     *
     * @param \CPK\Widgets\InfoboxItem $infoboxItem
     *
     * @return array
     */
    public function getItem(\CPK\Widgets\InfoboxItem $infoboxItem)
    {
        $select = new Select($this->table);
        $condition = "`id` = '".$infoboxItem->getId()."'";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select);

        $item = $this->resultsToArrayOfSpecifiObjects(
            $result,
            '\CPK\Widgets\InfoboxItem'
        );

        return $item[0];
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
            $select->order(new Expression('RAND()'));
        }

        $results = $this->executeAnyZendSQLSelect($select);

        $items = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\InfoboxItem'
        );

        return $items;
    }

    /**
     * Insert a new row to table
     *
     * @param \CPK\Widgets\InfoboxItem $infoboxItem
     *
     * @return void
     */
    public function addItem(\CPK\Widgets\InfoboxItem $infoboxItem)
    {
        $insert = new Insert($this->table);

        $insert->values([
            'title_cs' => $infoboxItem->getTitleCs(),
            'title_en' => $infoboxItem->getTitleEn(),
            'text_cs' => $infoboxItem->getTextCs(),
            'text_en' => $infoboxItem->getTextEn(),
            'date_from' => $infoboxItem->getDateFrom(),
            'date_to' => $infoboxItem->getDateTo()
        ]);

        $this->executeAnyZendSQLInsert($insert);
    }

    /**
     * Save edited row to table by id
     *
     * @param \CPK\Widgets\InfoboxItem $infoboxItem
     *
     * @return void
     */
    public function saveItem(\CPK\Widgets\InfoboxItem $infoboxItem)
    {
        $update = new Update($this->table);

        $update->set([
            'title_cs' => $infoboxItem->getTitleCs(),
            'title_en' => $infoboxItem->getTitleEn(),
            'text_cs' => $infoboxItem->getTextCs(),
            'text_en' => $infoboxItem->getTextEn(),
            'date_from' => $infoboxItem->getDateFrom(),
            'date_to' => $infoboxItem->getDateTo()
        ]);
        $update->where([
            'id' => $infoboxItem->getId()
        ]);

        $this->executeAnyZendSQLUpdate($update);
    }

    /**
     * Remove row from table
     *
     * @param \CPK\Widgets\InfoboxItem $infoboxItem
     *
     * @return array
     */
    public function removeItem(\CPK\Widgets\InfoboxItem $infoboxItem)
    {
        $update = new Delete($this->table);

        $update->where([
            'id' => $infoboxItem->getId()
        ]);

        $this->executeAnyZendSQLDelete($update);
    }
}