<?php
/**
 * Portal pages view helper
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
 * @package  View_Helpers
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\View\Helper\CPK;

use CPK\Db\Table\PortalPage as PortalPageTable;

/**
 * Portal pages view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class PortalPages extends \Zend\View\Helper\AbstractHelper
{
    /**
     * @var CPK\Db\Table\PortalPage
     * 
     */
    protected $portalPageTable;
    
    /**
     * @param CPK\Db\Table\PortalPage $portalPageTable
     * @param string $languageCode
     */
    public function __construct(PortalPageTable $portalPageTable, $languageCode)
    {
        $this->portalPageTable = $portalPageTable;
        $this->languageCode = $languageCode;
    }
    
    /**
     * Get the specified page
     * 
     * @param unknown $prettyUrl
     */
    public function getPage($prettyUrl)
    {
        return $this->portalPageTable->getPage($prettyUrl);
    }
    
    /**
     * Get all pages
     */
    public function getAllPages()
    {
        return $this->portalPageTable->getAllPages($this->languageCode);
    }
}