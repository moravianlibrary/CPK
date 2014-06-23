<?php
namespace MZKPortal\RecordDriver;
use VuFind\RecordDriver\SolrDefault;

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

    /**
    * uses setting from config.ini => External links
    * @return array  [] => [[0] = institution, [1] = [external link to catalogue]]
    *
    */
    public function getExternalLinks() {
        $resultArray = array();
        foreach ($this->getMergedIds() as $id) {
            list($ins, $id) = explode('.', $id);
            switch ($ins) {
                case 'mzk':
                    $finalID = $id;
                    break;
                case 'muni':
                    $finalID = $id;
                    break;
                case 'mend':
                    $finalID = substr($id, 5);
                    break;
            }
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase)) {
                $resultArray[] = array($ins, '');
            }
            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';
            $resultArray[] = array($ins, $linkBase . $finalID . $linkEnd);
        }
        return $resultArray;
    }

}
