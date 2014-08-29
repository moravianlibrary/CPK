<?php
namespace MZKCatalog\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for MZK
 *
 */
class SolrMarc extends ParentSolrDefault
{

    protected $numberOfHoldings;
    
    const ALEPH_BASE_URL = "http://aleph.mzk.cz/";

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

    public function getNumberOfHoldings() {
        if (!isset($this->numberOfHoldings)) {
            $this->numberOfHoldings = count($this->marcRecord->getFields('996'));
        }
        return $this->numberOfHoldings;
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
        $eod = isset($this->fields['statuses']) && in_array('available_for_eod', $this->fields['statuses']);
        if (!$eod) {
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

    public function isDigitized()
    {
        foreach ($this->getFieldArray('856', array('y', 'z'), false) as $onlineAccessText) {
            $onlineAccessText = strtolower($onlineAccessText);
            if (strpos($onlineAccessText, 'digitaliz') !== false) {
                return true;
            }
        }
        return false;
    }

    public function isAvailableForDigitalization()
    {
        return $this->getEODLink() == null
            && substr($this->getUniqueID(), 0, 5) == 'MZK01'
            && substr($this->marcRecord->getField('008'), 23, 2) == 'xr'
            && in_array('Book', $this->getFormats())
            && !$this->isDigitized()
        ;
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

    public function getNativeLinks()
    {
        list($base, $sysno) = split('-', $this->getUniqueID());
        $fullView = self::ALEPH_BASE_URL . "F?func=direct&doc_number=$sysno&local_base=$base&format=999";
        $holdings = self::ALEPH_BASE_URL . "F?func=item-global&doc_library=$base&doc_number=$sysno";
        return array(
            'native_link_full_view' => $fullView,
            'native_link_holdings'  => $holdings
        );
    }

    public function getCallNumber()
    {
        if (isset($this->fields['callnumber_str_mv'])) {
            return $this->fields['callnumber_str_mv'];
        } else {
            return array_unique($this->getFieldArray('910', array('b')));
        }
    }

    public function getItemLinks()
    {
        $itemLinks = $this->marcRecord->getFields('994');
        if (!is_array($itemLinks)) {
            return array();
        }
        $links = array();
        foreach ($itemLinks as $itemLink) {
            $base   = $itemLink->getSubfield('l')->getData();
            $sysno  = $itemLink->getSubfield('b')->getData();
            $label = $itemLink->getSubfield('n')->getData();
            $type   = $itemLink->getSubfield('a')->getData();
            $links[] = array(
                'id'    => $base . '-' . $sysno,
                'type'  => $type,
                'label' => $label,
            );
        }
        return $links;
    }

    public function getSubscribedYears()
    {
        return $this->getFirstFieldValue('910', array('r'));
    }

    public function getSubscribedVolumes()
    {
        return $this->getFirstFieldValue('910', array('s'));
    }

}