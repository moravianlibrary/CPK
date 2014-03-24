<?php
namespace MZKCatalog\RecordTab;
use VuFind\RecordTab\AbstractBase;

/**
 * Support for citace.com
 * 
 * @author xrosecky
 *
 */
class Citation extends AbstractBase
{
    
    const CITACE_API_URL = "https://www.citacepro.com/api/";

    public function getDescription()
    {
        return 'Citation';
    }
    
    public function getCitationURL()
    {
        $id = $this->getRecordDriver()->getUniqueID();
        list($base, $sysno) = split("-", $id);
        $url = self::CITACE_API_URL . 'mzk/' . $base . '/citace/' . $sysno;
        //$interface->assign('citace', array('url' => $baseURL . $base . "/citace/" . $sysno));
        return $url;
    }
    
}