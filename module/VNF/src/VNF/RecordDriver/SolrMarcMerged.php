<?php
namespace VNF\RecordDriver;
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
            if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);

            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';

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
                case 'sup';
                    $urls = isset($this->fields['externalLinks_str_mv']) ? $this->fields['externalLinks_str_mv'] : array();
                    foreach ($urls as $url) {
                        list($extIns, $extUrl) = explode(';', $url);
                        if ($extIns == 'vnf_sup' || $extIns == 'sup') {
                            $externalLink = 'http://' . $extUrl . $linkEnd;
                            $resultArray[] = array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id);
                            continue 3;
                        }
                    }
                    $finalID = $id;
                    break;
                default:
                    $finalID = $id;
            }

            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase)) {
                $resultArray[] = array('institution' => $ins, 'url' => '', 'display' => '', 'id' => $currentId);
                continue;
            }
           
            $externalLink = $linkBase . $finalID . $linkEnd;
            $resultArray[] = array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id);
        }
        return $resultArray;
    }

}

