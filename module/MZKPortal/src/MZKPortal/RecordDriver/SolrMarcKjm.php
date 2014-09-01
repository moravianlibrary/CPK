<?php
namespace MZKPortal\RecordDriver;

class SolrMarcKjm extends SolrMarcBase
{

    protected function getExternalID ()
    {
        $localId = $this->getLocalId();
        $id = substr($localId, 6);
        return ltrim($id, '0');
    }
    
    /**
     * get holdings from field 993
     */
    public function getHoldings($selectedFilters = array(), $field = '993') 
    {
        $result = array();
        foreach (parent::getHoldings($selectedFilters, $field) as $holding) {
            //fix format of location
            $holding['~'] = preg_replace('/^\d+\s+/', '', $holding['~']);
            $result[] = $holding;
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
        //field 993
        if (isset($holding['r']) && preg_match('/\d\d\d\d/', $holding['r'], $matches)) {
            if (is_array($matches) && count($matches) == 1) {
                return (int) $matches[0];
            }
        }
        return null;
    }
    
    public static function getSheduleOfPeriodics($holding) {
        $result = '';
        if (isset($holding['r'])) {
            $result .= $holding['r'];
        }
        if (isset($holding['e'])) {
            $result .= " " . $holding['e'];
        }
        if (isset($holding['w'])) {
            $result .= " " . $holding['w'];
        }
        return $result;
    }
    
    /**
     * converts holding to displayeble array
     * @param array holding
     * @return array
     */
    public static function unifyHolding($holding) {
        $holding_entry = array();
        $holding_entry['library'] = isset($holding['@']) ? $holding['@'] : '';
        $holding_entry['branch'] = isset($holding['~']) ? $holding['~'] : '';
        $holding_entry['branch2'] = '';
        $holding_entry['sheduleOfPeriodics'] = \MZKPortal\RecordDriver\SolrMarcKjm::getSheduleOfPeriodics($holding);
        $holding_entry['signature1'] = isset($holding['k']) ? $holding['k'] : '';
        $holding_entry['signature2'] = isset($holding['g']) ? $holding['g'] : '';
        $holding_entry['barcode'] = isset($holding['b']) ? $holding['b'] : '';
        $holding_entry['status'] = '';
    
        return $holding_entry;
    }
}