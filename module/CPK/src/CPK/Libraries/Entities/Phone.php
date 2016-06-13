<?php

namespace CPK\Libraries\Entities;

class Phone {
    private $phone;

    function __construct($apiobject) {
        $this->phone = $apiobject->phone;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }
    
    
}