<?php
namespace MZKCommon\RecordDriver;
use VuFind\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{

    public function getBibinfoForObalkyKnih()
    {
        $bibinfo = array(
            "authors" => array($this->getPrimaryAuthor()),
            "title" => $this->getTitle(),
            "ean" => $this->getEAN()
        );
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $year = $this->getPublicationDates();
        if (!empty($year)) {
            $bibinfo['year'] = $year[0];
        }
        return $bibinfo;
    }

    public function getBibinfoForObalkyKnihV3()
    {
        $bibinfo = array();
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $ean = $this->getEAN();
        if (!empty($ean)) {
            $bibinfo['ean'] = $ean;
        }
        $cnb = $this->getCNB();
        if (isset($cnb)) {
            $bibinfo['nbn'] = $cnb;
        } else {
            $prefix = 'BOA001';
            $bibinfo['nbn'] = $prefix . '-' . str_replace('-', '', $this->getUniqueID());
        }
        return $bibinfo;
    }

    public function getAvailabilityID() {
        if (isset($this->fields['availability_id_str'])) {
            return $this->fields['availability_id_str'];
        } else {
            return $this->getUniqueID();
        }
    }

    public function getRealTimeHoldings($filters = array())
    {
        return $this->hasILS()
        ? $this->holdLogic->getHoldings($this->getUniqueID(), $filters)
        : array();
    }

    public function getHoldingFilters()
    {
        return array();
    }

    public function getAvailableHoldingFilters()
    {
        return array();
    }

    public function getEAN()
    {
        return (!empty($this->fields['ean_str_mv']) ? $this->fields['ean_str_mv'][0] : null);
    }

    protected function getCNB()
    {
        return isset($this->fields['nbn']) ? $this->fields['nbn'] : null;
    }

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

}
