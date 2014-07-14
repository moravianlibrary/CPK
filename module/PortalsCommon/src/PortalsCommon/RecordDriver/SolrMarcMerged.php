<?php
        
namespace PortalsCommon\RecordDriver;
use VuFind\RecordDriver\SolrDefault as SolrDefault;

class SolrMarcMerged extends SolrDefault
{

    public function getMergedIds() {
        return isset($this->fields['local_ids_str_mv']) ?
            $this->fields['local_ids_str_mv'] : array();
    }

    public function getInstitutionsWithIds() {
        $ids = $this->getMergedIds();
        $result = array();
        foreach ($ids as $id) {
            list($source, $localId) = explode('.', $id);
            $result[$source] = $id;
        }
        ksort($result);
        return $result;
    }

    public function getAvailabilityID() {
        return $this->getId();
    }
   
    public function getId() {
        return $this->getUniqueID();
    }
    
    public function getLibraries() {
        $result = array();
        $ids = $this->getMergedIds();
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

     public function getGlobalSite() {
      $site = $this->mainConfig->Site->url;
      return $site && substr($site, -1) === '/' ? substr($site, 0, -1) : $site;
    }

    

}

