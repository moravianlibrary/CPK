<?php

/**
 * Search service.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace MZKCommon\VuFindSearch;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Backend\Exception\BackendException;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;

/**
 * Search service.
 *
 * @category VuFind2
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Service extends \VuFindSearch\Service
{
    
    private $defaultSort = null;
    
    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * 
     */
    public function __construct($searchSettings = null) {
        parent::__construct();
        print "OK<BR>";
        if (isset($searchSettings->General->default_empty_sort)) {
            $this->defaultSort = $searchSettings->General->default_empty_sort;
        }
    }

    public function search($backend, $query, $offset = 0,
        $limit = 20, $params = null
    ) {
        if ($this->defaultSort != null && trim($query->getString()) == '' 
            && $params->get('sort')[0] == 'score desc') {
            $params->set('sort', $this->defaultSort);
        }
        return parent::search($backend, $query, $offset, $limit, $params);
    }

}
