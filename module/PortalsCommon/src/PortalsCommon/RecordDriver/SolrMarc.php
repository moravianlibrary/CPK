<?php
namespace PortalsCommon\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{
    

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
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
        //FIXME temporary
        if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);
	$linkBase = $this->recordConfig->ExternalLinks->$ins;

        if (empty($linkBase)) { 
            return array(
                       array('institution' => $ins, 
                             'url' => '', 
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $finalID = $this->getExternalID();
        if (!isset($finalID)) { 
            return array(
                       array('institution' => $ins, 
                             'url' => '', 
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $confEnd  = $ins . '_end';
        $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        if (!isset($linkEnd) ) $linkEnd = '';
        $externalLink =  $linkBase . $finalID . $linkEnd;
        return array(
                   array('institution' => $ins, 
                         'url' => $externalLink,
                         'display' => $externalLink,
                         'id' => $id));
    }

    protected function getExternalID() {
        return $this->getLocalId();
    }
    
    public function getId() {
        return $this->getUniqueID();
    }

    public function getGlobalSite() {
      $site = $this->mainConfig->Site->url;
      return $site && substr($site, -1) === '/' ? substr($site, 0, -1) : $site;
    }
}
