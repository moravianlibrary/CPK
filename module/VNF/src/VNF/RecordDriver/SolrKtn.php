<?php

namespace VNF\RecordDriver;
use VNF\RecordDriver\SolrMarc as SolrMarc;


class SolrKtn extends SolrMarc
{
    protected function getExternalID() {
        $id = $this->getLocalId();
        $i = 0;
        for(; $i < strlen($id); $i++) {
            if (!ctype_alpha($id[$i])) break;
        }
        return substr($id, $i);
    }
}
