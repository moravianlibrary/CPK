<?php
namespace CPK\RecordDriver;

use CPK\RecordDriver\SolrMarc as ParentSolrMarc;
use VuFind\XSLT\Import\VuFind;
use VuFind\RecordDriver\Response;

class SolrAuthority extends ParentSolrMarc
{
    /**
     * Get the full name of authority.
     *
     * @return string
     */
    public function getPersonalName()
    {
        $field = $this->getFieldArray('100', array('a', 'd'));
        $name = empty($field) ? '' : $field[0];
        return $name;
    }

    /**
     * Get the alternatives of the full name.
     *
     * @return aray
     */
    public function getAddedEntryPersonalNames()
    {
        $field = $this->getFieldArray('400', array('a'));
        $name = empty($field) ? '' : $field;
        return $name;
    }

    /**
     * Get the authority's pseudonyms.
     *
     * @return array
     */
    public function getPseudonyms()
    {
        $field = $this->getFieldArray('500', array('a'));
        $name = empty($field) ? '' : $field;
        return $name;
    }

    public function getBibinfoForObalkyKnihV3()
    {
        $bibinfo = array();
        $bibinfo['cover_medium_url'] = $this->getAuthorityCover();
        return $bibinfo;
    }

    /**
     * Get the url of authority's cover from obalkyknih.
     *
     * @return string $coverUrl
     */
    public function getAuthorityCover()
    {
        $field = $this->getMarcRecord()->getField('001');
        $auth_id = empty($field) ? '' : $field->getData();
        $coverUrl = '';

        if (! empty($auth_id)) {
            $client = new \Zend\Http\Client('http://cache.obalkyknih.cz/api/auth/meta');
            $client->setParameterGet(array(
                'auth_id' => $auth_id
            ));

            $response = $client->send();
            $responseBody = $response->getBody();
            $phpResponse = json_decode($responseBody, true);
            $coverUrl = empty($phpResponse[0]['cover_medium_url']) ? '' : $phpResponse[0]['cover_medium_url'];
        }

        return $coverUrl;
    }
}
