<?php
namespace CPK\RecordDriver;

use VuFind\RecordDriver\SolrMarc as ParentSolrMarc;
use Zend\Http\Client\Adapter\Exception\TimeoutException as TimeoutException;

class SolrMarc extends ParentSolrMarc
{

    protected $ilsConfig = null;

    /**
     * These Solr fields should be used for snippets if available (listed in order
     * of preference).
     *
     * @var array
     */
    protected $preferredSnippetFields = [
        'toc_txt_mv', 'fulltext'
    ];

    /**
     * These Solr fields should NEVER be used for snippets.  (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     *
     * @var array
     */
    protected $forbiddenSnippetFields = [
        'author', 'author-letter', 'title', 'title_short', 'title_full',
        'title_auth', 'title_sub', 'spelling', 'id',
        'ctrlnum', 'title_autocomplete', 'author_autocomplete',
        'titleSeries_search_txt_mv', 'authorCorporation_search_txt_mv',
        'author_display', 'title_display', 'author_facet_str_mv', 'author-letter',
        'author_sort_str', 'sourceTitle_search_txt_mv', 'author_str', 'spellingShingle',
        'source_title_facet_str', 'title_fullStr', 'title_display', 'title_sort',
        'title_auth', 'author_search', 'publishDate'
    ];

    protected $reverse = false;
    protected $sortFields = array();

    protected function getILSconfig()
    {
        if ($this->ilsConfig === null)
            $this->ilsConfig = $this->ils->getDriverConfig();

        return $this->ilsConfig;
    }

