<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcBase extends SolrMarc
{

    public function getInstitutionsWithIds() {
        $id = $this->getUniqueID();
        list($source, $localId) = explode('.', $this->getUniqueID());
        return array($source => $id);
    }

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    public function getHoldLink() {
        return null;
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

        list($ins, $id) = explode('.' , $this->getUniqueID());
        $linkBase = $this->recordConfig->ExternalLinks->$ins;
        if (empty($linkBase)) {
            $resultArray[] = array($ins, '');
        }

        $finalID = $this->getExternalID();
        if (!isset($finalID)) array(array('institution' => $ins, 'url' => '', 'display' => '', 'id' => $id));

        $confEnd  = $ins . '_end';
        $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        if (!isset($linkEnd)) $linkEnd = '';
        $externalLink =  $linkBase . $finalID . $linkEnd;
        return array(array('institution' => $ins, 'url' => $externalLink, 'display' => $externalLink, 'id' => $id));
    }

    protected function getExternalID() {
        return '';
    }

}
