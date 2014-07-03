<?php

namespace HF\RecordDriver;
use HF\RecordDriver\SolrMarc as SolrMarc;


class SolrMarcNM extends SolrMarc
{
    protected function getExternalID() {
        return substr($this->getLocalId(), 5);
    }
}
