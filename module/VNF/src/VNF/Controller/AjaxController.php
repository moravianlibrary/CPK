<?php
namespace VNF\Controller;
use VuFind\Controller\AjaxController As ParentAjaxController;


class AjaxController extends ParentAjaxController
{
    /**
     * Filters years from 1800 to current year
     *
     */
    protected function processFacetValues($fields, $results)
    {
        $facets = $results->getFullFieldFacets(array_keys($fields),true,-1, 'index');
        $retVal = [];
        $currentYear = date("Y");
        $minRelevantYear = 1800;
        foreach ($facets as $field => $values) {
            $newValues = ['data' => []];
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match("/^[0-9]+$/", $current['value'])) {
                    if ($current['value'] < $currentYear && $current['value'] > $minRelevantYear) {
                        $newValues['data'][]
                            = [$current['value'], $current['count']];
                    }
                }
            }
            $retVal[$field] = $newValues;
        }
        return $retVal;
    }

}
