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
    
    public function getFormats()
    {
    	return isset($this->fields['cpk_detected_format_txtF_mv']) ? $this->fields['cpk_detected_format_txtF_mv'] : [];
    }
    
    public function get996(array $subfields)
    {
    	return $this->getFieldArray('996', $subfields);
    }
    
    public function getParentRecordID()
    {
    	return isset($this->fields['parent_id_str']) ? $this->fields['parent_id_str'] : [];
    }

    public function getAntikvariatyLink()
    {
    	return isset($this->fields['external_links_str_mv']) ? $this->fields['external_links_str_mv'] : [];
    }
}
