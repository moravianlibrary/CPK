<?php
namespace ObalkyKnihV3\View\Helper\ObalkyKnih;

use VuFind\View\Helper\Root\Record as ParentRecord;

class Record extends ParentRecord
{
    
    public function getObalkyKnihJSONV3()
    {
        $bibinfo = $this->driver->tryMethod('getBibinfoForObalkyKnihV3');
        if (empty($bibinfo)) {
            $isbn = $this->driver->getCleanISBN();
            if (!empty($isbn)) {
                $bibinfo['isbn'] = $isbn;
            }
        }
        if (empty($bibinfo)) {
            return false;
        } else {
            return json_encode($bibinfo, JSON_HEX_QUOT | JSON_HEX_TAG);
        }
    }

}