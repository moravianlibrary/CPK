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

use VuFind\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select;

class AlephConfigs extends Gateway
{

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    protected $configMappings = [
        'Catalog' => [
            'host',
            'dlfport',
            'debug',
            'default_patron',
            'send_language',
            'hmac_key',
            'bib',
            'useradm',
            'admlib',
            'wwwuser',
            'wwwpasswd',
            'available_statuses',
            'dont_show_link',
            'logo'
        ],
        'duedates' => [
            'on_site_loan',
            'reference_library',
            'in_processing',
            'absent_loan'
        ],
        'Availability' => [
            'source',
            'maxItemsParsed'
        ],
        'holdings' => [
            'default_required_date'
        ],
        'sublibadm' => []
    ];

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
        $this->table = 'aleph_configs';
        $this->rowClass = 'CPK\Db\Row\AlephConfigs';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Retrieves Institutions which are third party non-library.
     *
     * @return array
     */
    public function getConfig($source)
    {
        $alephConf = $this->getAlephConfig($source);
        
        if (! $alephConf)
            return $alephConf;
        
        $commonConfig = $this->getCommonConfig($source);
        
        $dbConfig = array_merge($commonConfig, $alephConf);
        
        $config = [];
        
        foreach ($this->configMappings as $section => $sectionElements) {
            
            $config[$section] = [];
            
            foreach ($sectionElements as $sectionElement) {
                
                if (isset($dbConfig[$sectionElement])) {
                    $config[$section][$sectionElement] = $dbConfig[$sectionElement];
                }
            }
        }
        
        return $config;
    }

    protected function getAlephConfig($source)
    {
        $alephConf = $this->select([
            'source' => $source
        ])->current();
        
        if (! $alephConf)
            return [];
        
        return $alephConf->toArray();
    }

    /**
     * Retrieves a column from institutions table
     *
     * @param string $source            
     */
    protected function getCommonConfig($source)
    {
        $select = new Select('institutions');
        
        $select->where([
            'source' => $source
        ]);
        
        return $this->sql->prepareStatementForSqlObject($select)
            ->execute()
            ->current();
    }
}
