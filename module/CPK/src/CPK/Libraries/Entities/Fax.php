<?php

namespace CPK\Libraries\Entities;

class Fax {
    private $fax;

    function __construct($apiobject) {
        $this->fax = $apiobject->fax;
    }

    /**
     * @return mixed
     */
    public function getFax()
    {
        return $this->fax;
    }
    
    
    
}