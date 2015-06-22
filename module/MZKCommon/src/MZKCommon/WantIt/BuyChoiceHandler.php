<?php
/**
 * BuyChoiceHandler Model Class
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

use MZKCommon\WantIt\BuyChoiceHandlerInterface;
use MZKCommon\WantIt\AbstractHttpClient;

/**
 * BuyChoiceHandler Model
 * Provides available Buy actions on records and handles user's requests.
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class BuyChoiceHandler extends AbstractHttpClient implements BuyChoiceHandlerInterface
{
	/**
	 * Record object
	 * @var	obj
	 */
	protected $record;

	/**
	 * Sets initial params
	 * 
	 * @param Record $record
	 */
	public function __construct(Record $record)
	{
		$this->record = $record;
	}
	
	/**
	 * @inheritDoc
	 */
	public function availableAtGoogleBooks()
	{
		$url = 'https://www.googleapis.com/books/v1/volumes';
		$params = array ('q' => 'isbn:'.$this->record->getIsbn());
		$dataArray = $this->getRequestDataResponseFromJSON($url, $params);
		
		if (isset($dataArray['totalItems']) && ($dataArray['totalItems'] > 0)) 
			return true;
	
		return false;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getGoogleBooksItemUrl()
	{
		$url = 'https://www.googleapis.com/books/v1/volumes';
		$params = array ('q' => 'isbn:'.$this->record->getIsbn());
		$dataArray = $this->getRequestDataResponseFromJSON($url, $params);	
		$cannonicalUrl = $dataArray['items'][0]['volumeInfo']['canonicalVolumeLink'];
		return $cannonicalUrl;
	}
	
	/**
	 * @inheritDoc
	 */
	public function availableAtKosmas()
	{
	
	}
	
	/**
	 * @inheritDoc
	 */
	public function getKosmasItemUrl()
	{
	
	}
	
	/**
	 * @inheritDoc
	 */
	public function availableAtAntikvariat()
	{
	
	}
	
	/**
	 * @inheritDoc
	 */
	public function getAntikvariatItemUrl()
	{
	
	}
}