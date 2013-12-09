<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcVut extends SolrMarcBase
{
    const VUT_ALEPH_BASE = "http://aleph.lib.vutbr.cz/F?func=item-global&doc_library=BUT01&doc_number=";

    public function getHoldLink() {
        return self::VUT_ALEPH_BASE . $this->getLocalId();
    }

}