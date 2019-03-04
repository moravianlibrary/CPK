<?php
/**
 * Object Definition for DocumentTypesWidget
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Widgets;

/**
 * Object Definition for DocumentTypesWidget
 *
 * @category VuFind2
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class DocumentTypesWidget
{

    /* DocumentTypesWidget list items */
    public $list = [];

    public function __construct($config)
    {
        foreach ($config->Document_Types_Widget->list_item as $item) {
            $data = explode(';', $item, 4);

            $listItem                = new \stdClass();
            $listItem->title         = $data[0];
            $listItem->description   = $data[1];
            $listItem->iconClassName = $data[2];
            $listItem->link          = '/Search/Results/?'
                .'bool0[]=AND&type0[]=AllFields&lookfor0[]=&join=AND&searchTypeTemplate=basic'
                .'&database=Solr&sort=relevance&page=1';

            if ($data[3]) {
                $listItem->link .= '&filter='.specialUrlEncode(\LZCompressor\LZString::compressToBase64($data[3]));
            }

            array_push($this->list, $listItem);
        }

        if ($config->Document_Types_Widget->list_sorting == 'random') {
            shuffle($this->list);
        }
    }
}