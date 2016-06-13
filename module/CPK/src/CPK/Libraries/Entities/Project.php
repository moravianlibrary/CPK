<?php

namespace CPK\Libraries\Entities;

class Project {
    private $code;
    private $name;

    function __construct($apiobject) {
        $this->code = $apiobject->code;
        $this->name = $apiobject->name;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    
}