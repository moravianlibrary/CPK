<?php
namespace MZKCatalog\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for MZK
 *
 */
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
    
    public function getRealTimeHoldings($filters = array())
    {
        $holdings = $this->hasILS()
        ? $this->holdLogic->getHoldings($this->getUniqueID(), $filters)
        : array();
        foreach ($holdings as &$holding) {
            $holding['duedate_status'] = $this->translateHoldingStatus($holding['status'],
                $holding['duedate_status']);
        }
        return $holdings;
    }
    
    public function getEODLink()
    {
        $eod = $this->getFirstFieldValue('EOD', array('a'));
        $isEod = ($eod == 'Y')?true:false;
        if (!$isEod) {
            return null;
        }
        list($base, $sysno) = explode('-', $this->getUniqueID());
        $eodLinks = array(
            'MZK01' => 'http://books2ebooks.eu/odm/orderformular.do?formular_id=133&sys_id=',
            'MZK03' => 'http://books2ebooks.eu/odm/orderformular.do?formular_id=131&sys_id=',
        );
        $link = $eodLinks[$base] . $sysno;
        return $link;
    }
    
    public function getRestrictions()
    {
        list($base, $sysno) = explode('-', $this->getUniqueID());
        $result = $this->getFieldArray('540', array('a'));
        if (in_array("NewspaperOrJournal", $this->getFormats())) {
            $result[] = 'periodicals_restriction_text';
        }
        return $result;
    }
    
    protected function translateHoldingStatus($status, $duedate_status)
    {
        $status = mb_substr($status, 0, 6, 'UTF-8');
        if ($duedate_status == 'On Shelf') {
            if ($status == 'Jen do' || $status == 'Studov') {
                return "present only";
            } else if ($status == 'Příruč') {
                return "reference";
            } else if ($status == 'Ve zpr') {
                return "";
            } else if ($status == 'Aktuál') {
                return "Newspapers and Journals - at the desk";
            }
        }
        if ($status == '0 po r') {
            return 'lost';
        } else if ($status == 'Nenale' || $duedate_status == 'Hledá ') {
            return 'lost - wanted';
        } else if ($status == 'Vyříze') {
            return 'lost by reader';
        }
        return $duedate_status;
    }

    public function getPublicationDates()
    {
        return isset($this->fields['publishDate_display']) ?
        $this->fields['publishDate_display'] : array();
    }

}