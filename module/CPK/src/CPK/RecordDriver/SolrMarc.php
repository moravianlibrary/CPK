<?php
namespace CPK\RecordDriver;

use MZKCommon\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{

    protected $ilsConfig = null;

    protected function getILSconfig()
    {
        if ($this->ilsConfig === null)
            $this->ilsConfig = $this->ils->getDriverConfig();

        return $this->ilsConfig;
    }

    public function getLocalId()
    {
        list ($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
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

    public function get773()
    {
    	$subfields = ['t', 'd', 'x', 'g', 'q', '9'];
    	$field773 = [];
    	foreach ($subfields as $subfield) {
    		$field773[$subfield] = $this->getFieldArray('773', array($subfield));
    	}

    	$resultArray = [];
    	foreach ($field773 as $subfieldKey => $subfieldValue) {
    		foreach ($subfieldValue as $intKey => $value) {
    			$resultArray[$intKey][$subfieldKey] = $value;
    		}
    	}

    	return $resultArray;
    }

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
    	$array = $this->getFieldArray('260', array('b'));
    	if (count($array) === 0) 
    		$array = $this->getFieldArray('264', array('b'));

    	return $array;
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
        $restrictions = $mappingsFor996['restricted'];

        if ($restrictions !== null)
            unset($mappingsFor996['restricted']);

        $ignoredKeyValsPairs = $mappingsFor996['ignoredVals'];

        if ($ignoredKeyValsPairs !== null) {
            unset($mappingsFor996['ignoredVals']);

            foreach ($ignoredKeyValsPairs as &$ignoredValue)
                $ignoredValue = array_map('trim', explode(',', $ignoredValue));
        }

        $toTranslate = [];

        // Here particular fields translation configuration takes place (see comments in MultiBackend.ini)
        if (isset($mappingsFor996['translate'])) {
            $toTranslateArray = array_map('trim', explode(',', $mappingsFor996['translate']));

            foreach ($toTranslateArray as $toTranslateElement) {
                list ($fieldToTranslate, $prependString) = explode(':', $toTranslateElement);

                if (empty($prependString))
                    $prependString = '';

                $toTranslate[$fieldToTranslate] = $prependString;
            }

        }

        $holdings = [];
        foreach ($fields as $currentField) {
            if (! $this->shouldBeRestricted($currentField, $restrictions)) {

                foreach ($mappingsFor996 as $variableName => $current996Mapping) {
                    if (! empty($currentField[$current996Mapping]) && ! $this->isIgnored($currentField[$current996Mapping], $current996Mapping, $ignoredKeyValsPairs)) {
                        $holding[$variableName] = $currentField[$current996Mapping];
                    }
                }

                foreach ($toTranslate as $fieldToTranslate => $prependString) {
                    if (! empty($holding[$fieldToTranslate]))
                        $holding[$fieldToTranslate] = $this->translate($prependString . $holding[$fieldToTranslate], null, $holding[$fieldToTranslate]);
                }

                $holding['id'] = $id;
                $holding['source'] = $source;

                // If is Aleph ..
                if (isset($this->getILSconfig()['Drivers'][$source]) && $this->getILSconfig()['Drivers'][$source] === 'Aleph') {
                    // If we have all we need
                    if (isset($holding['sequence_no']) && isset($holding['item_id']) && isset($holding['agency_id'])) {

                        $holding['item_id'] = $holding['agency_id'] . $holding['item_id'] . $holding['sequence_no'];

                        // instead of agency_id set bibId so that aleph driver knows what bibId he has to build the query on

                        $explodedId = explode('.', $id);
                        $bibId = array_pop($explodedId);
                        $holding['agency_id'] = $bibId;
                    } else {
                        // We actually cannot process Aleph holdings without complete item id ..
                        unset($holding['item_id']);
                    }
                }

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
    	return isset($this->fields['issnIsbnIsmn_search_str_mv'][0]) ? $this->fields['issnIsbnIsmn_search_str_mv'][0] : false;
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

        $client = new \Zend\Http\Client('http://cache.obalkyknih.cz/api/books');
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));

        $response = $client->send();


        $responseBody = $response->getBody();

        $phpResponse = json_decode($responseBody, true);

        $commentArray = array();

        $i = 0;

        foreach ($phpResponse[0]['reviews'] as $review) {
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
        $client = new \Zend\Http\Client('http://cache.obalkyknih.cz/api/books');
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));
        $response = $client->send();
        $responseBody = $response->getBody();
        $phpResponse = json_decode($responseBody, true);
        $bookid = $phpResponse[0]['book_id'];
        return $bookid;
    }


    /**
     * Get an array of summary strings for the record.
     *
     * @return string
     */
    public function getSummaryObalkyKnih()
    {
        $isbnArray = $this->getBibinfoForObalkyKnihV3();

        $isbnJson = json_encode($isbnArray);

        $client = new \Zend\Http\Client('http://cache.obalkyknih.cz/api/books');
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));

        $response = $client->send();

        $responseBody = $response->getBody();

        $phpResponse = json_decode($responseBody, true);

        if (isset($phpResponse[0]['annotation'])) {

            if ($phpResponse[0]['annotation']['html'] == null)
                return null;

            $anot = $phpResponse[0]['annotation']['html'];
            $source = $phpResponse[0]['annotation']['source'];

            return $anot . " - " . $source;
        }
        return null;
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
}
