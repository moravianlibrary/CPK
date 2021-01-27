<?php

namespace CPK\RecordTab;

use VuFind\RecordTab\AbstractBase;

class ZiskejEdd extends AbstractBase
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
    public function __construct($enabled = false)
    {
        $this->enabled = $enabled;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     * @throws \Exception
     */
    public function isActive(): bool
    {
        if ($this->getRecordDriver()->tryMethod('isAvailableInZiskejEdd') === true) {
            $this->enabled = true;
        }

        return $this->enabled;
    }

    public function getDescription(): string
    {
        return 'Získej EDD';
    }
}
