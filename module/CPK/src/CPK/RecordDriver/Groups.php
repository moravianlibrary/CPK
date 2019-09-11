<?php
/**
 * Created by PhpStorm.
 * User: novacek
 * Date: 12.2.18
 * Time: 14:47
 */

namespace CPK\RecordDriver;


class Groups
{
    /**
     * Sorts deduped records and excludes CNB and Caslins at the end
     * @param $dedupedRecords   array   Array of deduped records
     * @return array    Sorted deduped records
     */
    public static function getSortRecords($dedupedRecords) {
        $temp['source_cnb'] = $temp['source_caslin'] = [];
        foreach ($dedupedRecords as $key => $record) {
            //if source is one of these, exclude it and save temporary elsewhere
            if (in_array($record['source'], ['source_cnb','source_caslin'])){
                $temp[ $record['source'] ][] = $record;
                unset($dedupedRecords[$key]);
            }
        }
        sort($dedupedRecords);
        //add CNBs and Caslins at the end of array, both are numeric indexed arrays
        return array_merge($dedupedRecords, $temp['source_cnb'], $temp['source_caslin']);
    }

    /**
     * Get same institution from deduplicated list
     * @param $dedupedRecords   Array of deduplicated results
     * @param $institution
     * @return array
     */
    public static function getInternalMultiplicity($dedupedRecords, $institution){
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