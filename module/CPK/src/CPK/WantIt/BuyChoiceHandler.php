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
namespace CPK\WantIt;

use CPK\WantIt\BuyChoiceHandlerInterface;
use CPK\WantIt\AbstractHttpClient;

/**
 * BuyChoiceHandler Model
 * Provides available Buy actions on records and handles user's requests.
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class BuyChoiceHandler extends AbstractHttpClient
{
	/**
	 * @param string $isbn isbn
	 */
	public function getGoogleBooksItemAsArrayByISBN($isbn)
	{
		$url = 'https://www.googleapis.com/books/v1/volumes';
		$params = array ('q' => 'isbn:'.str_replace("-", "", $isbn));
		$dataArray = $this->getRequestDataResponseFromJSON($url, $params);

		return $dataArray;
	}
	
	/**
	 * @param string $lccn lccn
	 */
	public function getGoogleBooksItemAsArrayByLCCN($lccn)
	{
		$url = 'https://www.googleapis.com/books/v1/volumes';
		$params = array ('q' => 'lccn:'.str_replace("-", "", $lccn));
		$dataArray = $this->getRequestDataResponseFromJSON($url, $params);
	
		return $dataArray;
	}
	
	/**
	 * @param string $oclc oclc
	 */
	public function getGoogleBooksItemAsArrayByOCLC($oclc)
	{
		$url = 'https://www.googleapis.com/books/v1/volumes';
		$params = array ('q' => 'oclc:'.str_replace("-", "", $oclc));
		$dataArray = $this->getRequestDataResponseFromJSON($url, $params);
	
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function availableAtGoogleBooks()
	{
		$dataArray = getGoogleBooksItemAsArray();
		
		if (isset($dataArray['totalItems']) && ($dataArray['totalItems'] > 0)) 
			return true;
	
		return false;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getGoogleBooksItemUrl()
	{
		$dataArray = getGoogleBooksItemAsArray();
		
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