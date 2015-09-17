<?php
namespace CPK\RecordDriver;

use CPK\RecordDriver\SolrMarc as ParentSolrMarc;
use VuFind\XSLT\Import\VuFind;
use VuFind\RecordDriver\Response;

class SolrDublinCore extends ParentSolrMarc
{

    protected $ilsConfig = null;

    protected function getILSconfig()
    {
        if ($this->ilsConfig === null)
            $this->ilsConfig = $this->ils->getDriverConfig();

        return $this->ilsConfig;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  In this case, $data is a Solr record
     * array containing Dublin Core data.
     *
     * @return void
     */
    public function setRawData($data)
    {
        $this->fields = $data;

    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        return [];
    }

    /**
     * Get access to the raw File_MARC object.
     *
     * @return \File_MARCBASE
     */
    public function getMarcRecord()
    {
        return [];
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field.  If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     *
     * @return array
     */
    protected function getFieldArray($field, $subfields = null, $concat = true)
    {
        return [];
    }

    /**
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        return "";
    }

    /**
     * Get the item's publication information
     *
     * @param string $subfield The subfield to retrieve ('a' = location, 'c' = date)
     *
     * @return array
     */
    protected function getPublicationInfo()
    {
        return [];
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        return [];
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:subject');
        $ret = [];
        foreach ($value as $part) {
            $ret[] = (string) $part;
        }
        return empty($value) ? [] : $ret;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        return [];
    }

    protected function getAll996Subfields()
    {
        return [];
    }

    /**
     * Get the bibliographic level of the current record.
     *
     * @return string
     */
    public function getBibliographicLevel()
    {
        return "Unknown";
    }

    public function getFormats()
    {
        return isset($this->fields['format_display_mv']) ? $this->fields['format_display_mv'] : [];
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title']) ? $this->fields['title'] : '';
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return isset($this->fields['title_sub']) ? $this->fields['title_sub'] : '';
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return [];
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        return isset($this->fields['container_title']) ? $this->fields['container_title'] : '';
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        return [];
    }

    /**
     * Deduplicate author information into associative array with main/corporate/
     * secondary keys.
     *
     * @return array
     */
    public function getDeduplicatedAuthors()
    {
        $authors = [
            'main' => $this->getPrimaryAuthor(),
            'corporate' => $this->getCorporateAuthor(),
            'secondary' => $this->getSecondaryAuthors()
        ];

        // The secondary author array may contain a corporate or primary author;
        // let's be sure we filter out duplicate values.
        $duplicates = [];
        if (!empty($authors['main'])) {
                $duplicates[] = $authors['main'];
                }
                if (!empty($authors['corporate'])) {
                    $duplicates[] = $authors['corporate'];
                }
                if (!empty($duplicates)) {
                    $authors['secondary'] = array_diff($authors['secondary'], $duplicates);
                }

                return $authors;
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:creator');
        return empty($value) ? "" : (string) $value[0];
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:language');
        $ret = [];
        foreach ($value as $part) {
            $ret[] = (string) $part;
        }
        return empty($value) ? [] : $ret;
    }

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers() and getPlacesOfPublication().
     *
     * @return array
     */
    public function getPublicationDetails()
    {
        $places = $this->getPlacesOfPublication();
        $names = $this->getPublishers();
        $dates = $this->getHumanReadablePublicationDates();

        $i = 0;
        $retval = [];
        while (isset($places[$i]) || isset($names[$i]) || isset($dates[$i])) {
            // Build objects to represent each set of data; these will
            // transform seamlessly into strings in the view layer.
            $retval[] = new Response\PublicationDetails(
                    isset($places[$i]) ? $places[$i] : '',
                    isset($names[$i]) ? $names[$i] : '',
                    isset($dates[$i]) ? $dates[$i] : ''
            );
            $i++;
        }

        return $retval;
    }

    /**
     * Get the item's places of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return [];
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:publisher');
        $ret = [];
        foreach ($value as $part) {
            $ret[] = (string) $part;
        }
        return empty($value) ? [] : $ret;
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:date');
        $ret = [];
        foreach ($value as $part) {
            $ret[] = (string) $part;
        }
        return empty($value) ? [] : $ret;
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return isset($this->fields['edition']) ? $this->fields['edition'] : '';
    }

    /**
     * Get an array of all series names containing the record.  Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        return parent::getSeries();
    }

    /**
     * Does the OpenURL configuration indicate that we should display OpenURLs in
     * the specified context?
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    public function openURLActive($area)
    {
        return parent::openURLActive($area);
    }

    /**
     * Get all record links related to the current record. Each link is returned as
     * array.
     * Format:
     * array(
     *        array(
     *               'title' => label_for_title
     *               'value' => link_name
     *               'link'  => link_URI
     *        ),
     *        ...
     * )
     *
     * @return null|array
     */
    public function getAllRecordLinks()
    {
        // Load configurations:
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
        ? explode(',', $this->mainConfig->Record->marc_links) : [];
        $useVisibilityIndicator
        = isset($this->mainConfig->Record->marc_links_use_visibility_indicator)
        ? $this->mainConfig->Record->marc_links_use_visibility_indicator : true;

        $retVal = [];
        foreach ($fieldsNames as $value) {
            $value = trim($value);
            $fields = $this->getMarcRecord()->getFields($value);
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    // Check to see if we should display at all
                    if ($useVisibilityIndicator) {
                        $visibilityIndicator = $field->getIndicator('1');
                        if ($visibilityIndicator == '1') {
                            continue;
                        }
                    }

                    // Get data for field
                    $tmp = $this->getFieldData($field);
                    if (is_array($tmp)) {
                        $retVal[] = $tmp;
                    }
                }
            }
        }
        return empty($retVal) ? null : $retVal;
    }

    public function get856Links()
    {
        $retVal[] = isset($this->fields['url']) ? $this->fields['url'][0] : false;
        return $retVal;
    }

    public function getParentRecordID()
    {
        return isset($this->fields['parent_id_str']) ? $this->fields['parent_id_str'] : [];
    }

    /**
     * Get hierarchical place names (MARC field 752)
     *
     * Returns an array of formatted hierarchical place names, consisting of all
     * alpha-subfields, concatenated for display
     *
     * @return array
     */
    public function getHierarchicalPlaceNames()
    {
        $placeNames = [];
        return $placeNames;
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:description');
        $ret = [];
        foreach ($value as $part) {
            $ret[] = (string) $part;
        }
        return empty($value) ? [] : $ret;
    }

    /**
     * Get the first call number associated with the record (empty string if none).
     *
     * @return string
     */
    public function getCallNumber()
    {
        $all = $this->getCallNumbers();
        return isset($all[0]) ? $all[0] : '';
    }

    /**
     * Get all call numbers associated with the record (empty string if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        $fullrecord = $this->fields['fullrecord'];
        $dc = simplexml_load_string($fullrecord);
        $value = $dc->xpath('//dc:identifier');
        $ret = [];
        foreach ($value as $part) {
            if (! is_int(strpos((string) $part, "signature:"))) continue;
            $ret[] = str_replace("signature:", "", (string) $part);
        }
        return empty($value) ? [] : $ret;
    }

    public function getRelease()
    {
        //return $this->getFieldArray('250');
        return [];
    }

    public function getRange()
    {
        //return $this->getFieldArray('300');
        return [];
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        // Special case for Dublin Core:
        if ($format == 'oai_dc') {
            $fullrecord = $this->fields['fullrecord'];
            return $fullrecord;
        }

        // Try the parent method:
        return parent::getXML($format, $baseUrl, $recordLink);
    }

}
