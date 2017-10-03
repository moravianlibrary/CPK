<?php
/**
 * Table Definition for Widget
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
    Zend\Db\Adapter\Driver\ResultInterface,
    Zend\Db\ResultSet\HydratingResultSet,
    Zend\Stdlib\Hydrator\ObjectProperty,
    CPK\Widgets\Widget as WidgetModel,
    CPK\Widgets\InspirationWidget as InspirationWidgetModel;

/**
 * Table Definition for Widget
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class Widget extends Gateway
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
        parent::__construct('widget', 'CPK\Db\Row\Widget');
    }

    /**
     * Returns array of Objects representing Widgets
     *
     * @param bool $withInspirationsPositions This will return extended Widget
     *
     * @return array
     */
    public function getWidgets($withInspirationsPositions = false) : array
    {
        $select = new Select($this->table);
//dd($withInspirationsPositions);
        if ($withInspirationsPositions) {
            $select->join(
                ['i' => 'inspirations'],
                'i.widget_id = '.$this->table.'.id',
                ['widget_position'],
                'left'
            );
            $select->order('widget_position DESC');
        }

        $results= $this->executeAnyZendSQLSelect($select);

        $widgets = [];
        if ($results instanceof ResultInterface && $results->isQueryResult()) {
            $resultSet = new HydratingResultSet(
                new ObjectProperty,
                (($withInspirationsPositions) ? new \CPK\Widgets\InspirationWidget() : new \CPK\Widgets\Widget())
            );
            $resultSet->initialize($results);

            foreach ($resultSet as $object) {
                array_push($widgets, $object);
            }
        }

        return $widgets;
    }

    /**
     * Returns widget
     *
     * @param   string  $name
     *
     * @return array
     */
    public function getWidgetByName($name)
    {
        $select = new Select($this->table);

        $condition = "`name`='$name'";

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $results= $this->executeAnyZendSQLSelect($select);
        $widgets = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\Widget'
        );

        return isset($widgets[0]) ? $widgets[0] : false;
    }

    /**
     * Returns widget
     *
     * @param   int  $id
     *
     * @return array
     */
    public function getWidgetById($id)
    {
        $select = new Select($this->table);

        $condition = "`id`='$id'";

        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $results= $this->executeAnyZendSQLSelect($select);
        $widgets = $this->resultsToArrayOfSpecifiObjects(
            $results,
            '\CPK\Widgets\Widget'
        );

        return $widgets[0];
    }

    /**
     * Insert a new row to table
     *
     * @param   \CPK\Widgets\Widget  $widget
     *
     * @return void
     */
    public function addWidget(\CPK\Widgets\Widget $widget)
    {
        $insert = new Insert($this->table);

        $insert->values([
            'name' => $widget->getName(),
            'display' => $widget->getDisplay(),
            'title_cs' => $widget->getTitleCs(),
            'title_en' => $widget->getTitleEn(),
            'show_all_records_link' => $widget->getShowAllRecordsLink(),
            'shown_records_number' => $widget->getShownRecordsNumber(),
            'show_cover' => $widget->getShowCover(),
            'description' => $widget->getDescription()
        ]);

        $this->executeAnyZendSQLInsert($insert);
    }

    /**
     * Save edited row to table by id
     *
     * @param   \CPK\Widgets\Widget  $widget
     *
     * @return void
     */
    public function saveWidget(\CPK\Widgets\Widget $widget)
    {
        $update = new Update($this->table);

        $update->set([
            'name' => $widget->getName(),
            'display' => $widget->getDisplay(),
            'title_cs' => $widget->getTitleCs(),
            'title_en' => $widget->getTitleEn(),
            'show_all_records_link' => $widget->getShowAllRecordsLink(),
            'shown_records_number' => $widget->getShownRecordsNumber(),
            'show_cover' => $widget->getShowCover(),
            'description' => $widget->getDescription()
        ]);
        $update->where([
            'id' => $widget->getId()
        ]);

        $this->executeAnyZendSQLUpdate($update);
    }

    /**
     * Remove row from table
     *
     * @param   \CPK\Widgets\Widget  $widget
     *
     * @return void
     */
    public function removeWidget(\CPK\Widgets\Widget $widget)
    {
        $delete = new Delete($this->table);

        $delete->where([
            'id' => $widget->getId()
        ]);

        $this->executeAnyZendSQLDelete($delete);
    }
}
