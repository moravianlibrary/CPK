<?php
namespace MZKPortal\RecordTab;
use VuFind\RecordTab\AbstractBase;

class Libraries extends AbstractBase
{
    
    public function getDescription() {
        return 'Libraries';
    }
    
    public function getLibraries() {
        $result = array();
        $ids = $this->getRecordDriver()->getMergedIds();
        foreach ($ids as $id) {
            list($source, $localId) = explode('.', $id);
            $result[$source] = $id;
        }
        return $result;
    }
    
}