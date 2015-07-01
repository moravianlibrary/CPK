<?php
namespace CPK\View\Helper\CPK;

use CPK\View\Helper\Root\Record as ParentRecord;

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

    public function getObalkyKnihAdvert($description) {
        $sigla = '';
        if (isset($this->config->ObalkyKnih->sigla)) {
            $sigla = $this->config->ObalkyKnih->sigla;
        }
        return 'advert' . $sigla . ' ' . $description;
    }

}
