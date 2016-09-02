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
    public $emails;
    //array of Phone objects
    public $phones;
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


}