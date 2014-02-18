<?php
namespace MZKCatalog\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc As ParentSolrDefault;

class SolrMarc extends ParentSolrDefault
{

    public function getTitle()
    {
        return isset($this->fields['title_display']) ?
            $this->fields['title_display'] : '';
    }

    public function getHoldingFilters()
    {
        return $this->ils->getDriver()->getHoldingFilters($this->getUniqueID());
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