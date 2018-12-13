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
    public function getSortRecords($dedupedRecords) {

        $tempCnbs = [];
        $tempCaslins = [];
        foreach ($dedupedRecords as $key => $record) {
            if ($record['source'] === 'source_cnb') {
                $tempCnbs[] = $record;
                unset($dedupedRecords[$key]);
            }
            if ($record['source'] === 'source_caslin') {
                $tempCaslins[] = $record;
                unset($dedupedRecords[$key]);
            }
        }
        sort($dedupedRecords);
        foreach ($tempCnbs as $key => $record) {
            $dedupedRecords[] = $record;
        }
        foreach ($tempCaslins as $key => $record) {
            $dedupedRecords[] = $record;
        }

        return $dedupedRecords;
    }

    public function getInternalMultiplicity($dedupedRecords, $institution){
        $records = array();
        $compare = substr($institution,0, strpos($institution, '.'));
        foreach ($dedupedRecords as $key => $record) {
            if ($compare === substr($record['source'], 7) && $institution !== $record['id']) {
                array_push($records, $record['id']);
            }
        }
        return $records;
    }
}