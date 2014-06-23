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
        $urls = parent::getURLs();
        if (is_array($urls)) {
            for ($i = 0; $i < count ($urls); $i++) {
                if (isset($urls[$i]['url'])) {
                    $urls[$i]['url'] = 'http://' . $urls[$i]['url'];
                }
            }
        }
        return $urls;
    }

    public function getThumbnail($size = 'medium')
    {
        if (!$this->isAlbum()) return '';
        if ($size == 'small') $size = 'medium';
        $id = $this->fields['id'];
        $dot = strpos($id, '.');
        if ($dot == false) {
            return parent::getThumbnail($size);
        }

        $id = substr($id, $dot + 1);
        $link = '';
        if (preg_match('/\d+/', $id)) {
            $id = ltrim($id, '0');
            $link = $this->getImagePath($id, $size);
        }

        return empty($link) ? parent::getThumbnail($size) : $link;
    }

    /**
     * @return content of album as array
     */
    public function getContent()
    {
        $result = array();
        $children = $this->getChildren();
        $fields = $this->marcRecord->getFields('505');
        if (count($children) != count($fields)) {
            throw new \ErrorException('SupRecord: lines/links mismatch');
        }

        for ($i = 0; $i < count($fields); $i++) {
            $current = $fields[$i];
            $currentArray = array();
            foreach (array('8', 'g', 'r', 't') as $code) {
                $sub = $current->getSubfield($code);
                if ($sub) {
                    $currentArray[$code] = $sub->getData();
                }
            }
            $currentArray['id'] = $children[$i];
            if (!empty($currentArray)) {
                $result[] = $currentArray;
            }
        }
        return $result;
    }

    /**
     * @param string $id image id
     * @return string path to image
     */
    public function getImagePath($id, $size = 'medium')
    {
        if (!isset($this->fields['label_path_str'])) {
            return '';
        }
        
        $confPath = $this->recordConfig->SupraphonLabels->dir;
        if (!isset($confPath)) {
            return '';
        }
        
        $path = rtrim($this->fields['label_path_str'], '/');
        $path = $confPath . $path;
        
        if ($size == 'medium') {
            return $path;
        }
        $path = substr($path, 0, -10);
        return $path . $size . '.jpg';
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
        $urls = $this->getURLs();
        if (!is_array($urls) || !isset($urls[0]) || !isset($urls[0]['url'])) {
            return;
        }

        $url = $urls[0]['url'];
        $confEnd = 'sup_end';
        $linkEnd = $this->recordConfig->ExternalLinks->$confEnd;

        return array(
                  array( 'institution' => 'sup', 'url' => $url . $linkEnd, 'display' => $url)
               );
    }

}
