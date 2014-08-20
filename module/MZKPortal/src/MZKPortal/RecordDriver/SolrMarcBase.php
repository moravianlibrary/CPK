<?php
namespace MZKPortal\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarc;

class SolrMarcBase extends SolrMarc
{

    public function getInstitutionsWithIds() {
        $id = $this->getUniqueID();
        list($source, $localId) = explode('.', $this->getUniqueID());
        return array($source => $id);
    }

    public function getHoldLink() {
        return null;
    }
    
    /**
     * @return array of holdings from field 996
     */
    public function getHoldings($selectedFilters = array(), $field = '996') {
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

            if (array_key_exists('q', $currentField) && $currentField['q'] == "0") {
                continue;
            }
            
            if (count($currentHolding) > 0 && $this->matchFilters($currentHolding, $selectedFilters)) {
                $result[] = $currentHolding;
            }
        }
        return $result;
    }

    public function getAvailableHoldingFilters()
    {
        return array(
                'year' => array('type' => 'select'),
        );
    }
    
    public function getHoldingFilters() {
        $result = array();
        $result['year'] = array();
        foreach ($this->getHoldings() as $holding) {
            $year = $this->getHoldingYear($holding);
            if ($year) {
                $result['year'][] = $year;
            }
        }
        $result['year'] = array_unique($result['year'], SORT_NUMERIC);
        sort($result['year'], SORT_NUMERIC);
        return $result;
    }
    
    /**
     * decide whether holding match filter
     * @param unknown $selectedFilters
     */
    protected function matchFilters(&$holding, &$filters) {
        if (array_key_exists('year', $filters)) {
            $year = $this->getHoldingYear($holding);
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
    protected function getHoldingYear(&$holding) {
        //field 996
        if (isset($holding['d']) && preg_match('/^\d\d\d\d.*/', $holding['d'])) {
            return (int)substr($holding['y'], 0 ,4);
        }
        return null;
    }
    
    function getSheduleOfPeriodics($holding) {
        if (isset($holding['d'])) {
            return $holding['d'];
        }
        $result = '';
        if (isset($holding['y'])) {
            $result .= $holding['y'];
        }
        if (isset($holding['v']) && $holding['v'] != $holding['y']) {
            $result .= " " . $holding['v'];
        }
        if (isset($holding['i'])) {
            $result .= " " . $holding['i'];
        }
        return $result;
    }
    
    /**
     * @return array(string => int)
     */
    public function getAgregatedHoldings() {
        $result = array();
        foreach ($this->getHoldings() as $holding) {
            $inst = $holding['@'];
            if (!isset($result[$inst])) {
                $result[$inst] = 0;
            }
            $result[$inst]++;
        }        

        return $result;
    }
}
