<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcMuni extends SolrMarcBase
{
    const MUNI_ALEPH_BASE = "https://aleph.muni.cz/F?func=item-global&doc_library=MUB01&doc_number=";

    public function getHoldLink() {
        return self::MUNI_ALEPH_BASE . $this->getLocalId();
    }

    protected function getExternalID() {
        return $this->getLocalId();
    }
}

