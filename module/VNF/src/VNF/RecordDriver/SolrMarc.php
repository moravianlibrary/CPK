<?php
namespace VNF\RecordDriver;
use PortalsCommon\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Default record driver for VNF
 *
 */
class SolrMarc extends ParentSolrDefault
{
    public function getInstitutions() {
        return isset($this->fields['institutionAlbumsOnly'])
        ? $this->fields['institutionAlbumsOnly'] : array();
    }
    
    public function getSignature() {
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
    
    public function getId() {
        return $this->fields['id'];
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
