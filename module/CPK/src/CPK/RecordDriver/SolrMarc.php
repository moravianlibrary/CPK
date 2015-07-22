<?php
namespace CPK\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarc extends ParentSolrMarc
{

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

    protected function getExternalID() {
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

    public function getParentRecordID()
    {
        return isset($this->fields['parent_id_str']) ? $this->fields['parent_id_str'] : [];
    }

    public function getAntikvariatyLink()
    {
        return isset($this->fields['external_links_str_mv']) ? $this->fields['external_links_str_mv'] : [];
    }

    /**
     * Get comments on obalkyknih.cz associated with this record.
     *
     * @return array
     */
    public function getObalkyKnihComments()
    {
        $comment = new \stdClass();
        $comment->firstname = 'Jmeno';
        $comment->lastname = 'Prijmeni';
        $comment->created = 'datum vytvoreni';
        $comment->comment = ' Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam at efficitur mi. Pellentesque aliquet dolor ac ligula consequat dignissim. Quisque efficitur tellus nec sapien vehicula, vel pellentesque arcu varius. Nunc lectus augue, tristique quis congue id, condimentum a risus. Vivamus pulvinar vehicula velit at tempus. Praesent eu diam non mauris laoreet scelerisque sed et massa. Aenean nibh sapien, hendrerit sed mollis varius, dapibus ut quam. Proin dapibus suscipit risus sed congue. Duis vel libero non augue viverra scelerisque vitae at neque.

Sed ac malesuada ex. Praesent nec ipsum ut augue interdum ultrices sed ut ex. Phasellus hendrerit id dui et ullamcorper. Vestibulum non gravida dui. Curabitur semper sit amet eros et maximus. Nulla aliquet sodales nisl et posuere. Nam facilisis, nulla aliquet vestibulum dictum, magna ipsum lobortis sapien, eget accumsan magna metus at nunc. Nam nisl dui, interdum non ex in, placerat vulputate ligula. Suspendisse ipsum enim, posuere eu accumsan et, dignissim eget ipsum. Etiam fringilla velit quam. Nulla feugiat iaculis ornare. Phasellus luctus ultricies bibendum. ';

        return [
            $comment,
            $comment,
            $comment,
        ];
        $table = $this->getDbTable('Comments');
        return $table->getForResource(
            $this->getUniqueId(), $this->getResourceSource()
        );
    }
    
}
