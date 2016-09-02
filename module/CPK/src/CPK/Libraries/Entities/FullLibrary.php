<?php

namespace CPK\Libraries\Entities;

class FullLibrary extends SimpleLibrary {
    public $created_at;
    public $updated_at;
    //array of Person objects
    public $people;
    //array of Website objects
    public $websites;
    //array of Email objects
    private $emails;
    //array of Phone objects
    private $phones;
    //array of Fax objects
    public $faxes;
    //OpeningHours object
    public $opening_hours;
    //array of Project objects
    public $projects;
    //array of Service objects
    public $services;
    //array of Branch objects
    public $branches;

    /**
     * @return mixed
     */
    public function getEmails()
    {
        return $this->emails;
    }

    /**
     * @return bool
     */
    public function hasMoreThan2Emails()
    {
        $size = count($this->emails);
        if ($size>2)
            return true;
        return false;
    }

    /**
     * @return mixed
     */
    public function getFirst2Emails()
    {
        return array_slice($this->emails,0,2);
    }

    /**
     * @return mixed
     */
    public function getMoreEmails()
    {
        return array_slice($this->emails,2);
    }


    /**
     * @param mixed $emails
     */
    public function setEmails($emails)
    {
        $this->emails = $emails;
    }

    /**
     * @return mixed
     */
    public function getPhones()
    {
        return $this->phones;
    }

    /**
     * @return bool
     */
    public function hasMoreThan2Phones()
    {
        $size = count($this->phones);
        if ($size>2)
            return true;
        return false;
    }

    /**
     * @return mixed
     */
    public function getFirst2Phones()
    {
        return array_slice($this->phones,0,2);
    }

    /**
     * @return mixed
     */
    public function getMorePhones()
    {
        return array_slice($this->phones,2);
    }


    /**
     * @param mixed $phones
     */
    public function setPhones($phones)
    {
        $this->phones = $phones;
    }


}