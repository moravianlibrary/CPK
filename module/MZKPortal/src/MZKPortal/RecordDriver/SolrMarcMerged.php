<?php
namespace MZKPortal\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarcMerged as ParentSolr;

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
            
            if ($fieldName == 'holdings993_str_mv') {
                $currentHolding['~'] = preg_replace('/^\d+\s+/', '', $currentHolding['~']);
               
            }
            
            if (count($currentHolding) > 0 && $this->matchFilters($currentHolding, $selectedFilters)) {
                $result[] = $currentHolding;
            }
        }
        
        return $result;
    }
    
    public function getAllHoldings() {
        $result = array();
        $result = array_merge($result, $this->getHoldings(array(), '996'));
        $result = array_merge($result, $this->getHoldings(array(), '993'));
        $result = array_merge($result, $this->getHoldings(array(), '980'));
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
        sort($result['year'], SORT_NUMERIC);
        sort($result['institution']);
        return $result;
    }
    
    /**
     * decide whether holding match filter
     * @param unknown $selectedFilters
     */
    protected function matchFilters(&$holding, &$filters) {
        $result = true;
        if (array_key_exists('year', $filters)) {
            $year = $this->getHoldingYear($holding);
            if ($year != null) {
                return $year === (int)$filters['year'];
            }
        }
        if (array_key_exists('institution', $filters)) {
            $result = $result && $filters['institution'] == $holding['@'];
        }
        return $result;
    }
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    protected function getHoldingYear($holding) {
        if ($holding['@'] == 'MZK' || $holding['@'] == 'MUNI') {
            if (isset($holding['d']) && preg_match('/\d\d\d\d/', $holding['d'], $matches)) {
                if (is_array($matches) && count($matches) == 1) {
                    return (int) $matches[0];
                }
            }
        } elseif ($holding['@'] == 'MEND') {
            if (isset($holding['r']) && preg_match('/\d\d\d\d/', $holding['r'], $matches)) {
                if (is_array($matches) && count($matches) == 1) {
                    return (int) $matches[0];
                }
            }
        } else if ($holding['@'] == 'KJM') {
            if (isset($holding['r']) && preg_match('/\d\d\d\d/', $holding['r'], $matches)) {
                if (is_array($matches) && count($matches) == 1) {
                    return (int) $matches[0];
                }
            }
        }
        return null;
    }
    
    function getSheduleOfPeriodics($holding) {
        if ($holding['@'] == 'KJM') {
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
        } elseif ($holding['@'] == 'MZK' || $holding['@'] == 'MUNI') {
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

}
