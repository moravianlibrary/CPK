<?php

namespace MZKPortal\RecordTab;

class Holdings996 extends \MZKPortal\RecordTab\HoldingsBase
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
    
}