<?php 
namespace MZKCatalog\ILS\Logic;
use VuFind\ILS\Logic\Holds as ParentHolds;
use VuFind\ILS\Connection as ILSConnection;

class Holds extends ParentHolds
{
    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $account Auth manager object
     * @param ILSConnection        $ils     A catalog connection
     * @param \VuFind\Crypt\HMAC   $hmac    HMAC generator
     * @param \Zend\Config\Config  $config  VuFind configuration
     */
    public function __construct(\VuFind\Auth\Manager $account, ILSConnection $ils,
        \VuFind\Crypt\HMAC $hmac, \Zend\Config\Config $config
    ) {
        parent::__construct($account, $ils, $hmac, $config);
    }
    
    protected function formatHoldings($holdings)
    {
        return $holdings;
    }
    
    /**
     * Protected method for driver defined holdings
     *
     * @param array $result A result set returned from a driver
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result)
    {
        $holdings = array();

        // Are holds allows?
        $checkHolds = $this->catalog->checkFunction("Holds");

        if (count($result)) {
            foreach ($result as $copy) {
                if ($checkHolds != false) {
                    // Is this copy holdable / linkable
                    if (isset($copy['addLink']) && $copy['addLink']) {
                        // If the hold is blocked, link to an error page
                        // instead of the hold form:
                        $copy['link'] = (strcmp($copy['addLink'], 'block') == 0)
                        ? $this->getBlockedDetails($copy)
                        : $this->getHoldDetails(
                            $copy, $checkHolds['HMACKeys']
                        );
                        // If we are unsure whether hold options are available,
                        // set a flag so we can check later via AJAX:
                        $copy['check'] = (strcmp($copy['addLink'], 'check') == 0)
                        ? true : false;
                    }
                }
                $holdings[]= $copy;
            }
        }
        return $holdings;
    }
    
}