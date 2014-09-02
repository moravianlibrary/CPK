<?php
namespace MZKPortal\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarc;

class SolrMarcBase extends SolrMarc
{

    protected $numberOfHoldings;
    
    public function getInstitutionsWithIds() {
        $id = $this->getUniqueID();
        list($source, $localId) = explode('.', $this->getUniqueID());
        return array($source => $id);
    }

    public function getHoldLink() {
        return null;
    }
    
    public function getExternalLinks($type = 'link') {
    
        list($ins, $id) = explode('.' , $this->getUniqueID());

        if (strcasecmp($type, 'holdings') === 0) {
            $linkBase = $this->recordConfig->ExternalHoldings->$ins;
        } else {
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
        }
        $descripion = $this->getHoldingDescription($id);
        if (empty($linkBase)) {
            return array(
                array('institution' => $ins,
                    'url' => '',
                    'display' => '',
                    'id' => $this->getUniqueID(),
                    'description' => $descripion
                )
            );
        }
    
        $finalID = $this->getExternalID();
        if (!isset($finalID)) {
            return array(
                array('institution' => $ins,
                    'url' => '',
                    'display' => '',
                    'id' => $this->getUniqueID(),
                    'description' => $descripion)
            );
        }
    
        $confEnd  = $ins . '_end';
        if (strcasecmp($type, 'holdings') === 0) {
            $linkEnd  = $this->recordConfig->ExternalHoldings->$confEnd;
        } else {
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        }
    
        if (!isset($linkEnd) ) $linkEnd = '';
        $externalLink =  $linkBase . $finalID . $linkEnd;
        return array(
            array('institution' => $ins,
                'url' => $externalLink,
                'display' => $externalLink,
                'id' => $id,
                'description' => $descripion
            )
        );
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

            if (array_key_exists('q', $currentHolding) && $currentHolding['q'] == "0") {
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
        if (array_key_exists('id', $filters)) {
            return $filters['id'] == $holding['*'];
        }
        return true;
    }
    
    /**
     * @param array $holding
     * @return mixed int or null
     */
    public static function getHoldingYear(&$holding) {
        //field 996
        if (isset($holding['d']) && preg_match('/^\d\d\d\d.*/', $holding['d'])) {
            return (int)substr($holding['y'], 0 ,4);
        }
        return null;
    }
    
    public static function getSheduleOfPeriodics($holding) {
        if (isset($holding['%'])) {
            return $holding['%'];
        }
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
            $id = $this->getAvailabilityID();
            if (!empty($id)) {
                if (!isset($result[$id])) {
                    $result[$id] = 0;
                }
                $result[$id]++;
            }
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
        $holding_entry['branch'] = isset($holding['l']) ? $holding['l'] : '';
        $holding_entry['branch2'] = isset($holding['r']) ? $holding['r'] : '';
        $holding_entry['sheduleOfPeriodics'] = \MZKPortal\RecordDriver\SolrMarcBase::getSheduleOfPeriodics($holding);
        $holding_entry['signature1'] = isset($holding['c']) ? $holding['c'] : '';
        $holding_entry['signature2'] = isset($holding['h']) ? $holding['h'] : '';
        $holding_entry['barcode'] = isset($holding['b']) ? $holding['b'] : '';
        $holding_entry['status'] = '';
        if (isset($holding['s'])) {
            if ($holding['s'] == 'A') {
                $holding_entry['status'] = 'Holding Status Absent';
            } elseif ($holding['s'] == 'P') {
                $holding_entry['status'] = 'Holding Status Absent';
            }
        }
        
        return $holding_entry;
    }
    
    public function getNumberOfHoldings() {
        if (!isset($this->numberOfHoldings)) {
            $this->numberOfHoldings = count($this->getHoldings());
        }
        return $this->numberOfHoldings;
    }
    
    public function getHoldingDescription($id) {
        $holdings = $this->getHoldings(array('id' => $id));
        if (is_array($holdings) && count($holdings) > 0) {
            if (isset($holdings[0]['%'])) {
                return $holdings[0]['%'];
            }
        }
        return '';
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
