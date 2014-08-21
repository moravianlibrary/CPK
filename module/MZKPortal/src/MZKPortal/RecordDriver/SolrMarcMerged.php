<?php
namespace MZKPortal\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarcMerged as ParentSolr;
use MZKPortal;

class SolrMarcMerged extends ParentSolr
{

    /**
    * uses setting from config.ini => External links
    * @return array  [] => [
    *          [institution] = institution, 
    *          [url] = external link to catalogue,
    *          [display] => link to be possibly displayed]
    *          [id] => local identifier of record
    *
    */
    public function getExternalLinks() {
        $resultArray = array();
        foreach ($this->getMergedIds() as $currentId) {
            list($ins, $id) = explode('.', $currentId);
            switch ($ins) {
                case 'mzk':
                    $finalID = $id;
                    break;
                case 'muni':
                    $finalID = $id;
                    break;
                case 'kjm':
                    $finalID = substr($id, 6);   
                    break;
                case 'mend':
                    $finalID = substr($id, 5);
                    break;
            }
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase)) {
                $resultArray[] = array('institution' => $ins, 'url' => '', 'display' => '', 'id' => $currentId);
                continue;
            }
            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';
            $externalLink = $linkBase . $finalID . $linkEnd;
            $resultArray[] = array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id);
        }
        return $resultArray;
    }
    
    /**
     * get holdings from merged record
     * @param unknown $selectedFilters
     * @return 
     */
    public function getHoldings($selectedFilters = array(), $field = '996') {
        $result = array();
        $fieldName = 'holdings' . $field .'_str_mv';
        if (!isset($this->fields[$fieldName])) {
            return $result;
        }
        foreach ($this->fields[$fieldName] as $currentField) {
            $currentHolding = array();
            foreach (explode('$', $currentField) as $currentSubfield) {
                $currentHolding[substr($currentSubfield, 0, 1)] = substr($currentSubfield, 1);
            }
            
            if ($fieldName == 'holdings980_str_mv') {
                //filter 980e with numbers
                if (array_key_exists('e', $currentHolding)) {
                    if (is_numeric($currentHolding['e'])) {
                        continue;
                    }
                }
            }
            
            if ($fieldName == 'holdings996_str_mv') {
                if (array_key_exists('q', $currentHolding) && $currentHolding['q'] == "0") {
                    continue;
                }
            }
            
            if ($fieldName == 'holdings993_str_mv' && isset($currentHolding['~'])) {
                $currentHolding['~'] = preg_replace('/^\d+\s+/', '', $currentHolding['~']);
            }
            
            if (count($currentHolding) > 0 && $this->matchFilters($currentHolding, $selectedFilters)) {
                $result[] = $currentHolding;
            }
        }
        
        return $result;
    }
    
    public function getAllHoldings($filters = array()) {
        $result = array();
        $result = array_merge($result, $this->getHoldings($filters, '996'));
        $result = array_merge($result, $this->getHoldings($filters, '993'));
        $result = array_merge($result, $this->getHoldings($filters, '980'));
        return $result;
    }
    
    public function getAvailableHoldingFilters()
    {
        $filters = array(
            'institution' => array('type' => 'select'),
        );
        if (in_array('cistbrno_periodicals', $this->fields['format'])) {
            $filters['year'] = array('type' => 'select');
        }
        return $filters;
    }
    
    public function getHoldingFilters() {
        $result = array();
        $result['year'] = array();
        $result['institution'] = array();
        foreach ($this->getAllHoldings() as $holding) {
            if (in_array('cistbrno_periodicals', $this->fields['format'])) {
                $year = $this->getHoldingYear($holding);
                if ($year ) {
                    $result['year'][] = $year;
                }
            }
            $result['institution'][] = $holding['@'];
        }
        $result['year'] = array_unique($result['year'], SORT_NUMERIC);
        $result['institution'] = array_unique($result['institution']);
        usort($result['year'], function ($a, $b) { return $a > $b ? -1 : ($a == $b ? 0 : 1); });
        sort($result['institution']);
        return $result;
    }
    
    /**
     * decide whether holding match filter
     * @param unknown $selectedFilters
     */
    protected function matchFilters(&$holding, &$filters) {
        if (array_key_exists('year', $filters)) {
            $year = $this->getHoldingYear($holding);
            return $year === (int)$filters['year'];
        }
        if (array_key_exists('institution', $filters)) {
            return $filters['institution'] == $holding['@'];
        }
        return true;
    }
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    protected function getHoldingYear($holding) {
        if (!is_array($holding) || !isset($holding['@'])) {
            return $holding;
        }
        switch ($holding['@']) {
            case 'MZK':
            case 'MUNI':
                return MZKPortal\RecordDriver\SolrMarcBase::getHoldingYear($holding);
            case 'KJM':
                return MZKPortal\RecordDriver\SolrMarcKjm::getHoldingYear($holding);
            case 'MEND':
                return MZKPortal\RecordDriver\SolrMarcMend::getHoldingYear($holding);
        }
        return null;
    }
    
    /**
     * @return array(string => int)
     */
    public function getAgregatedHoldings() {
        $result = array();
        foreach ($this->getAllHoldings() as $holding) {
            $inst = $holding['@'];
            if (!isset($result[$inst])) {
                $result[$inst] = 0;
            }
            $result[$inst]++;
        }        

        return $result;
    }
    
    /**
     * converts holding to displayeble array
     * @param array holding
     * @return array
     */
    public function unifyHolding($holding) {
        if (!is_array($holding) || !isset($holding['@'])) {
            return $holding;
        }
        switch ($holding['@']) {
            case 'MZK':
            case 'MUNI':
                return MZKPortal\RecordDriver\SolrMarcBase::unifyHolding($holding);
            case 'KJM':
                return MZKPortal\RecordDriver\SolrMarcKjm::unifyHolding($holding);
            case 'MEND':
                return MZKPortal\RecordDriver\SolrMarcMend::unifyHolding($holding);
        }
    }

}
