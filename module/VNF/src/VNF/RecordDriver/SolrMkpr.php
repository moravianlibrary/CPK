<?php 
namespace VNF\RecordDriver;

class SolrMkpr extends SolrMarc
{

    protected function getExternalID ()
    {
        $localId = $this->getLocalId();
        $id = substr($localId, 6);
        return ltrim($id, '0');
    }
    
}