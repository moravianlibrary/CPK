<?php
namespace HF\Controller;
use VuFind\Controller\AjaxController As ParentAjaxController;


class AjaxController extends ParentAjaxController
{
    /**
     * Do not show dates in future in pubdatevis
     *
     */
    protected function processFacetValues($fields, $results)
    {
        $facets = $results->getFullFieldFacets(array_keys($fields));
        $retVal = [];
        $currentYear = date("Y");
        foreach ($facets as $field => $values) {
            $newValues = ['data' => []];
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match("/^[0-9]+$/", $current['value'])) {
                    if ($current['value'] < $currentYear) {
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
