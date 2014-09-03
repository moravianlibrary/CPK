<?php

namespace MZKPortal\RecordTab;

class HoldingsBase extends \MZKCommon\RecordTab\HoldingsILS
{

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection|bool $catalog ILS connection to use to check
     * for holdings before displaying the tab; set to false if no check is needed
     */
    public function __construct($catalog = null)
    {
        parent::__construct($catalog);
    }

    public function getSelectedFilters()
    {
        $filters = array();
        foreach ($this->getAvailableFilters() as $name => $type) {
            $filterValue = $this->getRequest()->getQuery($name);
            if ($filterValue != null || !empty($filterValue)) {
                $filters[$name] = $filterValue;

            }
        }

        return $filters;
    }

}