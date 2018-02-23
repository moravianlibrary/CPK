<?php
namespace CPK\RecordDriver;

use CPK\RecordDriver\SolrMarc as ParentSolrMarc;
use VuFind\RecordDriver\Response;
use Exception;

class SolrAuthority extends ParentSolrMarc
{
    private $searchController = null;
    private $searchRunner = null;

    public function __construct($mainConfig = null, $recordConfig = null,
            $searchSettings = null, $searchController = null, $searchRunner = null
    ) {
        $this->searchController = $searchController;
        $this->searchRunner = $searchRunner;
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Get the full name of authority.
     *
     * @return string
     */
    public function getPersonalName()
    {
        return isset($this->fields['personal_name_display']) ? $this->fields['personal_name_display'] : '';
    }

    /**
     * Get the alternatives of the full name.
     *
     * @return array of alternative names
     */
    public function getAddedEntryPersonalNames()
    {
        return isset($this->fields['alternative_name_display_mv']) ? $this->fields['alternative_name_display_mv'] : [];
    }

    /**
     * Get the authority's pseudonyms.
     *
     * @return array
     */
    public function getPseudonyms() {
        $array = array();
        $fields = $this->getMarcRecord()->getFields('500');
        if (is_array($fields)) {
            foreach($fields as $currentField) {
                foreach (array('a', 'd', '7') as $subfield ) {
                    if (!isset($array[$subfield])) $array[$subfield] = array();
                    $currentVal = $currentField->getSubfield($subfield);
                    $currentVal = is_object($currentVal) ? $currentVal->getData() : "";
                    array_push($array[$subfield], $currentVal);
                }
            }
        }
        return $array;
    }

    /**
     * Get authority's source.
     *
     * @return array
     */
    public function getSource()
    {
        $field = $this->getFieldArray('670');
        return $field;
    }

    /**
     * Get the authority's name, shown as title of record.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        $field = $this->getFieldArray('100', array('a', 'd'));
        $name = empty($field) ? '' : $field[0];
        if (substr($name, -1) == ',') $name = substr($name, 0, -1);
        return $name;
    }

    public function getBibinfoForObalkyKnihV3()
    {
        $bibinfo = array();
        $field = $this->getMarcRecord()->getField('001');
        $bibinfo['auth_id'] = empty($field) ? '' : $field->getData();
        return $bibinfo;
    }

    /**
     * Get the url of authority's cover from obalkyknih.
     *
     * Example of return value https://cache.obalkyknih.cz/file/cover/1376898/medium
     *
     * @return string $coverUrl
     */
    public function getAuthorityCover()
    {
        $obalky = $this->getAuthorityFromObalkyKnih();
        $coverUrl = empty($obalky[0]['cover_medium_url']) ? '' : $obalky[0]['cover_medium_url'];
        $coverUrl = str_replace('http://', 'https://', $coverUrl);
        return $coverUrl;
    }

    /**
     * Get the authority's bibliographic details.
     *
     * @return array $field
     */
    public function getSummary()
    {
        return isset($this->fields['bibliographic_details_display_mv']) ? $this->fields['bibliographic_details_display_mv'] : [];
    }

    /**
     * Get authority's e-version links.
     *
     * @return array
     */
    public function getLinks()
    {
        $links = array();
        $field998 = $this->getFieldArray('998', array('a'));
        foreach ($field998 as $part) {
            $links[] = 'auth|online|' . $part;
        }

        $field856 = $this->getFieldArray('856', array('u'));
        foreach ($field856 as $part) {
            if (strpos($part, 'osobnostiregionu') !== false) {
                $links[] = 'osobnostiregionu|online|' . $part;
            } else {
                $links[] = 'auth|online|' . $part;
            }
        }
        return $links;
    }

    /**
     * Get the bibliographic details of authority.
     *
     * @return string $details
     */
    public function getBibliographicDetails()
    {
        return isset($this->fields['bibliographic_details_display_mv']) ? $this->fields['bibliographic_details_display_mv'][0] : '';
    }

    private function getAuthorityFromObalkyKnih()
    {
        if (! isset($this->obalky)) {
            $field = $this->getAuthorityId();
            $auth_id = empty($field) ? '' : $field->getData();

            if (! empty($auth_id)) {
                try {
                    $cacheUrl = !isset($this->mainConfig->ObalkyKnih->cacheUrl)
                        ? 'https://cache.obalkyknih.cz' : $this->mainConfig->ObalkyKnih->cacheUrl;
                    $metaUrl = $cacheUrl . "/api/auth/meta";
                    $client = new \Zend\Http\Client($metaUrl);
                    $client->setParameterGet(array(
                        'auth_id' => $auth_id
                    ));

                    $response = $client->send();
                    $responseBody = $response->getBody();
                    $phpResponse = json_decode($responseBody, true);
                    $this->obalky = empty($phpResponse) ? null : $phpResponse;
                }
                catch (Exception $e) {
                    $this->obalky = null;
                }
            }
            else {
                $this->obalky = null;
            }
        }
        return $this->obalky;
    }

    /**
     * Get short note for authority (not record)
     *
     * @return string
     */
    public function getShortNoteEn()
    {
        return isset($this->fields['short_note_en_display']) ? $this->fields['short_note_en_display'] : '';
    }

    /**
     * Get short note for authority (not record)
     *
     * @return string
     */
    public function getShortNoteCs()
    {
        return isset($this->fields['short_note_cs_display']) ? $this->fields['short_note_cs_display'] : '';
    }

    /**
     * Get heading for authority (not record)
     *
     * @return string
     */
    public function getHeading()
    {
        return isset($this->fields['heading_display']) ? $this->fields['heading_display'] : '';
    }

    /**
     * Get id_authority.
     *
     * @return string
     */
    public function getAuthorityId()
    {
        return isset($this->fields['authority_id_display']) ? $this->fields['authority_id_display'] : '';
    }

    /**
     * Returns true, if authority has publications.
     *
     * @return bool
     */
    public function hasPublications()
    {
        $request = array(
            'join' => 'AND',
            'type0' => array(0 => 'adv_search_author_corporation'),
            'bool0' => array(0 => 'AND'),
            'lookfor0' => array(0 => $this->getAuthorityId()),
            'limit' => '1',
        );
        $results = $this->searchRunner->run( $request, 'Solr', $this->searchController->getSearchSetupCallback() );
        return ($results->getResultTotal() > 0) ? true : false;
    }

    /**
     * Get link to search publications of authority.
     *
     * @return string
     */
    public function getPublicationsUrl()
    {
        return "/Search/Results?sort=relevance&join=AND&type0[]=adv_search_author_corporation&bool0[]=AND&searchTypeTemplate=advanced&lookfor0[]=" . $this->getAuthorityId();
    }

}
