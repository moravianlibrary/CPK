<?php
namespace MZKPortal\RecordDriver;

class SolrMarcKjm extends SolrMarcBase
{

    protected function getExternalID ()
    {
        $localId = $this->getLocalId();
        $id = substr($localId, 6);
        return ltrim($id, '0');
    }
}