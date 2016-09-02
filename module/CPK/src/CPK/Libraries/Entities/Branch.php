<?php

namespace CPK\Libraries\Entities;

class Branch {
    private $name;
    private $address;
    private $longitude;
    private $latitude;

    function __construct($apiobject) {
        $this->name = $apiobject->name;
        $this->address = $apiobject->address;
        $this->longitude = $apiobject->longitude;
        $this->latitude = $apiobject->latitude;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return mixed
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @return mixed
     */
    public function getLatitude()
    {
        return $this->latitude;
    }


    
}