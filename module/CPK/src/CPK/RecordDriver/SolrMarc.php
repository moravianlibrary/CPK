<?php
namespace CPK\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    protected function getExternalID() {
        return $this->getLocalId();
    }
    
    public function get996()
    {
    	return $this->getFieldArray('996', array('l', 'r'));
    }

    public function getFormats()
    {
        return isset($this->fields['cpk_detected_format_txtF_mv']) ? $this->fields['cpk_detected_format_txtF_mv'] : [];
    }

}
