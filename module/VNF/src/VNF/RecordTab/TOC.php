<?php
namespace VNF\RecordTab;

/**
 *  TOC implementation for Supraphon records
 *
 */
class TOC extends \VuFind\RecordTab\TOC {

	public function getDescription() {
		return 'Table of Contents';
	}
	
	public function isActive() {
        return true;
	}
	
	/**
	 * converts MARC footage into readable format
	 * @param string $footage
	 * @return string
	 */
	public function convertFootage($footage) {
		if (!is_string($footage) || strlen($footage) != 6) {
			return '';
		}
		
		$hours = substr($footage, 0, 2);
		$minutes = substr($footage, 2, 2);
		$seconds = substr($footage, 4, 2);
		
		$result = '';
		$result .= (int) $hours >= 1 ? (int) $hours . ':' : '';
		$result .= (int) $minutes . ':';
		$result .=  $seconds;
		
		return $result;
	}
   
    

}