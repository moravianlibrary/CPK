<?php
/**
 * View helper to render a portion of an array.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\View\Helper\CPK;

use Zend\View\Helper\AbstractHelper;

/**
 * View helper to render a portion of an array.
 *
 * @category VuFind2
 * @package View_Helpers
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RenderArray extends AbstractHelper
{

    /**
     * Render a portion of an array.
     *
     * If $sync is TRUE, then $arr can be null. But also please
     * keep in mind to include %%KEY%% value into the template
     * in order to place the key for AJAX selector in proper place.
     *
     * @param string $tpl
     *            A template for displaying each row. This should
     *            include %%LABEL%% and %%VALUE%% placeholders
     * @param array $arr
     *            An associative array of possible values to display
     * @param array $rows
     *            A label => profile key associative array specifying
     *            which rows of $arr to display
     *
     * @param bool $async
     *            If the data will be loaded later by AJAX
     *
     * @return string
     */
    public function __invoke($tpl, $arr, $rows, $async = false)
    {
        $html = '';
        if (! $async) {
            foreach ($rows as $label => $key) {
                if (isset($arr[$key])) {
                    $html .= str_replace(
                        [
                            '%%LABEL%%',
                            '%%VALUE%%'
                        ],
                        [
                            $label,
                            $this->view->escapeHtml($arr[$key])
                        ], $tpl);
                }
            }
        } else {
            foreach ($rows as $label => $key) {
                $html .= str_replace(
                    [
                        '%%LABEL%%',
                        '%%KEY%%'
                    ],
                    [
                        $label,
                        $key
                    ], $tpl);
            }
        }
        return $html;
    }
}