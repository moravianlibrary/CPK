<?php
namespace MZKPortal\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarcMerged as ParentSolr;

class SolrMarcMerged extends ParentSolr
{

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
