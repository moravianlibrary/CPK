<?php
namespace MZKCatalog\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for EBSCO records
 *
 */
class EbscoSolrMarc extends ParentSolrDefault
{

    public function getEODLink()
    {
        return null;
    }

    public function isAvailableForDigitalization()
    {
        return false;
    }

    public function getRealTimeHoldings()
    {
        return array();
    }

    public function getRestrictions()
    {
        return array();
    }

    public function getSubscribedYears()
    {
        return null;
    }

    public function getSubscribedVolumes()
    {
        return null;
    }

    public function getNumberOfHoldings()
    {
        return null;
    }

    public function getItemLinks()
    {
        return array();
    }

}