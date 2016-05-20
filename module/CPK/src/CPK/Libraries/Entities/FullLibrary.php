<?php

namespace CPK\Libraries\Entities;

class FullLibrary extends SimpleLibrary {
    public $created_at;
    public $updated_at;
    //array of Person objects
    public $people;
    //array of Website objects
    public $websites;
    //array of email objects
    public $emails;
    //array of phone numbers
    public $phones;
    //array of fax numbers
    public $faxes;
    //array of opening hours
    public $opening_hours;
    //array of projects objects
    public $projects;
    //array of services objects
    public $services;


}