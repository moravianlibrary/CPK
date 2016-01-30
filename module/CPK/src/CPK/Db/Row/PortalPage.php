<?php
/**
 * Row Definition for portal page
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
 * @package  Db_Row
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Row;

use VuFind\Db\Row\RowGateway,
    VuFind\Db\Table\DbTableAwareInterface,
    VuFind\Db\Table\DbTableAwareTrait;

/**
 * Row Definition for portal page
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class PortalPage extends RowGateway implements DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     * 
     * @return void
     */
    public function __construct(\Zend\Db\Adapter\Adapter $adapter)
    {
        parent::__construct('id', 'portal_pages', $adapter);
    }
    
    /**
     * Configuration setter
     *
     * @param \Zend\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function setConfig(\Zend\Config\Config $config)
    {
        $this->config = $config;
    }
}