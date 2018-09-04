<?php
/**
 * Table Definition for WidgetContent
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
    Zend\Db\Sql\Select,
    Zend\Db\Sql\Insert,
    Zend\Db\Sql\Update,
    Zend\Db\Sql\Delete,
    Zend\Db\Sql\Expression;

/**
 * Table Definition for WidgetContent
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class WidgetContent extends Gateway
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
        parent::__construct('widget_content', 'CPK\Db\Row\WidgetContent');
    }

    /**
     * Returns rows for specific widget
     *
     * @param   int $limit
     * @param   int     $limit
     * @param   boolean $prefferedValues
     *
     * @return  array
     */
    public function getContentsByWidgetId($widgetId, $limit = false, $prefferedValues = false)
    {
        $select = new Select($this->table);

        $condition = "widget_id='$widgetId'";

        if ($prefferedValues) {
            $condition .= ' AND `preferred_value`=1';
        }

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        if ($limit) {
            $select->limit($limit);
        }

        $results= $this->executeAnyZendSQLSelect($select);

        $contents = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\WidgetContent'
        );

        return $contents;
    }

    /**
     * Returns rows for specific widget
     *
     * @param   string  $name    E.g. 'most_wanted'
     * @param   int     $limit
     * @param   boolean $prefferedValues
     *
     * @return  array
     */
    public function getContentsByName($name, $limit = false, $prefferedValues = false, $preferredFirst = false)
    {
        $select = new Select($this->table);

        $condition = "name='$name'";
        $subSelect = "SELECT `id` FROM `widget` WHERE `name`='$name'";
        $condition = "`widget_id`=($subSelect)";

        if ($prefferedValues) {
            $condition .= ' AND `preferred_value`=1';
        }

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        if ($preferredFirst) {
            $select->order('preferred_value DESC');
        } else {
            $select->order(array(new Expression('RAND()')));
        }

        if ($limit) {
            $select->limit($limit);
        }

        $results= $this->executeAnyZendSQLSelect($select);

        $contents = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\WidgetContent'
            );

        return $contents;
    }

    /**
     * Insert a new row to table
     *
     * @param   \CPK\Widgets\WidgetContent  $widgetContent
     *
     * @return void
     */
    public function addWidgetContent(\CPK\Widgets\WidgetContent $widgetContent)
    {
        $insert = new Insert($this->table);

        $insert->values([
            'widget_id' => $widgetContent->getWidgetId(),
            'value' => $widgetContent->getValue(),
            'preferred_value' => $widgetContent->getPreferredValue(),
            'description_cs' => $widgetContent->getDescriptionCs(),
            'description_en' => $widgetContent->getDescriptionEn()
        ]);

        if (! $this->rowExist($insert)) {
            $this->executeAnyZendSQLInsert($insert);
        }
    }

    /**
     * Save edited row to table by id
     *
     * @param   \CPK\Widgets\WidgetContent  $widgetContent
     *
     * @return void
     */
    public function saveWidgetContent(\CPK\Widgets\WidgetContent $widgetContent)
    {
        $update = new Update($this->table);

        $update->set([
            'widget_id' => $widgetContent->getWidgetId(),
            'value' => $widgetContent->getValue(),
            'preferred_value' => $widgetContent->getPreferredValue(),
            'description_cs' => $widgetContent->getDescriptionCs(),
            'description_en' => $widgetContent->getDescriptionEn()
        ]);
        $update->where([
            'id' => $widgetContent->getId()
        ]);

        if (! $this->rowExist($update)) {
            $this->executeAnyZendSQLUpdate($update);
        }
        else {
            $delete = new Delete($this->table);
            $delete->where($update->getRawState()['where']);
            $this->executeDelete($delete);
        }
    }

    /**
     * Remove row from table
     *
     * @param   \CPK\Widgets\WidgetContent  $widgetContent
     *
     * @return void
     */
    public function removeWidgetContent(\CPK\Widgets\WidgetContent $widgetContent)
    {
        $delete = new Delete($this->table);

        $delete->where([
            'id' => $widgetContent->getId()
        ]);

        $this->executeAnyZendSQLDelete($delete);
    }

    /**
     * Truncate widget content
     *
     * @param \CPK\Db\Table\Widget $widget
     *
     * @return void
     */
    public function truncateWidgetContent(\CPK\Widgets\Widget $widget)
    {
        $this->executeAnyZendSQLDelete(
            (new Delete($this->table))->where([
                'widget_id' => $widget->getId()
            ])
        );
    }

    /**
     * Returns row by Id
     *
     * @param   \CPK\Widgets\WidgetContent  $widgetContent
     *
     * @return  \CPK\Widgets\WidgetContent
     */
    public function getContentById(\CPK\Widgets\WidgetContent $widgetContent)
    {
        $select = new Select($this->table);

        $condition = "id='".$widgetContent->getId()."'";

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $results = $this->executeAnyZendSQLSelect($select);

        $contents = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\WidgetContent'
        );

        return $contents[0];
    }
}