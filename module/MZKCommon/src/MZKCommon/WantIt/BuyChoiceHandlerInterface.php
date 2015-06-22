<?php
/**
 * BuyChoiceHandler Interface
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

/**
 * BuyChoiceHandlerInterface
 * Provides available Buy actions on records and handles user's requests.
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
interface BuyChoiceHandlerInterface
{
	/**
	 * Is item available on Amazon
	 * 
	 * @return	boolean
	 */
	public function availableAtGoogleBooks();
	
	/**
	 * Returns Amazon item Url
	 * 
	 * @return	string
	 */
	public function getGoogleBooksItemUrl();
	
	/**
	 * Is item available on Kosmas
	 * 
	 * @return	boolean
	 */
	public function availableAtKosmas();
	
	/**
	 * Returns Kosmas item Url
	 * 
	 * @return	string
	 */
	public function getKosmasItemUrl();
	
	/**
	 * Is item available in Antikvariat
	 * 
	 * @return	boolean
	 */
	public function availableAtAntikvariat();
	
	/**
	 * Returns Antikvariat item Url
	 * 
	 * @return	string
	 */
	public function getAntikvariatItemUrl();
}