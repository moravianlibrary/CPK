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

}