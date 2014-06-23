<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcMzk extends SolrMarcBase
{
    protected function getExternalID() {
        return $this->getLocalId();
    }
}

