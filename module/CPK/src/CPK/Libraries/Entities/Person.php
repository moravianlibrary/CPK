<?php

namespace CPK\Libraries\Entities;

class Person {
    private $first_name;
    private $last_name;
    private $email;
    private $phone;
    private $degree1;
    private $degree2;
    private $role;

    function __construct($apiobject) {
        $this->first_name = $apiobject->first_name;
        $this->last_name = $apiobject->last_name;
        $this->email = $apiobject->email;
        $this->phone = $apiobject->phone;
        $this->degree1 = $apiobject->degree1;
        $this->degree2 = $apiobject->degree2;
        $this->role = $apiobject->role;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return mixed
     */
    public function getDegree1()
    {
        return $this->degree1;
    }

    /**
     * @return mixed
     */
    public function getDegree2()
    {
        return $this->degree2;
    }

    /**
     * @return mixed
     */
    public function getRole()
    {
        return $this->role;
    }

    

}