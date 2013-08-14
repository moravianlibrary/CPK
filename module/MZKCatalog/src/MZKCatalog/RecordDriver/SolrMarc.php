<?php
namespace MZKCatalog\RecordDriver;
use VuFind\RecordDriver\SolrMarc As ParentSolrDefault;

class SolrMarc extends ParentSolrDefault
{   
    
    public function getBibinfoForObalkyKnih()
    {
        $bibinfo = array(
            "authors" => array($this->getPrimaryAuthor()),
            "title" => $this->getTitle(),
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

}