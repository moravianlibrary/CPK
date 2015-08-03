<?php
/**
 * ElectronicChoiceHandler Model Class
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

use CPK\WantIt\ElectronicChoiceHandlerInterface;
use CPK\WantIt\AbstractHttpClient;

/**
 * ElectronicChoiceHandler Model
 * Provides available Electronic actions on records and handles user's requests.
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class ElectronicChoiceHandler extends AbstractHttpClient implements ElectronicChoiceHandlerInterface
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
	 * Detects if url is found and not forbidden
	 * 
	 * @param	string	$url
	 * 
	 * @return	boolean
	 */
	public function isAccessible($url)
	{
		return (get_headers($url)[0] === 'HTTP/1.1 200 OK');
	}
}
	