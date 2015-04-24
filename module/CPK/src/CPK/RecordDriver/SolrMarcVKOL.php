<?php
namespace CPK\RecordDriver;
use CPK\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarcVKOL extends ParentSolrMarc
{

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    protected function getExternalID() {
        return substr($this->getLocalId(), 4);
    }

}
