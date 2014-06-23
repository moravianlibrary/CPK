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
     * @return array[ array[ [0] = institution, [1] = external link]] due to compatibility with merged records
     */
    public function getExternalLinks() {

        list($ins, $id) = explode('.' , $this->getUniqueID());
        $linkBase = $this->recordConfig->ExternalLinks->$ins;
        if (empty($linkBase)) {
            $resultArray[] = array($ins, '');
        }

        $finalID = $this->getExternalID();
        if (!isset($finalID)) return array($ins, '');

        $confEnd  = $ins . '_end';
        $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        if (!isset($linkEnd)) $linkEnd = '';
        return array(array($ins, $linkBase . $finalID . $linkEnd));
    }

    protected function getExternalID() {
        return '';
    }

}
