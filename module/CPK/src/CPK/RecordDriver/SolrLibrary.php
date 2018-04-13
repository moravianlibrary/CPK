<?php
namespace CPK\RecordDriver;

use CPK\RecordDriver\SolrMarc as ParentSolrMarc;
use VuFind\RecordDriver\Response;
use Exception;

class SolrLibrary extends ParentSolrMarc
{
    private $searchController = null;
    private $searchRunner = null;
    protected $facetsConfig = null;

    public function __construct($mainConfig = null, $recordConfig = null,
                                $searchSettings = null, $searchController = null, $searchRunner = null, $facetsConfig = null
    ) {
        $this->searchController = $searchController;
        $this->searchRunner = $searchRunner;
        $this->facetsConfig = $facetsConfig;
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }




    public function getParentRecordID()
    {
        return isset($this->fields['id']) ? $this->fields['id'] : [];
    }



    /**
     * Get an array of note about the libraryname
     *
     * @return array
     */
    public function getLibraryNames()
    {
        return isset($this->fields['name_display']) ? $this->fields['name_display'] : [];
    }

    /**
     * Get an array of note about the libraryhours
     *
     * @return array
     */
    public function getLibraryHours()
    {
        return isset($this->fields['hours_display']) ? $this->fields['hours_display'] : [];
    }

    /**
     * Get an array of note about the libraryhours
     *
     * @return array
     */
    public function getLibraryHoursArray()
    {
        $string = $this->getLibraryHours();
        if ($string == []) return [];
        $days = explode("|", $string);
        $result = array();
        foreach ($days as $day)
        {
            $parts = explode(" ", trim($day),2);
            $result[$parts[0]] = $parts[1];
        }
        return $result;
    }

    /**
     * Get an array of note about the
     *
     * @return array
     */
    public function getLastUpdated()
    {
        return isset($this->fields['lastupdated_str']) ? $this->fields['lastupdated_str'] : [];
    }



    /**
     * Get an array of note about the libraryname
     *
     * @return array
     */
    public function getLibraryAddress()
    {
        return isset($this->fields['address_display_mv']) ? $this->fields['address_display_mv'] : [];
    }


    /**
     * Get an array of library ico and dicn
     *
     * @return array
     */
    public function getIco()
    {
        return isset($this->fields['ico_display']) ? $this->fields['ico_display'] :'';
    }

    /**
     * Get an array of library ico and dicn
     *
     * @return array
     */
    public function getLibNote()
    {
        return isset($this->fields['note_display']) ? $this->fields['note_display'] :'';
    }


    /**
     *
     * @return array
     */
    public function getLibNote2()
    {
        return isset($this->fields['note2_display']) ? $this->fields['note2_display'] :'';
    }

    /**
     *
     * @return array
     */
    public function getSigla()
    {
        return isset($this->fields['sigla_display']) ? $this->fields['sigla_display'] :'';
    }

    /**
     *
     * @return array
     */
    public function getLibType()
    {
        return isset($this->fields['type_display']) ? $this->fields['type_display'] :'';
    }

    /**
     *
     * @return array
     */
    public function getLibUrl()
    {

        return isset($this->fields['url_display_mv']) ? $this->fields['url_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getLibUrlArray()
    {
        $result = array();

        if (isset($this->fields['url_display_mv'])) {
            $urls = $this->fields['url_display_mv'];

            foreach ($urls as $url)
            {
                $parts = explode("|", trim($url),2);
                $link = array();
                if (isset($parts[0]))
                    $link['url'] = trim($parts[0]);
                if (isset($parts[1]))
                    $link['name'] = trim($parts[1]);
                else
                    $link['name'] = trim($parts[0]);
                array_push($result, $link);
            }
        }
        return $result;

    }


    /**
     *
     * @return array
     */
    public function getMvsNote()
    {
        return isset($this->fields['mvs_display_mv']) ? $this->fields['mvs_display_mv'] :[];
    }

    /**
     *
     * @return array
     */
    public function getLibBranch()
    {
        return isset($this->fields['branch_display_mv']) ? $this->fields['branch_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getLibNameAlt()
    {
        return isset($this->fields['name_alt_display_mv']) ? $this->fields['name_alt_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getLibResponsibility()
    {
        return isset($this->fields['responsibility_display_mv']) ? $this->fields['responsibility_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getPhone()
    {
        return isset($this->fields['phone_display_mv']) ? $this->fields['phone_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getEmail()
    {
        return isset($this->fields['email_display_mv']) ? $this->fields['email_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getFax()
    {
        return isset($this->fields['fax_display_mv']) ? $this->fields['fax_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getService()
    {
        return isset($this->fields['services_display_mv']) ? $this->fields['services_display_mv'] :'';

    }

    /**
     *
     * @return array
     */
    public function getFunction()
    {
        return isset($this->fields['function_display_mv']) ? $this->fields['function_display_mv'] :'';
    }

    /**
     *
     * @return array
     */
    public function getProject()
    {
        return isset($this->fields['projects_display_mv']) ? $this->fields['projects_display_mv'] :'';
    }


    public function getGps()
    {
        return isset($this->fields['gps_str']) ? $this->fields['gps_str'] : '';
    }

    /**
     *
     * @return array
     */
    public function getType()
    {
        return isset($this->fields['type_display_mv']) ? $this->fields['type_display_mv'] : [];
    }

    /**
     *
     * @return array
     */
    public function getMvs()
    {
        return isset($this->fields['mvs_display_mv']) ? $this->fields['mvs_display_mv'] :[];
    }

    /**
     *
     * @return string
     */
    public function getBranchUrl()
    {
        return isset($this->fields['branchurl_display_mv']) ? $this->fields['branchurl_display_mv'] :'';
    }

    public function getBookSearchFilter(){
        $institution = isset($this->fields['cpk_code_search_txt']) ? $this->fields['cpk_code_search_txt'] :'';
        $institutionsMappings = $this->facetsConfig->InstitutionsMappings->toArray();

        if (isset($institutionsMappings[$institution]))
            return $institutionsMappings[$institution];

        return null;

    }

    public function getGpsLat()
    {
        if ($this->getGps()!="")
        {
            $parts = explode(" ", $this->getGps(),2);
            return $parts[0];
        }
        return "";
    }

    public function getGpsLng()
    {
        if ($this->getGps()!="")
        {
            $parts = explode(" ", $this->getGps(),2);
            return $parts[1];
        }
        return "";
    }


    public function AddInfoItemsCount()
    {
        $result = 0;
        if (!empty($this->getSigla())) $result++;
        if (!empty($this->getLastUpdated())) $result++;
        return $result;
    }

    public function ContactsItemsCount()
    {
        $result = 0;
        if (!empty($this->getPhone())) $result++;
        if (!empty($this->getEmail())) $result++;
        if (!empty($this->getLibResponsibility())) $result++;
        return $result;
    }

    public function ServicesItemsCount()
    {
        $result = 0;
        if (!empty($this->getService())) $result++;
        if (!empty($this->getFunction())) $result++;
        if (!empty($this->getProject())) $result++;
        return $result;
    }

    public function BranchesItemsCount()
    {
        $result = 0;
        if (!empty($this->getLibBranch())) $result++;
        return $result;
    }

    public function getRegion()
    {
        return $this->getFieldArray('KRJ', ['a', 'b']);
    }

    public function getFilterParamsForRelated()
    {
        $filter = !$this->getRegion() ? 'qf' : 'qt';
        return [
            'handler' => 'morelikethis',
            'filter' => $filter
        ];
    }
}

