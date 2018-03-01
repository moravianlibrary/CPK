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
        if (! isset($this->fields['pseudonym_name_display_mv']) || ! isset($this->fields['pseudonym_ids_display_mv'])) {
            return [];
        }
        return array_combine($this->fields['pseudonym_name_display_mv'], $this->fields['pseudonym_ids_display_mv']);
    }

    /**
     * Get authority's source.
     *
     * @return array
     */
    public function getSource()
    {
        return isset($this->fields['source_display_mv']) ? $this->fields['source_display_mv'] : [];
    }

    /**
     * Get the authority's name, shown as title of record.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        return rtrim($this->getPersonalName(), ',');
    }

    public function getBibinfoForObalkyKnihV3()
    {
        return ['auth_id' => $this->getAuthorityId()];
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
     * Get the bibliographic details of authority.
     *
     * @return string $details
     */
    public function getBibliographicDetails()
    {
        return isset($this->fields['bibliographic_details_display_mv']) ? $this->fields['bibliographic_details_display_mv'][0] : '';
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