    protected $recordLoader;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     * @param \VuFind\RecordLoader
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $recordLoader
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->recordLoader = $recordLoader;
    }

    /**
     * Get an annotation
     *
     * @return string
     */
    public function getAnnotation() {
        $annotation = $this->getFieldArray('520', array('a'));
        return !empty($annotation) ? $annotation[0] : false;
    }

    /**
     * Get patent info for export in txt
     */
    public function getPatentInfo() {
        $patentInfo = [];
        $patentInfo['country'] = $this->getFieldArray('013', array('b'))[0];
        $patentInfo['type'] = $this->getFieldArray('013', array('c'))[0];
        $patentInfo['id'] = $this->getFieldArray('013', array('a'))[0];
        $patentInfo['publish_date'] = $this->getFieldArray('013', array('d'))[0];

        if(empty($patentInfo)) {
            return false;
        }

        $patentInfoText = $this->renderPatentInfo($patentInfo);

        return $patentInfoText;
    }

    /**
     * Render patent info to export file
     *
     * @param $patentInfo array with patent info
     * @return string rendered string
     */
    public function renderPatentInfo($patentInfo) {
        $patentInfoText = '';
        $patentInfoText .= $this->translate('Patent') . ': ' . $patentInfo['country'] . ', ';
        switch ($patentInfo['type']) {
            case 'B6':
                $patentInfoText .= $this->translate('patent_file'); break;
            case 'A3':
                $patentInfoText .= $this->translate('app_invention'); break;
            case 'U1':
                $patentInfoText .= $this->translate('utility_model'); break;
            default:
                $patentInfoText .= $this->translate('unknown_patent_type'); break;
        }
        $patentInfoText .= ', ' . $patentInfo['id'] . ', ' . $patentInfo['publish_date'] . "\r\n";
        return $patentInfoText;
    }

    public function getLocalId()
    {
        list ($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    public function getChildrenIds()
    {
        return isset($this->fields['local_ids_str_mv']) ? $this->fields['local_ids_str_mv'] : [];
    }

    public function getSourceId()
    {
        list ($source, $localId) = explode('.', $this->getUniqueID());
        return $source;
    }

    protected function getExternalID()
    {
        return $this->getLocalId();
    }

    public function get996(array $subfields)
    {
        return $this->getFieldArray('996', $subfields);
    }

    public function get866()
    {
        $field866 = $this->getFieldArray('866', array('s', 'x'));
        return $field866;
    }

    /**
    * uses setting from config.ini => External links
    * @return array  [] => [
    *          [institution] = institution,
    *          [url] = external link to catalogue,
    *          [display] => link to be possibly displayed]
    *          [id] => local identifier of record
    *
    */
    public function getExternalLinks() {

        list($ins, $id) = explode('.' , $this->getUniqueID());
        //FIXME temporary
        if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);
    $linkBase = $this->recordConfig->ExternalLinks->$ins;

        if (empty($linkBase)) {
            return array(
                       array('institution' => $ins,
                             'url' => '',
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $finalID = $this->getExternalID();
        if (!isset($finalID)) {
            return array(
                       array('institution' => $ins,
                             'url' => '',
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $confEnd  = $ins . '_end';
        $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        if (!isset($linkEnd) ) $linkEnd = '';
        $externalLink =  $linkBase . $finalID . $linkEnd;
        return array(
                   array('institution' => $ins,
                         'url' => $externalLink,
                         'display' => $externalLink,
                         'id' => $id));
    }

    /**
     * Get field of 7xx
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     *
     * @return boolean|array
     */
    public function get7xxField($field, array $subfields = null) {
        $array = [];
        $notFalseSubfields = 0;
        foreach ($subfields as $subfield) {
            $result = $this->getFieldArray($field, array($subfield));
            if (count($result))
                ++$notFalseSubfields;

            $array[$subfield] = $result;
        }

        if ($notFalseSubfields === 0)
            return false;

        $resultArray = [];
        foreach ($array as $subfieldKey => $subfieldValue) {
            foreach ($subfieldValue as $intKey => $value) {
                $resultArray[$intKey][$subfieldKey] = $value;
            }
        }

        return $resultArray;
    }

    public function getF773_display()
    {
        return isset($this->fields['f773_display']) ? $this->fields['f773_display'] : '';
    }

    public function getLink773_str()
    {
        return isset($this->fields['link773_str']) ? $this->fields['link773_str'] : '';
    }


    protected function getAll996Subfields()
    {
        $fields = [];
        $fieldsParsed = $this->getMarcRecord()->getFields('996');

        foreach ($fieldsParsed as $field) {
            $subfieldsParsed = $field->getSubfields();
            $subfields = [];
            foreach ($subfieldsParsed as $subfield) {
                $subfieldCode = trim($subfield->getCode());

                // If is this subfield already set, ignore next value .. probably incorrect OAI data
                if (! isset($subfields[$subfieldCode]))
                    $subfields[$subfieldCode] = $subfield->getData();
            }
            $fields[] = $subfields;
        }
        return $fields;
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return isset($this->fields['publisher_display_mv']) ? $this->fields['publisher_display_mv'] : [];
    }

    public function getFormats()
    {
        return isset($this->fields['cpk_detected_format_txtF_mv']) ? $this->fields['cpk_detected_format_txtF_mv'] : [];
    }

    /**
     * This is deprecated as we will never want real time holdings in order to parse cached ones from Solr index
     * in record's 996 field & statuses info later by AJAX queries
     *
     * @deprecated
     *
     */
    public function getRealTimeHoldings($filters = array())
    {
        return $this->parseHoldingsFrom996field($filters);
        /* This was original code:
        return $this->hasILS() ? $this->holdLogic->getHoldings(
            $this->getUniqueID(), $this->getConsortialIDs()
        ) : [];*/
    }

    public function getHoldingFilters()
    {
        return array();
    }

    public function getAvailableHoldingFilters()
    {
        return array();
    }

    /**
     * Returns array of holdings parsed via indexed 996 fields.
     *
     * TODO: Implement filtering
     *
     * @return array
     */
    protected function parseHoldingsFrom996field($filters = [])
    {
        $id = $this->getUniqueID();
        $fields = $this->getAll996Subfields();

        $source = $this->getSourceId();

        $mappingsFor996 = $this->getMappingsFor996($source);

        // Remember to unset all arrays at that would log an error providing array as another's array key
        if (isset($mappingsFor996['restricted'])) {
            $restrictions = $mappingsFor996['restricted'];

            unset($mappingsFor996['restricted']);
        }

        if (isset($mappingsFor996['ignoredVals'])) {
            $ignoredKeyValsPairs = $mappingsFor996['ignoredVals'];

            unset($mappingsFor996['ignoredVals']);

            foreach ($ignoredKeyValsPairs as &$ignoredValue)
                $ignoredValue = array_map('trim', explode(',', $ignoredValue));
        }

        if (isset($mappingsFor996['toUpper'])) {
            $toUpper = $mappingsFor996['toUpper'];

            // We will take care of the upperation process in the closest iteration
            unset($mappingsFor996['toUpper']);
        } else {
            // We will iterate over this, so don't let it be null
            $toUpper = [];
        }

        $toTranslate = [];

        // Here particular fields translation configuration takes place (see comments in MultiBackend.ini)
        if (isset($mappingsFor996['translate'])) {

            $toTranslateArray = $mappingsFor996['translate'];

            unset($mappingsFor996['translate']);

            foreach ($toTranslateArray as $toTranslateElement) {
                $toTranslateElements = explode(':', $toTranslateElement);

                $fieldToTranslate = $toTranslateElements[0];

                if (count($toTranslateElements) < 2)
                    $prependString = '';
                else
                    $prependString = $toTranslateElements[1];

                $toTranslate[$fieldToTranslate] = $prependString;
            }

        }

        $this->sortFields($fields, $source);

        if ((isset($this->fields['format_display_mv'][0])) && ($this->fields['format_display_mv'][0] == '0/PERIODICALS/')) {
            usort($fields, function($a, $b) {
                $found = false;
                $sortFields = array('y', 'v', 'i');
                foreach ($sortFields as $sort) {
                    if (! isset($a[$sort])) {
                        $a[$sort] = '';
                    }
                    if (! isset($b[$sort])) {
                        $b[$sort] = '';
                    }
                    if ($a[$sort] != $b[$sort]) {
                        $pattern = '/(\d+)(.+)?/';
                        $first = preg_replace($pattern, '$1', $a[$sort]);
                        $second = preg_replace($pattern, '$1', $b[$sort]);
                        $found = true;
                        break;
                    }
                }
                return $found ? ($first < $second) : false;
            });
        }

        $holdings = [];
        foreach ($fields as $currentField) {
            if (! $this->shouldBeRestricted($currentField, $restrictions)) {
                unset($holding);
                $holding = array();

                foreach ($mappingsFor996 as $variableName => $current996Mapping) {
                    // Here it omits unset values & values, which are desired to be ignored by their presence in ignoredVals MultiBackend.ini's array
                    if (! empty($currentField[$current996Mapping]) && ! $this->isIgnored($currentField[$current996Mapping], $current996Mapping, $ignoredKeyValsPairs)) {
                        $holding[$variableName] = $currentField[$current996Mapping];
                    }
                }

                // Translation takes place from translate
                foreach ($toTranslate as $fieldToTranslate => $prependString) {
                    if (! empty($holding[$fieldToTranslate]))
                        $holding[$fieldToTranslate] = $this->translate($prependString . $holding[$fieldToTranslate], null, $holding[$fieldToTranslate]);
                }

                foreach ($toUpper as $fieldToBeUpperred) {
                    if (! empty($holding[$fieldToBeUpperred]))
                        $holding[$fieldToBeUpperred] = strtoupper($holding[$fieldToBeUpperred]);
                }

                $holding['id'] = $id;
                $holding['source'] = $source;

                // If is Aleph ..
                if (isset($this->getILSconfig()['Drivers'][$source]) && $this->getILSconfig()['Drivers'][$source] === 'Aleph') {
                    // If we have all we need
                    if (isset($holding['sequence_no']) && isset($holding['item_id']) && isset($holding['agency_id'])) {

                        $holding['item_id'] = $holding['agency_id'] . $holding['item_id'] . $holding['sequence_no'];

                        unset($holding['agency_id']);
                    } else {
                        // We actually cannot process Aleph holdings without complete item id ..
                        unset($holding['item_id']);
                    }
                }
                $holding['w_id'] = array_key_exists('w', $currentField) ? $currentField['w'] : null;

                $holding['sigla'] = array_key_exists('e', $currentField) ? $currentField['e'] : null;

                $holdings[] = $holding;
            }
        }

        return $holdings;
    }

    /**
     * Returns array of key->value pairs where the key is variableName &
     * value is mapped subfield.
     *
     * This method basically fetches default996Mappings & overrides there
     * these variableNames, which are present in overriden996mappings.
     *
     * For more info see method getOverriden996Mappings
     *
     * @return mixed null | array
     */
    protected function getMappingsFor996($source)
    {
        $default996Mappings = $this->getDefault996Mappings();

        $overriden996Mappings = $this->getOverriden996Mappings($source);

        if ($overriden996Mappings === null)
            return $default996Mappings;

            // This will override all identical entries
        $merged = array_reverse(array_merge($default996Mappings, $overriden996Mappings));

        // We shouldn't set value where is the subfield the same as in any other overriden default variableName
        return $this->array_unique_with_nested_arrays($merged);
    }

    /**
     * This function is similar to array_unique, but with support
     * for nested arrays to make sure no error occurs.
     *
     * @param array $mergedOnes
     * @return array
     */
    protected function array_unique_with_nested_arrays($mergedOnes)
    {
        $nestedArrays = [];
        foreach ($mergedOnes as $key => $value) {
            if ($value !== null && is_array($value)) {
                $nestedArrays[$key] = $value;
                unset($mergedOnes[$key]);
            }
        }

        $toReturn = array_unique($mergedOnes);

        foreach ($nestedArrays as $key => $value) {
            // We won't do callback here as e.g. array 'restricted' may have multiple key-value pair with duplicate values
            $toReturn[$key] = $value;
        }

        return $toReturn;
    }

    /**
     * Returns array of config to process 996 mappings with.
     *
     * Returns null if not found.
     *
     * @return mixed null | array
     */
    protected function getDefault996Mappings()
    {
        $ilsConfig = $this->getILSconfig();

        return isset($ilsConfig['Default996Mappings']) ? $ilsConfig['Default996Mappings'] : [];
    }

    /**
     * Returns array of config with which it is desired to override the default one.
     *
     * Also returnes null if no overriden config is found.
     *
     * @return mixed null | array
     */
    protected function getOverriden996Mappings($source)
    {
        $ilsConfig = $this->getILSconfig();
        $overriden996Mappings = isset($ilsConfig['Overriden996Mappings']) ? $ilsConfig['Overriden996Mappings'] : [];
        foreach ($overriden996Mappings as $institution => $configToOverrideWith) {
            if ($source === $institution)
                return $this->ilsConfig[$configToOverrideWith];
        }
        return null;
    }

    /**
     * Returns true only if in $subfields is found key->value pair identical
     * with any key->value pair in restrictions.
     *
     * @param array $subfields
     * @param array $restrictions
     * @return boolean
     */
    protected function shouldBeRestricted($subfields, $restrictions)
    {
        if ($restrictions === null)
            return false;

        foreach ($restrictions as $key => $restrictedValue) {
            if (isset($subfields[$key]) && $subfields[$key] == $restrictedValue)
                return true;
        }

        return false;
    }

    /**
     * Returns true only if $ignoredKeyValsPairs has any restriction on current key
     * and the restriction on current key has at least one value in it's array of
     * ignored values identical with passed $subfieldValue.
     *
     * Otherwise returns false.
     *
     * @param string $subfieldValue
     * @param string $subfieldKey
     * @param array $ignoredKeyValsPairs
     * @return boolean
     */
    protected function isIgnored($subfieldValue, $subfieldKey, $ignoredKeyValsPairs)
    {
        if ($ignoredKeyValsPairs === null)
            return false;

        if (isset($ignoredKeyValsPairs[$subfieldKey]))
            return array_search($subfieldValue, $ignoredKeyValsPairs[$subfieldKey]) !== false;

        return false;
    }

    /**
     * Returns perent record ID from SOLR
     *
     * @return  string
     */
    public function getParentRecordID()
    {
        return isset($this->fields['parent_id_str']) ? $this->fields['parent_id_str'] : [];
    }

    /**
     * Returns link to antikvariaty from SOLR
     *
     * @return  string
     */
    public function getAntikvariatyLink()
    {
        return isset($this->fields['external_links_str_mv'][0]) ? $this->fields['external_links_str_mv'][0] : false;
    }

    /**
     * Returns links from SOLR indexed from 856
     *
     * @return  string
     */
    public function get856Links()
    {
        return isset($this->fields['url']) ? $this->fields['url'] : false;
    }

    /**
     * Returns data from SOLR representing links and metadata to access SFX
     *
     * @return  array
     */
    public function get866Data()
    {
        return isset($this->fields['sfx_links']) ? $this->fields['sfx_links'] : [];
    }

    /**
     * Returns document range info from field 300
     *
     * @return  array
     */
    public function getRange()
    {
        return $this->getFieldArray('300');
    }

    /**
     * Returns document release info from field 250
     *
     * @return  array
     */
    public function getRelease()
    {
        return $this->getFieldArray('250');
    }

    /**
     * Returns all ISSNs, ISBNs and ISMNs from SOLR
     *
     * @return  string
     */
    public function getIsn()
    {
        foreach (array('isbn', 'issn', 'ismn_int_mv') as $field) {
            if (isset($this->fields[$field][0])) return $this->fields[$field][0];
        }
        return false;
    }

    public function getBibinfoForObalkyKnih()
    {
        $bibinfo = array(
            "authors" => array($this->getPrimaryAuthor()),
            "title" => $this->getTitle(),
            "ean" => $this->getEAN()
        );
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $year = $this->getPublicationDates();
        if (!empty($year)) {
            $bibinfo['year'] = $year[0];
        }
        return $bibinfo;
    }

    /**
     * Get comments on obalkyknih.cz associated with this record.
     *
     * @return array
     */
    public function getObalkyKnihComments()
    {
        $isbnArray = $this->getBibinfoForObalkyKnihV3();

        $isbnJson = json_encode($isbnArray);

        $cacheUrl = !isset($this->mainConfig->ObalkyKnih->cacheUrl)
            ? 'https://cache.obalkyknih.cz' : $this->mainConfig->ObalkyKnih->cacheUrl;
        $apiBooksUrl = $cacheUrl . "/api/books";
        $client = new \Zend\Http\Client($apiBooksUrl);
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));

        $response = $client->send();


        $responseBody = $response->getBody();

        $phpResponse = json_decode($responseBody, true);

        $commentArray = array();

        $i = 0;

        if (! empty($phpResponse) && ! empty($phpResponse[0]['reviews'])) foreach ($phpResponse[0]['reviews'] as $review) {
            $com = new \stdClass();
            $com->library = $review['library_name'];
            $com->created = $review['created'];
            $com->comment = $review['html_text'];

            $commentArray[$i] = $com;
            $i ++;
        }

        return $commentArray;
    }

    /**
     * Get bookid on obalkyknih.cz associated with this record.
     *
     * @return bookid
     */
    public function getObalkyKnihBookId()
    {
        $isbnArray = $this->getBibinfoForObalkyKnihV3();
        $isbnJson = json_encode($isbnArray);

        $cacheUrl = !isset($this->mainConfig->ObalkyKnih->cacheUrl)
            ? 'https://cache.obalkyknih.cz' : $this->mainConfig->ObalkyKnih->cacheUrl;
        $apiBooksUrl = $cacheUrl . "/api/books";
        $client = new \Zend\Http\Client($apiBooksUrl);
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));
        $response = $client->send();
        $responseBody = $response->getBody();
        $phpResponse = json_decode($responseBody, true);
        $bookid = (empty($phpResponse) || empty($phpResponse[0]['book_id'])) ? '' : $phpResponse[0]['book_id'];
        return $bookid;
    }


    /**
     * Save this record to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Tag array to associate with record (optional)</li>
     *    <li>notes - Notes to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param \VuFind\Db\Row\User $user   The user saving the record
     *
     * @return void
     */
    public function saveToFavorites($params, $user)
    {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        // Get or create a list object as needed:
        $listId = isset($params['list']) ? $params['list'] : '';
        $table = $this->getDbTable('UserList');
        if (empty($listId) || $listId == 'NEW') {
            $list = $table->getNew($user);
            $list->title = $this->translate('Primary List');
            $list->save($user);
        } else {
            $list = $table->getExisting($listId);
            // Validate incoming list ID:
            if (!$list->editAllowed($user)) {
                throw new \VuFind\Exception\ListPermission('Access denied.');
            }
            $list->rememberLastUsed(); // handled by save() in other case
        }

        // Get or create a resource object as needed:
        $resourceTable = $this->getDbTable('Resource');
        $resource = $resourceTable->findResource(
                $this->getUniqueId(), $this->getResourceSource(), true, $this
        );

        // Add the information to the user's account:
        $user->saveResource(
                $resource, $list,
                isset($params['mytags']) ? $params['mytags'] : [],
                isset($params['notes']) ? $params['notes'] : ''
        );

        return ['listId' => $list->id];
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getTitle();
    }

    public function getRecordType()
    {
        return isset($this->fields['recordtype']) ? $this->fields['recordtype'] : '';
    }

    public function getNonStandardISBN()
    {
        return $this->getFieldArray('902');
    }

    public function getCitationRecordType()
    {
        return isset($this->fields['citation_record_type_str']) ? $this->fields['citation_record_type_str'] : '';
    }


    /**
     * From all 996's fields get only the searched item's field.
     *
     * @param string $item_id
     * @return array
     */
    public function getItem996($item_id = null)
    {
        if (empty($item_id)) return null;
        $all996subfields = $this->getAll996Subfields();
        foreach ($all996subfields as $item996) {
            if ($item_id === $item996['b']) {
                return $item996;
            }
        }
        return null;
    }

    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // Only process snippets if the setting is enabled:
        if ($this->snippet) {
            // First check for preferred fields:
            foreach ($this->preferredSnippetFields as $current) {
                if (isset($this->highlightDetails[$current][0])) {
                    return [
                        'snippet' => $this->highlightDetails[$current][0],
                        'caption' => $this->getSnippetCaption($current)
                    ];
                }
            }
        }

        // If we got this far, no snippet was found:
        return false;
    }

    public function getEAN()
    {
        return (!empty($this->fields['ean_isn_mv']) ? $this->fields['ean_isn_mv'][0] : null);
    }

    protected function getCNB()
    {
        return isset($this->fields['nbn']) ? $this->fields['nbn'] : null;
    }

    public function getBibinfoForObalkyKnihV3()
    {
        $bibinfo = array();
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $ean = $this->getEAN();
        if (!empty($ean) && !array_key_exists('isbn', $bibinfo)) {
            $bibinfo['isbn'] = $ean;
        }
        $cnb = $this->getCNB();
        if (isset($cnb)) {
            $bibinfo['nbn'] = $cnb;
        } else {
            $prefix = 'BOA001';
            $bibinfo['nbn'] = $prefix . '-' . str_replace('-', '', $this->getUniqueID());
        }
        return $bibinfo;
    }

    /**
     * Get authority ID of main author.
     *
     * @return string
     */
    public function getMainAuthorAuthorityRecordId()
    {
        return isset($this->fields['author_authority_id_display']) ? $this->fields['author_authority_id_display'] : false;
    }

    public function getAvailabilityID() {
        if (isset($this->fields['availability_id_str'])) {
            return $this->fields['availability_id_str'];
        } else {
            return $this->getUniqueID();
        }
    }

    /**
     * Returns name of the Author to display
     *
     * @return string|NULL
     */
    public function getDisplayAuthor()
    {
        if (isset($this->fields['author_display']))
            return $this->fields['author_display'];

        return null;
    }

    /**
     * Get an array of authority Ids of all secondary authors in the same order
     * and amount as getSecondaryAuthors() method from author2 solr field.
     *
     * @return array
     */
    public function getSecondaryAuthoritiesRecordIds()
    {
        return isset($this->fields['author2_authority_id_display_mv'])
        ? $this->fields['author2_authority_id_display_mv']
        : [];
    }

    public function getISSNFromMarc()
    {
        $issn = $this->getFieldArray('022', array('a'));
        return $issn;
    }

    /**
     * There are rules how to sort holdings in some special cases.
     * Set $this->reverse and $this->sortFields.
     */
    private function sortFields(&$fields, $source) {
        if (($source == 'kfbz') &&
                (isset($this->fields['format_display_mv'][0])) &&
                ($this->fields['format_display_mv'][0] == '0/BOOKS/')) {
            $this->reverse = false;
            $this->sortFields = array('l',);
            usort($fields, array($this, 'sortLogic'));
        }

        if ((isset($this->fields['format_display_mv'][0])) && ($this->fields['format_display_mv'][0] == '0/PERIODICALS/')) {
            $this->reverse = true;
            $this->sortFields = array('y', 'v', 'i');
            usort($fields, array($this, 'sortLogic'));
        }
    }

    /**
     * The comparison function for usort, must return an integer <, =, or > than 0 if the first
     * argument is <, =, or > than the second argument.
     *
     * Uses array $this->sortFields Fields from 996, used to sorting.
     * Uses @param boolean $this->reverse Reverse the result.
     *
     * @param $a, $b
     *
     * @return integer
     */
    private function sortLogic($a, $b) {
        $found = false;
        $first = $second = '';
        foreach ($this->sortFields as $sort) {
            if (! isset($a[$sort])) {
                $a[$sort] = '';
            }
            if (! isset($b[$sort])) {
                $b[$sort] = '';
            }
            if ($a[$sort] != $b[$sort]) {
                $pattern = '/(\d+)(.+)?/';
                $first = preg_replace($pattern, '$1', $a[$sort]);
                $second = preg_replace($pattern, '$1', $b[$sort]);
                $found = true;
                break;
            }
        }
        $ret = $this->reverse ? ($first < $second) : ($first > $second);
        return $found ? $ret : false;
    }

    public function getScales()
    {
        $scales = $this->getFieldArray('255', array('a'));
        return $scales;
    }

    public function getMpts()
    {
        $field024s = $this->getFieldArray('024', array('a', '2'), false); // Mezinárodní patentové třídění
        $mpts = [];
        $count = count($field024s);
        if ($count) {
            for ($i = 0; $i < $count; $i++) {
                if (isset($field024s[$i+1])) {
                    if ($field024s[$i+1] == 'MPT') {
                        $mpts[] = $field024s[$i];
                    }
                }
            }
        }
        return $mpts;
    }

    /**
     * Get handler for related
     *
     * @return array
     */
    public function getFilterParamsForRelated()
    {
        return ['handler' => 'morelikethis'];
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary($searchAlsoInParentRecord = true)
    {
        /**
         * @var array   summary
         */
        $summary = isset($this->fields['summary_display_mv']) ? $this->fields['summary_display_mv'] : null;
        //nothing found and allowed to search in parent
        if ($searchAlsoInParentRecord && !$summary){
            $summary = ($parent = $this->getParentRecordDriver()) ? $parent->getSummary(false) : null;
        }
        //return the summary
        return $summary;
    }

    public function getMonographicSeries($searchAlsoInParentRecord = true)
    {
        $series = $this->fields['monographic_series_display_mv'] ?: false;
        if (! $series && $searchAlsoInParentRecord) {
            $series = $this->getParentRecordDriver()->getMonographicSeries(false);
        }
        return $series;
    }

    public function getMonographicSeriesUrl(string $serie)
    {
        $mainSerie = explode("|", $serie)[0];
        return '/Search/Results?lookfor0[]=' . urlencode($mainSerie)
            . '&amp;type0[]=adv_search_monographic_series&amp;join=AND&amp;searchTypeTemplate=advanced&amp;page=1&amp;bool0[]=AND';
    }

    public function getMonographicSeriesTitle(string $serie)
    {
        return implode(" | ", explode("|", $serie));
    }

    public function getZiskejBoolean() : bool
    {

        return $this->fields['ziskej_boolean'] ?? false;
    }
}
