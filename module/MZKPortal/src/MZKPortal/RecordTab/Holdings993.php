<?php

namespace MZKPortal\RecordTab;

class Holdings993 extends \MZKCommon\RecordTab\HoldingsILS
{

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection|bool $catalog ILS connection to use to check
     * for holdings before displaying the tab; set to false if no check is needed
     */
    public function __construct($catalog)
    {
        parent::__construct($catalog);
    }
    
}