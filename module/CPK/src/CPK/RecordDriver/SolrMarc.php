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

    protected function getAll996Subfields()
    {
        $fields = [];
        $fieldsParsed = $this->getMarcRecord()->getFields('996');

        foreach ($fieldsParsed as $field) {
            $subfieldsParsed = $field->getSubfields();
            $subfields = [];
            foreach ($subfieldsParsed as $subfield) {
                $subfields[trim($subfield->getCode())] = $subfield->getData();
            }
            $fields[] = $subfields;
        }
        return $fields;
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
        unset($mappingsFor996['restricted']);

        $holdings = [];
        foreach ($fields as $currentField) {
            if (! $this->shouldBeRestricted($currentField, $restrictions)) {

                foreach ($mappingsFor996 as $variableName => $default996Mapping) {
                    if (! empty($currentField[$default996Mapping]))
                        $holding[$variableName] = $currentField[$default996Mapping];
                }

                $holding['id'] = $id;
                $holding['source'] = $source;
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
        return $this->getILSconfig()['Default996Mappings'];
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
        $overriden996Mappings = $this->getILSconfig()['Overriden996Mappings'];
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

    public function getParentRecordID()
    {
        return isset($this->fields['parent_id_str']) ? $this->fields['parent_id_str'] : [];
    }

    public function getAntikvariatyLink()
    {
        return isset($this->fields['external_links_str_mv'][0]) ? $this->fields['external_links_str_mv'][0] : false;
    }

    public function get856Links()
    {
        return isset($this->fields['links_from_856_str']) ? $this->fields['links_from_856_str'][0] : false;
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

        if (isset($phpResponse[0]['rating_url']))
            echo '<p>Hodnocení knihy:</p><img src="' . $phpResponse[0]['rating_url'] . '"/>';

        $commentArray = array();

        $i = 0;

        foreach ($phpResponse[0]['reviews'] as $review) {
            $com = new \stdClass();
            $com->firstname = $review->library_name;
            $com->created = $review->created;
            $com->comment = $review->html_text;

            $commentArray[$i] = $com;
            $i ++;
        }

        return $commentArray;
    }
}
