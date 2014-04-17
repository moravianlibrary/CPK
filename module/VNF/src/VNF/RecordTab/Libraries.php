<?php
namespace VNF\RecordTab;
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
            if (strlen($source) > 6) {
                //remove portal prefix
                $source = substr($source, 4);
            }
            $result[$source] = $id;
        }
        return $result;
    }
    
}