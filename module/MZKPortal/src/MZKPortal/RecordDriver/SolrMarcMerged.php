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
            $descripion = $this->getHoldingDescription($id);
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase)) {
                $resultArray[] = array('institution' => $ins, 'url' => '', 'display' => '', 'id' => $currentId, 'description' => $descripion);
                continue;
            }
            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';
            $externalLink = $linkBase . $finalID . $linkEnd;
            $resultArray[] = array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id, 'description' => $descripion);
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
//         if (!empty($filters && $filters['institution'] == 'KJM')) {
//             throw new \Exception(print_r($filters, true));
//         }
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
        if (array_key_exists('id', $filters)) {
            return $filters['id'] == $holding['*'];
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
            $idPart = $holding['*'];
            $id = $this->getFullId($idPart);
            if (!empty($id)) {
                if (!isset($result[$id])) {
                    $result[$id] = 0;
                }
                $result[$id]++;
            }
        }        

        return $result;
    }
    
    public function getHoldingDescription($id) {
        $holdings = $this->getAllHoldings(array('id' => $id));
        if (is_array($holdings) && count($holdings) > 0) {
            if (isset($holdings[0]['%'])) {
                return $holdings[0]['%'];
            }
        }
        return '';
    }
    
    /**
     * finds corresponding id for sysno
     * @param string $sysno
     */
    protected function getFullId($sysno) {
        foreach ($this->fields['local_ids_str_mv'] as $localId) {
            if (preg_match('/'. $sysno .'/', $localId)) {
                return $localId;
            }
        }
        return '';
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

    
    public function getNumberOfHoldings() {
        if (!isset($this->numberOfHoldings)) {
            $this->numberOfHoldings = count($this->getAllHoldings(array()));
        }
        return $this->numberOfHoldings;
    }
    
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->highlightDetails['title_portaly_txtP'][0]))
            ? $this->highlightDetails['title_portaly_txtP'][0] : '';
    }
    
    public function getTitle() {
        return empty($this->fields['title_portaly_txtP']) ? parent::getTitle() : $this->fields['title_portaly_txtP'];
    }
}
