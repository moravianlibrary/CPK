<?php
/**
 * Table Definition for LibrariesGeolocations
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
 * Table Definition for LibrariesGeolocations
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class LibrariesGeolocations extends Gateway
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
        parent::__construct('libraries_geolocations', 'CPK\Db\Row\LibrariesGeolocations');
    }

    /**
     * Replace table content with new libraries data
     *
     * @param array $data
     *
     * @return void
     */
    public function saveGeoData($data)
    {
        /* Truncate table */
        $delete = new Delete($this->table);

        $delete->where([
            "1=1"
        ]);

        $this->executeAnyZendSQLDelete($delete);

        foreach($data as $row) {
            /* @FIXME: Rewrite this to INSERT all rows at once */
            $insert = new Insert($this->table);
            $insert->values($row);
            $this->executeAnyZendSQLInsert($insert);
        }
    }

    /**
     * Return towns from table by Region
     *
     * @todo For better performance, WHERE only institutions connected to portal. So first of all,
     * add column to table and store there some information, in harvesting
     * method AJAX->createLirariesGeolocationsTableAjax
     *
     * @param string $region
     *
     * @return array
     */
    public function getTownsByRegion($region)
    {
        $select = new Select($this->table);
        $select->columns(array('town' => new Expression('DISTINCT(town)')));

        $region = str_replace(" kraj", '', $region);

        $condition = "`region`='$region'";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $results = $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }
}