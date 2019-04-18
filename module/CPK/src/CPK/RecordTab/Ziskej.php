<?php


namespace CPK\RecordTab;


use VuFind\RecordTab\AbstractBase;

class Ziskej extends AbstractBase
{
    /**
     * Is this tab enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor
     *
     * @param bool $enabled is this tab enabled?
     */
    public function __construct($enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function getDescription()
    {
        return 'ZISKEJ';
    }
}