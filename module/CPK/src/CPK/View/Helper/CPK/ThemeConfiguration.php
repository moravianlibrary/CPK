<?php
/**
 * Theme confuguration view helper
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2018.
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Inhliziian Bohdan <inhliziian@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\View\Helper\CPK;

use WebDriver\Exception;
use Zend\View\Helper\AbstractHelper;

/**
 * Class ThemeConfiguration
 *
 * @package CPK\View\Helper\CPK
 */
class ThemeConfiguration extends AbstractHelper
{
	/**
	 * Default theme configuration
	 */
	const DEFAULT_THEME_CONFIG = [
		'logo_image'           => false,
		'hide_navbar'          => false,
		'hide_catalog'         => false,
		'hide_inspirations'    => false,
		'hide_library_search'  => false,
		'hide_switch_language' => false,
		'hide_eds_source'      => false
	];

	/**
	 * Site configuration
	 *
	 * @var \Zend\Config\Config
	 */
	protected $config;

	/**
	 * Configuration
	 *
	 * @var array
	 */
	protected $themeConfig;

	/**
	 * ThemeConfiguration constructor.
	 *
	 * @param \Zend\Config\Config $config
	 */
	public function __construct(\Zend\Config\Config $config)
	{
		$this->config = $config;

		$this->initThemeConfiguration();
	}

	/**
	 * Gets protected properties
	 *
	 * @param $name
	 * @return array
	 */
	public function __get($name)
	{
		switch ($name) {
			case 'themeConfig':
				return $this->themeConfig;
		}
	}

	/**
	 * Init theme configuration
	 */
	public function initThemeConfiguration() {
		try {
			$themeConfigProperties = $this->config->Theme->toArray();
		} catch (Exception $e) {
			$themeConfigProperties = [];
		}

		if ($themeConfigProperties) {
			$this->themeConfig = array_merge($this::DEFAULT_THEME_CONFIG, $themeConfigProperties);
		}
	}
}