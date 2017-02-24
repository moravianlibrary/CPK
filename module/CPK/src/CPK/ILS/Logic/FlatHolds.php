<?php
namespace CPK\ILS\Logic;
use VuFind\ILS\Logic\Holds as Holds;
use VuFind\ILS\Connection as ILSConnection;

class FlatHolds extends Holds
{
    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $account Auth manager object
     * @param ILSConnection        $ils     A catalog connection
     * @param \VuFind\Crypt\HMAC   $hmac    HMAC generator
     * @param \Zend\Config\Config  $config  VuFind configuration
     */
    public function __construct(\VuFind\Auth\ILSAuthenticator $ilsAuth, ILSConnection $ils,
        \VuFind\Crypt\HMAC $hmac, \Zend\Config\Config $config
    ) {
        parent::__construct($ilsAuth, $ils, $hmac, $config);
    }

    protected function formatHoldings($holdings)
    {
        return $holdings;
    }

    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id A Bib ID
     *
     * @return array A sorted results set
     */

    public function getHoldings($id, $filters=array())
    {
        $holdings = array();

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            $patron = $this->ilsAuth->storedCatalogLogin();
            $result = $this->catalog->getHolding($id, $patron ? $patron : null, $filters);
            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result);
            } else {
                $holdings = $this->generateHoldings($result, $mode);
            }
        }
        return $this->formatHoldings($holdings);
    }

    /**
     * Protected method for driver defined holdings
     *
     * @param array $result     A result set returned from a driver
     * @param array $holdConfig Hold configuration from driver
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result, $holdConfig)
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
                        $action = ($copy['holdtype'] != 'shortloan')?'Hold':'ShortLoan';
                        $copy['link'] = (strcmp($copy['addLink'], 'block') == 0)
                        ? $this->getBlockedDetails($copy)
                        : $this->getRequestDetails(
                            $copy, $checkHolds['HMACKeys'], $action
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