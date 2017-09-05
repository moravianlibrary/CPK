<?php
namespace CPK\RecordDriver;

use CPK\RecordDriver\SolrMarc as ParentSolrMarc;
use VuFind\RecordDriver\Response;
use Exception;

class SolrDictionary extends ParentSolrMarc
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
     * Get the authority's bibliographic details.
     *
     * @return array $field
     */
    public function getSummary()
    {
        $field = $this->getFieldArray('678', array('a'));
        return empty($field) ? '' : $field;
    }

    /**
     * Get the authority's name, shown as title of record.
     *
     * @return string
     */
    public function getTitle()
    {
        $field = $this->getFieldArray('150', array('a', 'd'));
        $name = empty($field) ? '' : $field[0];
        return $name;
    }

    /**
     * Get the bibliographic details of authority.
     *
     * @return string $details
     */
    public function getBibliographicDetails()
    {
        $field = $this->getFieldArray('678', array('a'));
        $details = empty($field) ? '' : $field[0];
        return $details;
    }
    
    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset ($this->fields ['format_display_mv']) ? $this->fields ['format_display_mv'] : [];
    }
}
