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

    //FIXME: TODO
    protected function getCNB()
    {
        
    }

}