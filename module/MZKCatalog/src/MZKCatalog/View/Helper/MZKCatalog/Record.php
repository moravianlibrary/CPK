<?php
namespace MZKCatalog\View\Helper\MZKCatalog;

use ObalkyKnih\View\Helper\ObalkyKnih\Record as ParentRecord;

class Record extends ParentRecord
{

    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     *
     * @return string
     */
    public function getCheckbox($idPrefix = '')
    {
        static $checkboxCount = 0;
        $id = $this->driver->getResourceSource() . '|'
            . $this->driver->getUniqueId();
        $context
            = array('id' => $id, 'count' => $checkboxCount++, 'prefix' => $idPrefix);
        return $this->contextHelper->renderInContext(
            'record/checkboxAutoAddToCart.phtml', $context
        );
    }

    
    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     *
     * @return string
     */
    public function getCheckboxWithoutAutoAddingToCart($idPrefix = '')
    {
    	static $checkboxCount = 0;
    	$id = $this->driver->getResourceSource() . '|'
    			. $this->driver->getUniqueId();
    	$context
    	= array('id' => $id, 'count' => $checkboxCount++, 'prefix' => $idPrefix);
    	return $this->contextHelper->renderInContext(
    			'record/checkbox.phtml', $context
    	);
    }


}