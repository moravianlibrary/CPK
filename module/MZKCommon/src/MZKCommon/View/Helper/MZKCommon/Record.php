<?php
namespace MZKCommon\View\Helper\MZKCommon;

use VuFind\View\Helper\Root\Record as ParentRecord;

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

    public function getObalkyKnihJSON()
    {
        $bibinfo = $this->driver->tryMethod('getBibinfoForObalkyKnih');
        if (empty($bibinfo)) {
            $bibinfo = array(
                "authors" => array($this->driver->getPrimaryAuthor()),
                "title" => $this->driver->getTitle(),
            );
            $isbn = $this->driver->getCleanISBN();
            if (!empty($isbn)) {
                $bibinfo['isbn'] = $isbn;
            }
            $year = $this->driver->getPublicationDates();
            if (!empty($year)) {
                $bibinfo['year'] = $year[0];
            }
        }
        return json_encode($bibinfo, JSON_HEX_QUOT | JSON_HEX_TAG);
    }

    public function getObalkyKnihJSONV3()
    {
        return $this->getObalkyKnihJSON();
    }

}
