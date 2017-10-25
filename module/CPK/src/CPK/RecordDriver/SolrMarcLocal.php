<?php
namespace CPK\RecordDriver;
use CPK\RecordDriver\SolrMarc as ParentSolrMarc;

class SolrMarcLocal extends ParentSolrMarc
{

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title_display']) ?
        $this->fields['title_display'] : '';
    }

    public function getSubtitle()
    {
        return isset($this->fields['title_sub_display']) ?
        $this->fields['title_sub_display'] : '';
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset($this->fields['format_display_mv']) ? $this->fields['format_display_mv'] : [];
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return isset($this->fields['author_display']) ?
            $this->fields['author_display'] : '';
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return isset($this->fields['author2_display_mv']) ?
        $this->fields['author2_display_mv'] : [];
    }

    /**
     * Get the corporate author of the record.
     *
     * @return string
     */
    public function getCorporateAuthor()
    {
        return isset($this->fields['corp_author_display']) ?
            $this->fields['corp_author_display'] : '';
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return isset($this->fields['publishDate_display']) ?
            $this->fields['publishDate_display'] : [];
    }

    public function getEAN()
    {
        return (!empty($this->fields['ean_display_mv']) ? $this->fields['ean_display_mv'][0] : null);
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        // If ISBN is in the index, it should automatically be an array... but if
        // it's not set at all, we should normalize the value to an empty array.
        return isset($this->fields['isbn_display_mv']) && is_array($this->fields['isbn_display_mv']) ?
                        $this->fields['isbn_display_mv'] : [];
    }

    protected function getCNB()
    {
        return isset($this->fields['nbn_display']) ? $this->fields['nbn_display'] : null;
    }

}
