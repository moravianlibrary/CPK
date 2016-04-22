<?php
/**
 * GoogleTagManager view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\View\Helper\Root;

/**
 * GoogleTagManager view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class GoogleTagManager extends \Zend\View\Helper\AbstractHelper
{

    /**
     * API key (false if disabled)
     *
     * @var string|bool
     */
    protected $key;

    /**
     * Constructor
     *
     * @param array $config configuration
     */
    public function __construct($config)
    {
        $this->key = isset($config->key)? $config->key : false;
    }

    /**
     * Returns GA code (if active) or empty string if not.
     *
     * @return string
     */
    public function __invoke()
    {
        if (!$this->key) {
            return '';
        }
        $key = json_encode($this->key);
        $jsCode = <<<EOF
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer',{$key});
EOF;
        $iframeCode = <<<EOF
 <noscript>
   <iframe src="//www.googletagmanager.com/ns.html?id={$key}"
     height="0" width="0" style="display:none;visibility:hidden">
   </iframe>
</noscript>
EOF;
        $inlineScript = $this->getView()->plugin('inlinescript');
        $inlineCode = $inlineScript(\Zend\View\Helper\HeadScript::SCRIPT, $jsCode, 'SET');
        return $iframeCode . $inlineCode;
    }

}
