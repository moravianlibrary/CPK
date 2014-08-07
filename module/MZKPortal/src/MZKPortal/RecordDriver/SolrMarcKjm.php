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
     * @return array of holdings from field 993
     */
    public function getHoldings() {
        $result = array();
    
        if (!$this->marcRecord) {
            return $result;
        }
    
        foreach ($this->marcRecord->getFields('993') as $currentField) {
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