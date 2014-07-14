<?php
namespace MZKPortal\RecordDriver;

class SolrMarcMzk extends SolrMarcBase
{
    protected function getExternalID() {
        return $this->getLocalId();
    }
}

