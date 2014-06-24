<?php

namespace VNF\RecordDriver;
use VNF\RecordDriver\SolrMarc as SolrMarc;


class SolrKkfb extends SolrMarc
{
    protected function getExternalID() {
        return substr($this->getLocalId(), 5);
    }
}
