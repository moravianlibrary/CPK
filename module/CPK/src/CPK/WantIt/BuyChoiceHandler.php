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
class BuyChoiceHandler extends AbstractHttpClient implements BuyChoiceHandlerInterface
{
	/**
	 * RecordDriver
	 * @var	\CPK\RecordDriver\SolrMarc	$recordDriver
	 */
	protected $recordDriver;

	public function __construct(\CPK\RecordDriver\SolrMarc $recordDriver)
	{
		$this->recordDriver = $recordDriver;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGoogleBooksVolumeLink()
	{
		$isbn = (! empty($isbns = $this->recordDriver->getISBNs())) ? $isbns[0] : null;
		$lccn = $this->recordDriver->getLCCN();
		$oclc = $this->recordDriver->getCleanOCLCNum();

		if ((! $isbn) && (! $lccn) && (! $oclc))
			return false;

		$url = 'https://www.googleapis.com/books/v1/volumes';

		if ($isbn)
			$params = array ('q' => 'isbn:'.str_replace("-", "", $isbn));

		if ($lccn)
			$params = array ('q' => 'lccn:'.str_replace("-", "", $lccn));

		if ($oclc)
			$params = array ('q' => 'oclc:'.str_replace("-", "", $oclc));

		$dataArray = $this->getRequestDataResponseAsArray($url, $params);

		$link = $dataArray['items'][0]['volumeInfo']['canonicalVolumeLink'];

		if (! isset($link) || empty($link))
			return null;

		return $link;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getZboziLink()
	{
		$isbn = (! empty($isbns = $this->recordDriver->getISBNs())) ? $isbns[0] : null;
		if (! $isbn)
			return false;

		$url = 'http://www.zbozi.cz/api/v1/search';
		$params = array (
				'groupByCategory' => 1,
				'loadTopProducts' => 'true',
				'page' => 1,
				'query' => str_replace("-", "", $isbn),
		);

		$dataArray = $this->getRequestDataResponseAsArray($url, $params);

		$link = '';
		if (isset($dataArray['status']) && $dataArray['status'] === 200)
			if (isset($dataArray['productsGroupedByCategories'][0]['products'][0]['normalizedName']) && $dataArray['productsGroupedByCategories'][0]['products'][0]['normalizedName'] !== '')
				$link = 'http://www.zbozi.cz/vyrobek/'.$dataArray['productsGroupedByCategories'][0]['products'][0]['normalizedName'].'/';

		return $link;
	}
}