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
    public function getExternalLinks() 
    {
        $resultArray = array();
        foreach ($this->getMergedIds() as $currentId) {
            list($ins, $id) = explode('.', $currentId);
            if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);

            $confEnd  = $ins . '_end';
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
            if (!isset($linkEnd)) $linkEnd = '';
            $finalID = '';
            
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
                    $finalID = '';
                    break;
                default:
                    $finalID = $id;
            }

            $linkBase = $this->recordConfig->ExternalLinks->$ins;
            if (empty($linkBase) || empty ($finalID)) {
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
         if (isset($this->fields['institutionAlbumsOnly']) 
               && in_array('SUP', $this->fields['institutionAlbumsOnly']) 
               && count($this->fields['institutionAlbumsOnly']) > 0
               && isset($this->fields['label_path_str_mv']))  {
                   
            $confPath = 'labels';
            $path = rtrim($this->fields['label_path_str_mv'][0], '/');
            $path = $confPath . $path;
           
            if (!empty($path) && strlen($path) > 10) {

                if ($size == 'medium' || $size='small') {
                    return $path;
                }
                $path = substr($path, 0, -10);
                return $path . $size . '.jpg';      
            }

        }


        $actualSize = $size == 'small' || $size == 'medium' ? '' : $size;
        if ($size == 'medium') $size = 'small';
        $formats = $this->fields['format'];


        if (is_array($formats)) {
            $base = '';
            if (in_array('vnf_CD', $formats)) {
                $base .= 'format/nf-icon-cd';
            } elseif (in_array('vnf_vinyl', $formats) || in_array('vnf_shellac', $formats)) {
                $base .= 'format/nf-icon-gramo';
            } elseif (in_array('vnf_SoundCassette', $formats)) {
                $base .= 'format/nf-icon-magnetic';
            } elseif (in_array('vnf_magneticTape', $formats)) {
                $base .= 'format/nf-icon-tape';
            } elseif (in_array('vnf_track', $formats) || in_array('vnf_unspecified', $formats)) {
                $base .= 'format/nf-icon-tune';
            } elseif (in_array('vnf_data', $formats)) {
                $base .= 'format/nf-icon-file';
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

    public function getUniqueKeys() {
        $result = array();
        foreach (array('ean_view_txtP_mv',
            'isrc_view_txtP_mv',
            'upc_view_txtP_mv',
            'issue_view_txtP_mv',
            'matrix_view_txtP_mv',
            'plate_view_txtP_mv',
            'publisher_view_txtP_mv') as $current) {
            
            if (array_key_exists($current, $this->fields)) {
                $keyType = substr($current, 0, strlen($current) - strlen('_txtP_mv'));
                foreach ($this->fields[$current] as $key) {
                    if (!isset($result[$keyType])) {
                        $result[$keyType] = array();
                    }
                    $result[$keyType][] = $key;
                }
            }
        }
        return $result;
    }
    
    public function getOtherFields() {
        $result = array();
        foreach (array('awards_str_mv',
            'audience_str_mv',
            'production_credits_str_mv',
            'performer_note_str_mv') as $current) {                 
            if (array_key_exists($current, $this->fields)) {
                $keyType = substr($current, 0, strlen($current) - strlen('_str_mv'));
                foreach ($this->fields[$current] as $key) {
                    if (!isset($result[$keyType])) {
                        $result[$keyType] = array();
                    }
                    $result[$keyType][] = $key;
                }
            }
        }
        return $result;
            
    }

    public function getGeneralNotes()
    {
        return isset($this->fields['description_str_mv']) ?
            $this->fields['description_str_mv'] : array();
    }

    public function getSecondaryAuthors()
    {
        if (isset($this->fields['authors_other_str_mv'])) {
            return is_array($this->fields['authors_other_str_mv']) ? $this->fields['authors_other_str_mv'] : array($this->fields['authors_other_str_mv']);
        }
        return array();
    }
    
    public function getCorporateAuthor()
    {
        return isset($this->fields['authors_corporate_str_mv']) ?
            $this->fields['authors_corporate_str_mv'] : array();
    }
    
    public function getTOC()
    {
        if (!isset($this->fields['contents']) ) {
            return array();
        }
        
        $content = $this->fields['contents'][0];
        
        if (preg_match('/^vnf_sup.*/', $content)) {
            //handle supraphon content
            $result = array();
            //remove prefix
            $content = substr($content, 8);
            $currentHolding = array();
            $currentResult = '';
            foreach (explode('--!--', $content) as $currentLine) {
                foreach (explode('$', $currentLine) as $currentSubfield) {
                    $currentResult [substr($currentSubfield, 0, 1)] = substr($currentSubfield, 1);
                }
                $result[] = $currentResult;
            }
            return $result;
        }
   
        return is_array($content) ? $content : explode('--', $content);
    }
    
    /**
     * Create urls with description
     * @see \VuFind\RecordDriver\SolrDefault::getURLs()
     */
    public function getURLs()
    {
        $supUtmParams = '';
        $linkEnd = $this->recordConfig->ExternalLinks->sup_end;
        if ($linkEnd) {
            $utmParams = $linkEnd;
        }
        
        $result = array();
        if (isset($this->fields['url'])) {
            foreach ($this->fields['url'] as $url) {
                $current = array();
                list($current['url'], $current['desc']) = explode('!desc!', $url);
                if (preg_match('/www\.supraphonline\.cz/', $current['url'])) {
                    $current['url'] = 'http://' . $current['url'] . $linkEnd;
                }
                $current['desc'] = !empty($current['desc']) ? $current['desc'] : 'Get full text';
                $result[] = $current;
            }   
        }
        return $result;
    }
    
    public function getSummary()
    {
        $summary = array();
        if (isset($this->fields['summary_txt'])) {
             $summary = array($this->fields['summary_txt']);
        }
        
        if (empty($summary)) {
            return array();
        }

        if (is_array($summary) && isset($summary[0])) {
            return explode('EOL_ENT', $summary[0]);
        }
        return $summary;
    }
    
    
    public function getPublicationDetails() {
        return parent::getPublicationDetails();
    }
}

