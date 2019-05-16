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
            $searchSettings = null, $searchController = null, $searchRunner = null, $recordLoader = null
    ) {
        $this->searchController = $searchController;
        $this->searchRunner = $searchRunner;
        parent::__construct($mainConfig, $recordConfig, $searchSettings, $recordLoader);
    }

    /**
     * Get explanation.
     *
     * @return array $field
     */
    public function getSummary()
    {
        return isset ($this->fields ['explanation_display']) ? array($this->fields ['explanation_display']) : [];
    }

    /**
     * Get term author list
     *
     * @return array Term author list or empty array
     */
    public function getTermAuthors(){
      return isset($this->fields['author_term_display_mv'])? $this->fields['author_term_display_mv']: [];
    }

    /**
     * Get name, shown as title of record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset ($this->fields ['title']) ? $this->fields ['title'] : [];
    }

    /**
     * Get english term.
     *
     * @return string
     */
    public function getEnglish()
    {
        return isset ($this->fields ['english_display']) ? $this->fields ['english_display'] : [];
    }

    /**
     * Get explanation.
     *
     * @return string
     */
    public function getExplanation()
    {
        return isset ($this->fields ['explanation_display']) ? $this->fields ['explanation_display'] : [];
    }

    /**
     * Get relative terms.
     *
     * @return array
     */
    public function getRelatives()
    {
        return isset ($this->fields ['relative_display_mv']) ? $this->fields ['relative_display_mv'] : [];
    }

    /**
     * Get alternative terms.
     *
     * @return array
     */
    public function getAlternatives()
    {
        return isset ($this->fields ['alternative_display_mv']) ? $this->fields ['alternative_display_mv'] : [];
    }

    /**
     * Get source.
     *
     * @return string
     */
    public function getSource()
    {
        return isset ($this->fields ['source_display']) ? $this->fields ['source_display'] : [];
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

    /**
     * Get handler for related
     *
     * @return array
     */
    public function getFilterParamsForRelated()
    {
        return ['handler' => 'morelikethisdictionary'];
    }
}
