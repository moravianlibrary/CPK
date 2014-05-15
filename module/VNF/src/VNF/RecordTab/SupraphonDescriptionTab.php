<?php
namespace VNF\RecordTab;

/**
 *  TOC implementation for Supraphon records
 *
 */
class SupraphonDescriptionTab extends \VuFind\RecordTab\Description {

	public function getDescription() {
		return 'Description';
	}
	
	public function isActive() {
		return true;
	}

   public function getSummary() 
   {}
    

}