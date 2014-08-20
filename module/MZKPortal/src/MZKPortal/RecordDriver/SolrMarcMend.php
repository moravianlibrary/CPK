<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

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
    
            //filter 980e with numbers
            if (array_key_exists('e', $currentHolding)) {
                if (is_numeric($currentHolding['e'])) {
                    continue;
                }
            }
            
            if (count($currentHolding) > 0 && $this->matchFilters($currentHolding, $selectedFilters)) {
                $result[] = $currentHolding;
            }
        }
    
    
        return $result;
    }
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    protected function getHoldingYear(&$holding) {
        //field 993
        if (isset($holding['d']) && preg_match('/\d\d\d\d/', $holding['d'], $matches)) {
            if (is_array($matches) && count($matches) == 1) {
                return (int) $matches[0];
            }
        }
        return null;
    }
}

