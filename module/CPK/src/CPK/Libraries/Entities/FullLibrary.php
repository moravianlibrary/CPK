<?php

namespace CPK\Libraries\Entities;

class FullLibrary extends SimpleLibrary {
    public $created_at;
    public $updated_at;
    //array of people objects
    public $people;
    //array of website objects
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