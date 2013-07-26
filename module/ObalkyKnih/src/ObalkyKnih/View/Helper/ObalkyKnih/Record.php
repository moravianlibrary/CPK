<?php
namespace ObalkyKnih\View\Helper\ObalkyKnih;

use VuFind\View\Helper\Root\Record as ParentRecord;

class Record extends ParentRecord
{
    
    public function getObalkyKnihJSON() {
        $bibinfo = $this->driver->tryMethod('getBibinfoForObalkyKnih');
        if (empty($bibinfo)) {
            $bibinfo = array(
                "authors" => array($this->driver->getPrimaryAuthor()),
                "title" => $this->driver->getTitle(),
            );
            $isbn = $this->driver->getCleanISBN();
            if (!empty($isbn)) {
                $bibinfo['isbn'] = $isbn;
            }
            $year = $this->driver->getPublicationDates();
            if (!empty($year)) {
                $bibinfo['year'] = $year[0];
            }
        }
        return json_encode($bibinfo, JSON_HEX_QUOT | JSON_HEX_TAG);
    }

}