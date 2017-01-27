<?php
/**
 * Table Definition for PortalPage
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use CPK\Db\Table\Gateway,
    Zend\Config\Config,
    Zend\Db\Sql\Select,
    Zend\Db\Sql\Update,
    Zend\Db\Sql\Delete,
    Zend\Db\Sql\Insert,
    Zend\Db\Sql\Expression;

/**
 * Table Definition for PortalPage
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class PortalPage extends Gateway
{
    /**
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->table = 'portal_pages';
        $this->rowClass = 'CPK\Db\Row\PortalPage';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Returns all rows from portal_pages table
     *
     * @param string    $languageCode, e.g. "en-cpk"
     * @param boolean   $publishedOnly Set to false to get all pages
     *
     * @return array
     */
    public function getAllPages($languageCode = '*', $publishedOnly = true)
    {
        $select = new Select($this->table);

        $condition = '';
        if ($languageCode != '*') {
            $condition = "language_code='$languageCode'";
        }

        if ($publishedOnly) {
            if (! empty($condition)) {
                $condition .= ' AND published="1"';
            } else {
                $condition = 'published="1"';
            }

        }
        if (! empty($condition)) {
            $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
            $select->where($predicate);
        }

        $select->order('order_priority');

        $results= $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }

    /**
     * Return row from table
     *
     * @param string $prettyUrl
     * @param string $languageCode
     *
     * @return array
     */
    public function getPage($prettyUrl, $languageCode)
    {
        $select = new Select($this->table);
        $subSelect = "SELECT `group` FROM `$this->table` WHERE `pretty_url`='$prettyUrl'";
        $condition = "`language_code` like '$languageCode%' AND `group` = ($subSelect) ";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }

    /**
     * Return row from table by id
     *
     * @param int $pageId
     *
     * @return array
     */
    public function getPageById($pageId)
    {
        $select = new Select($this->table);

        $subSelect = "SELECT `group` FROM `portal_pages` ";

        $condition = "`id`='$pageId'";
        $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
        $select->where($predicate);

        $result = $this->executeAnyZendSQLSelect($select)->current();
        return $result;
    }

    /**
     * Save edited row to table by id
     *
     * @param array $page
     *
     * @return void
     */
    public function save(array $page)
    {
        $update = new Update($this->table);

        $update->set([
            'title' => $page['title'],
            'pretty_url' => $this->generateCleanUrl($page['title']),
            'language_code' => $page['language'],
            'content' => $page['content'],
            'published' => isset($page['published']) ? 1 : 0,
            'placement' => $page['placement'],
            'position' => $page['position'],
            'order_priority' => $page['orderPriority'],
            'last_modified_timestamp' => date("Y-m-d H:i:s"),
            'last_modified_user_id' => $page['userId']
        ]);
        $update->where([
            'id' => $page['pageId']
        ]);

        $this->executeAnyZendSQLUpdate($update);
    }

    /**
     * Save specific contents
     *
     * @param array $page
     *
     * @return void
     */
    public function saveSpecifiContents(array $page)
    {
        $delete = new Delete('modal_specific_contents');

        $delete->where([
            'portal_page_group' => $page['pageGroup'],
            'language_code' => $page['language']
        ]);

        $this->executeAnyZendSQLDelete($delete);

        foreach ($page['content'] as $key => $content) {

            $insert = new Insert('modal_specific_contents');
            $insert->values([
                'portal_page_group' => $page['pageGroup'],
                'language_code' => $page['language'],
                'content' => $page['content'][$key],
                'source' => $page['source'][$key]
            ]);
            $this->executeAnyZendSQLInsert($insert);

        }
    }

    /**
     * Insert a new row to table
     *
     * @param array $page
     *
     * @return void
     */
    public function insertNewPage(array $page)
    {
        $nextGroup = $this->getMaxValueInColumn('group') + 1;

        $insert = new Insert($this->table);

        $insert->values([
            'title' => $page['title'],
            'pretty_url' => $this->generateCleanUrl($page['title']),
            'language_code' => $page['language'],
            'content' => $page['content'],
            'published' => isset($page['published']) ? 1 : 0,
            'placement' => $page['placement'],
            'position' => $page['position'],
            'order_priority' => $page['orderPriority'],
            'last_modified_timestamp' => date("Y-m-d H:i:s"),
            'last_modified_user_id' => $page['userId'],
            'group' => $nextGroup
        ]);

        $this->executeAnyZendSQLInsert($insert);

        /* And create one page for second language */
        $insert2 = new Insert($this->table);

        $page['title'] .= ' 2';
        $insert2->values([
            'title' => $page['title'],
            'pretty_url' => $this->generateCleanUrl($page['title']),
            'language_code' => $page['language'],
            'content' => $page['content'],
            'published' => isset($page['published']) ? 1 : 0,
            'placement' => $page['placement'],
            'position' => $page['position'],
            'order_priority' => $page['orderPriority'],
            'last_modified_timestamp' => date("Y-m-d H:i:s"),
            'last_modified_user_id' => $page['userId'],
            'group' => $nextGroup
        ]);

        $this->executeAnyZendSQLInsert($insert2);
    }

    /**
     * Remove row from table by id
     *
     * @param int $pageId
     *
     * @return array
     */
    public function delete($pageId)
    {
        $delete = new Delete($this->table);

        $delete->where([
            'id' => $pageId
        ]);

        $this->executeAnyZendSQLDelete($delete);
    }

    /**
     * Generate clean url from title
     *
     * @param string $title
     *
     * @return string
     */
    protected function generateCleanUrl($title)
    {
        setlocale(LC_ALL, 'en_US.UTF8');
        $title = $this->removeAccent($title);
        $cleanUrl = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
	    $cleanUrl = preg_replace("/[^a-zA-Z0-9\/_| -]/", '', $cleanUrl);
	    $cleanUrl = strtolower(trim($cleanUrl, '-'));
	    $cleanUrl = preg_replace("/[\/_| -]+/", '-', $cleanUrl);

	    return $cleanUrl;
    }

    /**
     * Remove diacritic
     *
     * @param string $title
     *
     * @return string
     */
    protected function removeAccent($str)
    {
        $a = array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ü','ý','ÿ','Ā','ā','Ă','ă','Ą','ą','Ć','ć','Ĉ','ĉ','Ċ','ċ','Č','č','Ď','ď','Đ','đ','Ē','ē','Ĕ','ĕ','Ė','ė','Ę','ę','Ě','ě','Ĝ','ĝ','Ğ','ğ','Ġ','ġ','Ģ','ģ','Ĥ','ĥ','Ħ','ħ','Ĩ','ĩ','Ī','ī','Ĭ','ĭ','Į','į','İ','ı','Ĳ','ĳ','Ĵ','ĵ','Ķ','ķ','Ĺ','ĺ','Ļ','ļ','Ľ','ľ','Ŀ','ŀ','Ł','ł','Ń','ń','Ņ','ņ','Ň','ň','ŉ','Ō','ō','Ŏ','ŏ','Ő','ő','Œ','œ','Ŕ','ŕ','Ŗ','ŗ','Ř','ř','Ś','ś','Ŝ','ŝ','Ş','ş','Š','š','Ţ','ţ','Ť','ť','Ŧ','ŧ','Ũ','ũ','Ū','ū','Ŭ','ŭ','Ů','ů','Ű','ű','Ų','ų','Ŵ','ŵ','Ŷ','ŷ','Ÿ','Ź','ź','Ż','ż','Ž','ž','ſ','ƒ','Ơ','ơ','Ư','ư','Ǎ','ǎ','Ǐ','ǐ','Ǒ','ǒ','Ǔ','ǔ','Ǖ','ǖ','Ǘ','ǘ','Ǚ','ǚ','Ǜ','ǜ','Ǻ','ǻ','Ǽ','ǽ','Ǿ','ǿ');
        $b = array('A','A','A','A','A','A','AE','C','E','E','E','E','I','I','I','I','D','N','O','O','O','O','O','O','U','U','U','U','Y','s','a','a','a','a','a','a','ae','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','o','u','u','u','u','y','y','A','a','A','a','A','a','C','c','C','c','C','c','C','c','D','d','D','d','E','e','E','e','E','e','E','e','E','e','G','g','G','g','G','g','G','g','H','h','H','h','I','i','I','i','I','i','I','i','I','i','IJ','ij','J','j','K','k','L','l','L','l','L','l','L','l','l','l','N','n','N','n','N','n','n','O','o','O','o','O','o','OE','oe','R','r','R','r','R','r','S','s','S','s','S','s','S','s','T','t','T','t','T','t','U','u','U','u','U','u','U','u','U','u','U','u','W','w','Y','y','Y','Z','z','Z','z','Z','z','s','f','O','o','U','u','A','a','I','i','O','o','U','u','U','u','U','u','U','u','U','u','A','a','AE','ae','O','o');
        return str_replace($a, $b, $str);
    }



    /**
     * Get max value in columnt
     *
     * @param string $column
     *
     * @return int
     */
    protected function getMaxValueInColumn($column)
    {
        $select = new Select($this->table);

        $select->columns(array(
            'max' => new Expression('MAX(`'.$column.'`)')
        ));

        $statement = $this->sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);
        $resultsArray = $resultSet->toArray();

        return $resultsArray[0]['max'];
    }

    /**
     * Returns rows from modal_specific_contents table
     *
     * @param string    $languageCode, e.g. "en-cpk"
     * @param boolean   $portalPageGroup
     *
     * @return array
     */
    public function getSpecificContents($languageCode, $portalPageGroup)
    {
        $select = new Select('modal_specific_contents');

        $condition = "language_code='$languageCode' AND portal_page_group='$portalPageGroup'";

        if (! empty($condition)) {
            $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
            $select->where($predicate);
        }

        $results= $this->executeAnyZendSQLSelect($select);

        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        $resultSet->initialize($results);

        return $resultSet->toArray();
    }

    /**
     * Returns row from modal_specific_contents table
     *
     * @param string    $languageCode, e.g. "en-cpk"
     * @param boolean   $portalPageGroup
     * @param string    $source             mzk
     *
     * @return array
     */
    public function getSpecificContent($languageCode, $portalPageGroup, $source)
    {
        $select = new Select('modal_specific_contents');

        $condition = "language_code='$languageCode' AND portal_page_group='$portalPageGroup' AND source='$source'";

        if (! empty($condition)) {
            $predicate = new \Zend\Db\Sql\Predicate\Expression($condition);
            $select->where($predicate);
        }

        return $this->executeAnyZendSQLSelect($select)->current();
    }
}