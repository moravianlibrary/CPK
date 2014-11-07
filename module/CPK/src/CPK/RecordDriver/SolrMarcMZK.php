<?php
namespace CPK\RecordDriver;
use CPK\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarcMZK extends ParentSolrMarc
{

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    protected function getExternalID() {
        return  $this->getLocalId();
    }

}
