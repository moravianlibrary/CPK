<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;
use MZKPortal;

class SolrMarcMend extends SolrMarcBase
{
    public function getInstitutionsWithIds() {
        
    }

    protected function getExternalID() {
        return substr($this->getLocalId(), 5);
    }
    
    /**
     * @return array of holdings from field 980
     */
    public function getHoldings($selectedFilters = array(), $field = '980') {
        $result = array();

        $fieldName = 'holdings' . $field . '_str_mv';
        if (!isset($this->fields[$fieldName])) {
            return $result;
        }
        
        foreach ($this->fields[$fieldName] as $currentField) {
            $currentHolding = array();
            foreach (explode('$', $currentField) as $currentSubfield) {
                $currentHolding[substr($currentSubfield, 0, 1)] = substr($currentSubfield, 1);
            }
    
            //filter 980e
            if (array_key_exists('e', $currentHolding)) {
                continue;
            }
            
            if (count($currentHolding) > 0 && $this->matchFilters($currentHolding, $selectedFilters)) {
                $result[] = $currentHolding;
            }
        }
    
    
        return $result;
    }
    
    public function getHoldingFilters() {
        $result = array();
        $result['year'] = array();
        foreach ($this->getHoldings() as $holding) {
            $year = self::getHoldingYear($holding);
            if ($year) {
                $result['year'][] = $year;
            }
        }
        $result['year'] = array_unique($result['year'], SORT_NUMERIC);
        usort($result['year'], function ($a, $b) { return $a > $b ? -1 : ($a == $b ? 0 : 1); });
        return $result;
    }
    
    /**
     * decide whether holding match filter
     * @param unknown $selectedFilters
     */
    protected function matchFilters(&$holding, &$filters) {
        if (array_key_exists('year', $filters)) {
            $year = self::getHoldingYear($holding);
            if ($year != null) {
                return $year === (int)$filters['year'];
            }
        }
        return true;
    }
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    public static function getHoldingYear(&$holding) {
        return \MZKPortal\RecordDriver\SolrMarcMend::getSheduleOfPeriodics($holding); 
    }
    
    public static function getSheduleOfPeriodics($holding) {
        if (isset($holding['%'])) {
            return $holding['%'];
        }
        if (isset($holding['c']) && preg_match('/\/\d\d\d\d/', $holding['c'], $matches)) {
            return (int)substr($matches[0], 1, 4);
        }
        return null;
    }
    
    /**
     * converts holding to displayeble array
     * @param array holding
     * @param callback for getScheduleOfPeriodics
     * @return array
     */
    public static function unifyHolding($holding) {
        $holding_entry = array();
        $holding_entry['library'] = isset($holding['@']) ? $holding['@'] : '';
        $holding_entry['branch'] = isset($holding['l']) ? $holding['l'] : '';
        $holding_entry['branch2'] = isset($holding['r']) ? $holding['r'] : '';
        $holding_entry['sheduleOfPeriodics'] = \MZKPortal\RecordDriver\SolrMarcMend::getSheduleOfPeriodics($holding);
        $holding_entry['signature1'] = isset($holding['g']) ? $holding['g'] : '';
        $holding_entry['signature2'] = isset($holding['h']) ? $holding['h'] : '';
        $holding_entry['barcode'] = isset($holding['b']) ? $holding['b'] : '';
        $holding_entry['status'] = isset($holding['k']) ? $holding['k'] : '';
    
        return $holding_entry;
    }
}

