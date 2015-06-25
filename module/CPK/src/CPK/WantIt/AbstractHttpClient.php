<?php
/**
 * AbstractHttpClient Class
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
 * AbstractHttpClient
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
abstract class AbstractHttpClient
{
	/**
	 * TrustSSLHost
	 * @var	boolean
	 */
	protected $trustSSLHost;
	
	public function __construct()
	{
		$this->trustSSLHost	= isset($config->WantIt->trust_ssl_host) ? $config->WantIt->trust_ssl_host: true;
	}
	
	/**
	 * Returns build url string
	 *
	 * @param	string	$url	"http://domain.tld"
	 * @param	array	$params	GET params
	 *
	 * @return	string
	 */
	protected function buildQuery($url, array $params)
	{
		$query = http_build_query($params);
		$url   = $url.'?'.$query;
	
		return $url;
	}
	
	/**
	 * Returns page content from Piwik API Request
	 *
	 * CURLOPT_HEADER - Include header in result? (0 = yes, 1 = no)
	 * CURLOPT_RETURNTRANSFER - (true = return, false = print) data
	 *
	 * @param	string	$url	"http://domain.tld"
	 * @param	array	$params	GET params
	 * @throws	\Exception when cURL us not installed
	 * @throws	\Exception when Json cannot be decoded
	 * 			or the encoded data is deeper than the recursion limit.
	 * @throws	\Exception when response body contains error element
	 * @throws	\Exception when reponse status code is not 200
	 * @return	array
	 */
	protected function getRequestDataResponseFromJSON($url, array $params)
	{
		$url = $this->buildQuery($url, $params);
	
		if (! function_exists('curl_init'))
			throw new \Exception('cURL is not installed!');
	
		$curlAdapterConfig = array(
			'adapter'     => '\Zend\Http\Client\Adapter\Curl',
			'curloptions' => array(
				CURLOPT_FOLLOWLOCATION 	=> true,
				CURLOPT_REFERER			=> $this->defaultStatisticsUrl,
				CURLOPT_USERAGENT		=> "Mozilla/5.0",
				CURLOPT_HEADER			=> 0,
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_TIMEOUT			=> 10,
				CURLOPT_SSL_VERIFYHOST	=> ($this->trustSSLHost) ? 0 : 2,
				CURLOPT_SSL_VERIFYPEER	=> ($this->trustSSLHost) ? 0 : 1,
			),
		);
	
		$client = new \Zend\Http\Client($url, $curlAdapterConfig);
		$response = $client->send();
	
		// Response head error handling
		$responseStatusCode = $response->getStatusCode();
		if($responseStatusCode !== 200)
			throw new \Exception("Response status code: ".$responseStatusCode);
		//
	
		$output	= $response->getBody();
	
		$dataArray = \Zend\Json\Json::decode($output, \Zend\Json\Json::TYPE_ARRAY);
	
		if ($dataArray === NULL)
			throw new \Exception('Json cannot be decoded or the encoded data is deeper than the recursion limit.');
			
		return $dataArray;
	}
}