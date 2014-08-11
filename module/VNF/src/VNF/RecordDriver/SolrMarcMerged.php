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
                case 'kjm':
                    $finalID = substr($id, 6);
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
    
    public function getThumbnail($size = 'small')
    {
        $actualSize = $size == 'small' || $size == 'medium' ? '' : $size;
        if ($size == 'medium') $size = 'small';
        $formats = $this->fields['format'];
        if (is_array($formats)) {
            $base = '';
            if (in_array('vnf_CD', $formats)) {
                $base .= 'format/nf-icon-cd';
            }
            if (in_array('vnf_vinyl', $formats)) {
                $base .= 'format/nf-icon-gramo';
            }
            if (in_array('vnf_SoundCassette', $formats)) {
                $base .= 'format/nf-icon-magnetic';
            }
            if (in_array('vnf_magneticTape', $formats)) {
                $base .= 'format/nf-icon-tape';
            }
    
            if (empty($base)) {
                return 'noimage.gif';
            }
    
            if (empty($actualSize)) {
                return $base . '.png';
            } else {
                return $base . '-large.png';
            }
        }
    
        return 'noimage.gif';
    }

}

