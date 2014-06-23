<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

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

}
