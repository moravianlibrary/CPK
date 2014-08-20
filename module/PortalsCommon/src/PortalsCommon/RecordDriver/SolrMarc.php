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
    * @param string type
    *     'link' - defalult value, uses settings from config.ini => External links
    *     'holdings - uses settings from config.ini => External holdings
    * @return array  [] => [
    *          [institution] = institution, 
    *          [url] = external link to catalogue,
    *          [display] => link to be possibly displayed]
    *          [id] => local identifier of record
    *
    */
    public function getExternalLinks($type = 'link') {

        list($ins, $id) = explode('.' , $this->getUniqueID());
        //FIXME temporary
        if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);
        if (strcasecmp($type, 'holdings') === 0) {
	       $linkBase = $this->recordConfig->ExternalHoldings->$ins;
        } else {
            $linkBase = $this->recordConfig->ExternalLinks->$ins;
        }
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
        if (strcasecmp($type, 'holdings') === 0) { 
            $linkEnd  = $this->recordConfig->ExternalHoldings->$confEnd;
        } else {
            $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        }
        
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
    
    public function getAvailableHoldingFilters()
    {
        return array(
                'year' => array('type' => 'select', 'keep' => array('hide_loans')),
                'volume' => array('type' => 'select', 'keep' => array('hide_loans')),
                'hide_loans' => array('type' => 'checkbox', 'keep' => array('year', 'volume')),
        );
    }
}
