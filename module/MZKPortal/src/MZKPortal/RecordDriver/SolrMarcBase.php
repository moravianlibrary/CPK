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
    public function getHoldings() {
        $result = array();
        
        if (!$this->marcRecord) {
            return $result;
        }
        
        foreach ($this->marcRecord->getFields('996') as $currentField) {
            $currentHolding = array();
            foreach ($currentField->getSubfields(null) as $currentSubfield) {
                $currentHolding[$currentSubfield->getCode()] = $currentSubfield->getData();    
            }
            if (count($currentHolding) > 0) {
                $result[] = $currentHolding;
            }
        }
        return $result;
    }

}
