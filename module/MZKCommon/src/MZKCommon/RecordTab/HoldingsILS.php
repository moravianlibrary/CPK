<?php
/**
 * Holdings (ILS) tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace MZKCommon\RecordTab;

/**
 * Holdings (ILS) tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class HoldingsILS extends \VuFind\RecordTab\HoldingsILS
{
    
    protected $availableFilters;
    
    protected $filters;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection|bool $catalog ILS connection to use to check
     * for holdings before displaying the tab; set to false if no check is needed
     */
    public function __construct($catalog)
    {
        parent::__construct($catalog);
    }
    
    public function getFilters()
    {
        if (!isset($this->filters)) {
            $this->filters = $this->driver->getHoldingFilters();
        }
        return $this->filters;
    }
    
    public function getSelectedFilters()
    {
        $filters = array();
        foreach ($this->getAvailableFilters() as $name => $type) {
            $filterValue = $this->getRequest()->getQuery($name);
            if ($filterValue != null || !empty($filterValue)) {
                $filters[$name] = $filterValue;
            }
        }
        if (empty($filters) && $this->driver->getNumberOfHoldings() > 30) {
            $fields = $this->getAvailableFilters();
            $available = $this->getFilters();
            foreach ($fields as $name => $config) {
                if (isset($available[$name])) {
                    $filters[$name] = $available[$name][0];
                    break;
                }
            }
        }
        return $filters;
    }
    
    public function getAvailableFilters()
    {
        if (!isset($this->availableFilters)) {
            $this->availableFilters = $this->driver->getAvailableHoldingFilters();
        }
        return $this->availableFilters;
    }
    
    public function getRealTimeHoldings()
    {
        return $this->driver->getRealTimeHoldings($this->getSelectedFilters());
    }
    
    public function asHiddenFields($field)
    {
        $allFilters = $this->getAvailableFilters();
        $filtersToKeep = isset($allFilters[$field]['keep']) ? $allFilters[$field]['keep'] : array();
        $selectedFilters = $this->getSelectedFilters();
        $result = '';
        foreach ($filtersToKeep as $filterToKeep) {
            if (isset($selectedFilters[$filterToKeep])) {
                $value = $selectedFilters[$filterToKeep];
                $result .= '<input type="hidden" name="' .
                    htmlspecialchars($filterToKeep) . '" value="' .
                    htmlspecialchars($value) . '" />';
            }
        }
        return $result;
    }

}