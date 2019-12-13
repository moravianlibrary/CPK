<?php
/**
 * Feedback view helper
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

use VuFind\View\Helper\Root\Feedback as FeedbackBase;

/**
 * Class Feedback
 * @package CPK\View\Helper\CPK
 * @property string $view
 * @property string $tab
 */
class Feedback extends FeedbackBase
{
    /**
     * Site key for recapcha
     *
     * @var string
     */
    protected $siteKey;

    /**
     * Configuration for feedback
     *
     * @var array
     */
    protected $config;

    /**
     * Current url from user has sent feedback
     *
     * @var string
     */
    protected $actualLink;

    /**
     * Feedback constructor.
     *
     * @param bool $enabled
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;

        $this->initConfiguration();
    }

    /**
     * Init configurations for feedback
     */
    public function initConfiguration()
    {
        //set site and secret keys for captcha
        $this->siteKey = $this->config->Captcha->siteKey;
    }

    /**
     * Gets protected property
     *
     * @param $name
     * @return string
     */
    public function __get($name)
    {
        switch ($name) {
            case 'siteKey':
                return $this->siteKey;
            case 'actualLink':
                return $this->actualLink;
        }
    }
}