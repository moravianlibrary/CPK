<?php

namespace CPK\Libraries;

use CPK\Libraries\Entities\Branch;
use CPK\Libraries\Entities\Email;
use CPK\Libraries\Entities\Fax;
use CPK\Libraries\Entities\FullLibrary;
use CPK\Libraries\Entities\OpeningHours;
use CPK\Libraries\Entities\Person;
use CPK\Libraries\Entities\Phone;
use CPK\Libraries\Entities\Project;
use CPK\Libraries\Entities\Service;
use CPK\Libraries\Entities\SimpleLibrary;
use CPK\Libraries\Entities\Website;

class Loader {

    public static $infoKnihovnyUrl = "http://info.knihovny.cz/api/v1/";

    /**
     * @param $sigla
     *
     * @return FullLibrary object
     */
    public function LoadLibrary($sigla){
        $url   = Loader::$infoKnihovnyUrl.'libraries/'.$sigla;

        $client = new \Zend\Http\Client($url);
        $response = $client->send();

        // Response head error handling
        $responseStatusCode = $response->getStatusCode();
        if($responseStatusCode !== 200)
            throw new \Exception("info.knihovny.cz response status code: ".$responseStatusCode);

        $output	= $response->getBody();
        $apilibrary = \Zend\Json\Json::decode($output);

        $library = new FullLibrary();
        $library->ParseSimpleLibrary($apilibrary,$library);
        $library->created_at = $apilibrary->created_at;
        $library->updated_at = $apilibrary->updated_at;

        if(isset($apilibrary->people) && ! empty($apilibrary->people)) {
            $people = [];
            foreach ($apilibrary->people as $apiperson) {
                $person = new Person($apiperson);
                $people[] = $person;
            }
            $library->people = $people;
        }


        if(isset($apilibrary->websites) && ! empty($apilibrary->websites)) {
            $websites = [];
            foreach ($apilibrary->websites as $apiwebsite) {
                $website = new Website($apiwebsite);
                $websites[] = $website;
            }
            $library->websites = $websites;
        }

        if(isset($apilibrary->emails) && ! empty($apilibrary->emails)) {
            $emails = [];
            foreach ($apilibrary->emails as $apiemail) {
                $email = new Email($apiemail);
                $emails[] = $email;
            }
            $library->setEmails($emails);
        }

        if(isset($apilibrary->phones) && ! empty($apilibrary->phones)) {
            $phones = [];
            foreach ($apilibrary->phones as $apiphone) {
                $phone = new Phone($apiphone);
                $phones[] = $phone;
            }
            $library->setPhones($phones);
        }

        if(isset($apilibrary->faxes) && ! empty($apilibrary->faxes)) {
            $faxes = [];
            foreach ($apilibrary->faxes as $apifax) {
                $fax = new Fax($apifax);
                $faxes[] = $fax;
            }
            $library->faxes = $faxes;
        }
        
        if(isset($apilibrary->opening_hours) && ! empty($apilibrary->opening_hours)) {
            $apiOpeningHours = $apilibrary->opening_hours;
            $openingHours = new OpeningHours($apiOpeningHours);

            $library->opening_hours = $openingHours;
        }

        if(isset($apilibrary->projects) && ! empty($apilibrary->projects)) {
            $projects = [];
            foreach ($apilibrary->projects as $apiproject) {
                $project = new Project($apiproject);
                $projects[] = $project;
            }
            $library->projects = $projects;
        }

        if(isset($apilibrary->services) && ! empty($apilibrary->services)) {
            $services = [];
            foreach ($apilibrary->services as $apiservice) {
                $service = new Service($apiservice);
                $services[] = $service;
            }
            $library->services = $services;
        }

        if(isset($apilibrary->branches) && ! empty($apilibrary->branches)) {
            $branches = [];
            foreach ($apilibrary->branches as $apibranch) {
                $branch = new Branch($apibranch);
                $branches[] = $branch;
            }
            $library->branches = $branches;
        }

        return $library;


    }


    /**
     * @return array of Project objects
     */
    public function LoadAllProjects(){
        return "";
    }

    /**
     * @return array of Service objects
     */
    public function LoadAllServices(){

    }

    /**
     * @param $sigla
     *
     * @return array of Project objects
     */
    public function LoadLibraryProjects($sigla){

    }

    /**
     * @return array of Service objects
     */
    public function LoadLibraryServices($sigla){

    }

    /**
     * @param $query
     * @param $limit
     * @param $lang
     *
     * @return array of Strings
     */
    public function LoadAutocomplete($query, $limit, $lang){

    }


}