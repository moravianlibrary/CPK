<?php
/**
 * Model for Supraphon records in Solr.
 */
namespace VNF\RecordDriver;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrSup extends SolrMarc
{
    /**
     * @return true iff this is album record
     */
    public function isAlbum()
    {
        $leader = $this->marcRecord->getLeader();
        return strlen($leader > 22) && $leader['21'] == 'a';
    }

    /**
     * @return array of children ids
     */
    public function getChildren()
    {
        return isset($this->fields['sup_downlinks']) ? $this->fields['sup_downlinks'] : array();
    }
    
    /**
     * @return string parent record id
     */
    public function getParent()
    {
        return isset($this->fields['sup_uplink']) ? $this->fields['sup_uplink'] : '';            
    }

    /**
     * replaces new line entity 'EOL_ENT' in summary
     */
    public function getSummary()
    {
        $summary = parent::getSummary();
        if (is_array($summary) && isset($summary[0])) {
            return explode('EOL_ENT', $summary[0]);
        }
        return $summary;
    }

    /**
     * @return array - result of parent getURL with 'http://' prefix
     */
    public function getURLs()
    {
        $utmParams = '';
        $linkEnd = $this->recordConfig->ExternalLinks->sup_end;
        if ($linkEnd) {
            $utmParams = $linkEnd;
        }
        $urls = parent::getURLs();
        if (is_array($urls)) {
            for ($i = 0; $i < count ($urls); $i++) {
                if (isset($urls[$i]['url']) && !empty($urls['url'])) {
                    $urls[$i]['url'] = 'http://' . $urls[$i]['url'] . $linkEnd;
                    $urls[$i]['desc'] = 'Get full text';
                } else {
                    unset ($urls[$i]);
                }
            }
        }
        return $urls;
    }
    
    /**
     * @return content of album as array
     */
    public function getToc()
    {
        $result = array();
        if (!isset($this->fields['contents']) ) {
            return array();
        }
        
        $content = $this->fields['contents'][0];
        //remove prefix
        $content = substr($content, 8);
        $currentHolding = array();
        $currentResult = '';
        foreach (explode('--!--', $content) as $currentLine) {
            foreach (explode('$', $currentLine) as $currentSubfield) {
                $currentResult [substr($currentSubfield, 0, 1)] = substr($currentSubfield, 1);
            }
            $result[] = $currentResult;
        }
        return $result;
    }

    public function getUniqueKeys() {
        $result = array();
        foreach (array('ean_view_txtP_mv',
            'isrc_view_txtP_mv',
            'upc_view_txtP_mv',
            'issue_view_txtP_mv',
            'matrix_view_txtP_mv',
            'plate_view_txtP_mv',
            'publisher_view_txtP_mv') as $current) {

            if (array_key_exists($current, $this->fields)) {
                $keyType = substr($current, 0, strlen($current) - strlen('_txtP_mv'));
                foreach ($this->fields[$current] as $key) {
                    if (!isset($result[$keyType])) {
                        $result[$keyType] = array();
                    }
                    $result[$keyType][] = $key;
                }
            }
        }
        return $result;
    }

    /**
     * return array of deduplicated authors with fixed authors format
     * each secondary author is represented as array:
     * [name] => name, [role] => role1, role2 ...
     * @return array
     */
    public function getDeduplicatedAuthors()
    {
        $deduplicatedAuthors['main'] = $this->getAuthorsArray('100');
        $deduplicatedAuthors['secondary'] = $this->getAuthorsArray('700');
        return $deduplicatedAuthors;

    }
    
    public function getPrimaryAuthor() 
    {
        $author = $this->getMainAuthorEntry();
        if (!$author || !is_array($author)) 
            return parent::getPrimaryAuthor();
        return $author['name']; 
    }
    
    public function getMainAuthorEntry()
    {
        $author = $this->getAuthorsArray('100');
        return !empty($author) ? $author[0] : array();
    }

    protected function getAuthorsArray($fieldCode)
    {
        $result = array();
        $fields = $this->marcRecord->getFields($fieldCode);
        for ($i = 0; $i < count($fields); $i++) {
            $current = $fields[$i];
            $currentAuthor = array();
            $subfield = $current->getSubfield('a');
            if ($subfield) {
                $currentAuthor['name'] = $subfield->getData();
                $role = array();
                $subfields = $current->getSubfields('e');
                foreach ($subfields as $subfield) {
                    $role[] = $subfield->getData();
                }
                if (!empty($role)) {
                    $currentAuthor['role'] = implode(', ', $role);
                }
                $result[] = $currentAuthor;

            }
        }
        return $result;
    }

    public function getExternalLinks()
    {
       return array(array( 'institution' => 'sup', 'url' => '', 'display' => '', 'id' => $this->getId()));
    }

    public function getProductionCredits()
    {
        return array();
    }

}
