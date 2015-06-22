<?php
/**
 * WantIt Model Class
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace MZKCommon\WantIt;

use MZKCommon\WantIt\WantItInterface;
use MZKCommon\WantIt\PaperChoiceHandler;
use MZKCommon\WantIt\ElectronicChoiceHandler;
use MZKCommon\WantIt\BuyChoiceHandler;

/**
 * WantIt Model
 * Provides available actions on records and handles user's requests.
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class WantIt implements WantItInterface
{
	/**
	 * Record object
	 * @var	obj
	 */
	protected $record;
	
	/**
	 * PaperChoiceHandler object
	 * @var	obj
	 */
	protected $paperChoiceHandler;
	
	/**
	 * ElectronicChoiceHandler object
	 * @var	obj
	 */
	protected $electronicChoiceHandler;
	
	/**
	 * BuyChoiceHandler object
	 * @var	obj
	 */
	protected $buyChoiceHandler;

	/**
	 * Sets initial params
	 * 
	 * @param Record $record
	 */
	public function __construct(Record $record)
	{
		$this->record = $record;
		$this->paperChoiceHandler = new PaperChoiceHandler($record);
		$this->electronicChoiceHandler = new ElectronicChoiceHandler($record);
		$this->buyChoiceHandler = new BuyChoiceHandler($record);
	}
	
	/**
	 * Is user logged in
	 * @return	boolean
	 */
	protected function isLoggedIn()
	{
		
	}
}
	
	