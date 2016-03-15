<?php
/**
 * Table Definition for Institutions
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
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use VuFind\Db\Table\Gateway, Zend\Config\Config;

class Institutions extends Gateway
{

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     *            
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->table = 'institutions';
        $this->rowClass = 'CPK\Db\Row\Institutions';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Creates institutions Row
     *
     * @param unknown $type            
     * @param unknown $source            
     * @param unknown $entity_id            
     * @param unknown $name_cs            
     * @param unknown $name_en            
     * @param unknown $url            
     * @param unknown $timeout            
     * @param unknown $bot_username            
     * @param unknown $bot_password            
     * @param unknown $logo_url            
     *
     * @throws \Exception
     * @return \CPK\Db\Row\Institutions
     */
    public function createInstitutionsRow($type, $source, $entity_id, $name_cs, $name_en, $url = null, $timeout = null, $bot_username = null, $bot_password = null, $logo_url = null)
    {
        $errors = [];
        
        // Check type is one of the enumerated
        $allowedTypes = [
            'Aleph',
            'NCIP'
        ];
        
        if (array_search($type, $allowedTypes, true) === false) {
            array_push($errors, 'Institution provided has not allowed type');
        }
        
        // Check all must-be-unique columns are really unique
        $mustBeUniqueColumnValue = [
            'source',
            'entity_id',
            'name_cs',
            'name_en'
        ];
        
        foreach ($mustBeUniqueColumnValue as $columnName) {
            
            $row = $this->select([
                $columnName => ${$columnName}
            ])->current();
            
            if (! empty($row)) {
                array_push($errors, 'Institution with this ' . $columnName . ' already exists');
            }
        }
        
        // Throw all errors at a time if any ..
        if (! empty($errors)) {
            
            $message = '';
            
            foreach ($errors as $error) {
                $message .= $error . '\n';
            }
            
            throw new \Exception($message);
        }
        
        $row = $this->createRow();
        
        // Save columns which are definitely not empty
        foreach ($mustBeUniqueColumnValue as $columnName) {
            $row->$columnName = ${$columnName};
        }
        
        // Now save column that may be empty
        $canBeEmptyColumns = [
            'url',
            'timeout',
            'bot_username',
            'bot_password',
            'logo_url'
        ];
        
        foreach ($canBeEmptyColumns as $columnName) {
            $what = ${$columnName};
            if (! empty(${$columnName}))
                $row->$columnName = ${$columnName};
        }
        
        $row->save();
        
        return $row;
    }

    /**
     * Retrieves Institutions which are libraries capable of login to.
     *
     * @return array
     */
    public function getLibraries()
    {
        return $this->select([
            'type' => [
                'NCIP',
                'Aleph'
            ]
        ])->toArray();
    }

    /**
     * Retrieves Institutions which are third party non-library.
     *
     * @return array
     */
    public function getOthers()
    {
        return $this->select([
            'type' => 'IdP'
        ])->toArray();
    }
}