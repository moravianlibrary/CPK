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
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    protected function getHoldingYear(&$holding) {
        //field 993
        if (isset($holding['r']) && preg_match('/\d\d\d\d/', $holding['r'], $matches)) {
            if (is_array($matches) && count($matches) == 1) {
                return (int) $matches[0];
            }
        }
        return null;
    }
    
    function getSheduleOfPeriodics($holding) {
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