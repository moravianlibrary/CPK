<?php
namespace VNF\RecordDriver;
use VuFind\RecordDriver\SolrDefault;


// TODO: unify with SolrMarcMerged in MZKPortal module (move to MZKCommon?)
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
        return null;
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

    /**
    * uses setting from config.ini => External links
    * @return array  [] => [
    *          [institution] = institution, 
    *          [url] = external link to catalogue,
    *          [display] => link to be possibly displayed]
    *          [id] => local identifier of record
    *
    */
    public function getExternalLinks() {
        $resultArray = array();
        foreach ($this->getMergedIds() as $currentId) {
            list($ins, $id) = explode('.', $currentId);
            if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);

            switch ($ins) {
                case 'kkfb': 
                    $finalID = substr($id, 5);
                    break;
                case 'ktn':
                    $i = 0;
                    for (;$i < strlen($id); $i++) {
                        if (!ctype_alpha($id[$i])) break;
                    }
                    $finalID = substr($id, $i);
                    break;
                default:
                    $finalID = $id;
            }
            
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase)) {
                $resultArray[] = array('institution' => $ins, 'url' => '', 'display' => '', 'id' => $currentId);
                continue;
            }
            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';
            $externalLink = $linkBase . $finalID . $linkEnd;
            $resultArray[] = array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id);
        }
        return $resultArray;
    }

}

