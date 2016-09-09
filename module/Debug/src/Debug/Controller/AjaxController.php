<?php

namespace Debug\Controller;

use CPK\Controller\AjaxController as AjaxControllerBase;

class AjaxController extends AjaxControllerBase
{
    protected function output($data, $status, $httpCode = null)
    {
        if (isset($GLOBALS['dg'])) {
            $data['dg'] = $GLOBALS['dg'];
            unset($GLOBALS['dg']);
        }
        return parent::output($data, $status, $httpCode);
    }
}