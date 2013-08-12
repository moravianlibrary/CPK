<?php
namespace MZKPortal\RecordDriver;
use VuFind\RecordDriver\SolrDefault;

class SolrMarcMerged extends SolrDefault
{
    public function getMergedIds() {
        return isset($this->fields['local_ids_str_mv']) ?
            $this->fields['local_ids_str_mv'] : array();
    }
}