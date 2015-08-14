<?php
namespace CPK\RecordDriver;

use MZKCommon\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{

    const FIELD_996_SUBFIELDS = [
        'a',
        'b',
        'c',
        '9',
        'l',
        'd',
        'y',
        'v',
        'i',
        'p',
        's',
        'e',
        'f',
        'o'
    ];

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
        $holdings = $this->parseHoldingsFrom996field();

        return $holdings;

        // TODO Delete rest of the code after implemented remappedRecordIds elsewhere (getStatuses in MultiBackend ??)
        if ($this->ils === null)
            return [];

        $config = $this->ils->getDriverConfig();

        $remappedRecordIds = $config['RecordIdRemapped'];

        $recordSource = $this->getSourceId();

        foreach ($remappedRecordIds as $source => $fieldOfValidRecordId) {
            if ($source === $recordSource) {
                $recordIdField = $this->getMarcRecord()->getFields($fieldOfValidRecordId);

                if (empty($recordIdField) || empty($recordIdField[0]))
                    return [];

                $recordId = $recordIdField[0]->getData();
                if (empty($recordId))
                    return [];

                $toReturn = $this->holdLogic->getHoldings($source . '.' . $recordId, $filters);
                return $toReturn;
            }
        }

        return $this->holdLogic->getHoldings($this->getUniqueID(), $filters);
    }

    protected function parseHoldingsFrom996field()
    {
        $fieldsParsed = $this->getMarcRecord()->getFields('996');
        $fields = [];
        foreach ($fieldsParsed as $field) {
            $subfieldsParsed = $field->getSubfields();
            $subfields = [];
            foreach ($subfieldsParsed as $subfield) {
                $subfields[trim($subfield->getCode())] = $subfield->getData();
            }
            $fields[] = $subfields;
        }

        $config = $this->ils->getDriverConfig();
        $default996Mappings = $config['Default996Mappings'];

        $id = $this->getUniqueID();

        $holdings = [];
        foreach ($fields as $currentField) {

            foreach ($default996Mappings as $variableName => $default996Mapping) {
                if (! empty($currentField[$default996Mapping]))
                    $holding[$variableName] = $currentField[$default996Mapping];
            }

            $holding['id'] = $id;
            $holdings[] = $holding;
        }

        return $holdings;
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

        $phpResponse = json_decode($responseBody);

        echo '<p>Hodnocení knihy:</p><img src="' . $phpResponse[0]->rating_url . '"/>';

        $commentArray = array();

        $i = 0;

        foreach ($phpResponse[0]->reviews as $review) {
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
