<?php
namespace VNF\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Default record driver for VNF
 *
 */
class SolrMarc extends ParentSolrDefault
{
    public function getInstitutions()
    {
        return isset($this->fields['institutionAlbumsOnly'])
        ? $this->fields['institutionAlbumsOnly'] : array();
    }
    
    public function getSignature()
    {
        $field = $this->marcRecord->getField('910');
        if ($field) {
            $subfield = $field->getSubfield('b');
            if ($subfield) {
                return $subfield->getData();
            }
        }
        
        $field = $this->marcRecord->getField('996');
        if ($field) {
            $subfield = $field->getSubfield('c');
            return $subfield ? $subfield->getData() : '';
        }
        return '';
    }
    
    public function getId()
    {
        return $this->fields['id'];
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


    public function getUniqueKeys()
    {
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
 
    public function getOtherFields()
    {
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
        return isset($this->fields['authors_other_str_mv']) ?
            $this->fields['authors_other_str_mv'] : array();
    }

    public function getCorporateAuthor()
    {
        return isset($this->fields['authors_corporate_str_mv']) ?
            $this->fields['authors_corporate_str_mv'] : array();
    }
    
    public function getProductionCredits()
    {
        return array();
    }   
}
