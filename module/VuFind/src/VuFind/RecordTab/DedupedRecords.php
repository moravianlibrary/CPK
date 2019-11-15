<?php
/**
 * Description tab
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordTabs
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Description tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class DedupedRecords extends AbstractBase
{

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service $search Search service
     */
    public function __construct(\VuFindSearch\Service $search)
    {
        $this->searchService = $search;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Records in group';
    }

    /**
     * Check existence of image in logos and returns it's URI
     * @param $source
     * @return string|null  URI of the institution logo
     */
    public function get_logo_uri($source)
    {
        $logosPath =  '/themes/bootstrap3/images/institutions/logos/';
        $logosPostfix =  '_small';
        $sourceWithoutPrefix = str_replace('source_', '', $source);
        $uri = $logosPath . $sourceWithoutPrefix . '/' . $sourceWithoutPrefix . $logosPostfix . '.png';
        return file_exists(APPLICATION_PATH . $uri) ?
            $uri : null;
    }

    /**
     * Gets institution's id, source and logo
     * @param $localID
     * @return array
     */
    public function get_institution_data($localID)
    {
        $source = 'source_' . substr($localID, 0, strpos($localID, '.'));
        return [
            'id' => $localID,
            'source' => $source,
            'logo' => $this->get_logo_uri($source)
        ];
    }

    /**
     * Get institutions data
     * @return array    Array of institution data ['source', 'id', 'logo']
     */
    public function getRecordsInGroup()
    {
        $records = [];
        $localIds = $this->driver->getParentRecordDriver()->getChildrenIds();
        foreach ($localIds as $id) {
            $records[] = $this->get_institution_data($id);
        }
        return $records;
    }

    /**
     * Sorts deduped records and excludes CNB and Caslins at the end
     * @return array    Sorted deduped records
     */
    public function get_sorted_records_groups()
    {
        //unique array
        $records = array_map("unserialize", array_unique(array_map("serialize", $this->getRecordsInGroup())));

        $temp['source_cnb'] = $temp['source_caslin'] = [];
        foreach ($records as &$record) {
            //if source is one of these, exclude it and save temporary elsewhere
            if (in_array($record['source'], ['source_cnb','source_caslin'])){
                $temp[ $record['source'] ][] = $record;
                unset($record);
            }
        }
        sort($records);
        //add CNBs and Caslins at the end of array, both are numeric indexed arrays
        return array_merge($records, $temp['source_cnb'], $temp['source_caslin']);
    }

    /**
     * Get same institution from deduplicated list
     * @param $dedupedRecords   Array of deduplicated results
     * @param $institution
     * @return array
     */
    public static function get_internal_multiplicity($dedupedRecords, $institution)
    {
        $records = [];
        //get institution - e.g. 'source_mzk' => 'mzk'
        $compare = substr($institution,0, strpos($institution, '.'));
        foreach ($dedupedRecords as $key => $record) {
            if ($compare === substr($record['source'], 7) && $institution !== $record['id']) {
                array_push($records, $record['id']);
            }
        }
        return $records;
    }
}