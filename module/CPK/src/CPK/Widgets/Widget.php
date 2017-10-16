<?php
/**
 * Object Definition for Widget
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
 * Object Definition for Widget
 *
 * @category VuFind2
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class Widget {
    public $id;
    public $name;
    public $display;
    public $title_cs;
    public $title_en;

    /*
     * Widget contents
     * @var array Array of CPK\Widgets\WidgetContents
     */
    public $contents;

    /*
     * Show link in widget for showing all widget contents
     * @var boolean $show_all_records_link
     **/
    public $show_all_records_link;

    /*
     * How many contents to show in widget
     * @var int $shown_records_number
     **/
    public $shown_records_number;

    /*
     * Show cover?
     * var boolean showCover
     */
    public $show_cover;

    /*
     * What to show as description (author, description)
     * @var string
     */
    public $description;

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getDisplay() {
        return $this->display;
    }

    public function getTitleCs() {
        return $this->title_cs;
    }

    public function getShowAllRecordsLink() {
        return $this->show_all_records_link;
    }

    public function getShownRecordsNumber() {
        return $this->shown_records_number;
    }

    public function getContents() {
        return $this->contents;
    }

    public function getShowCover() {
        return $this->show_cover;
    }

    public function getDescription() {
        return $this->description;
    }

    public function setContents($contents) {
        $this->contents = $contents;
    }

    public function setDisplay($display) {
        $this->display = $display;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setTitleCs($titleCs) {
        $this->title_cs = $titleCs;
    }

    public function setTitleEn($titleEn) {
        $this->title_en = $titleEn;
    }

    public function setShowAllRecordsLink($showAllRecordsLink) {
        $this->show_all_records_link = $showAllRecordsLink;
    }

    public function setShownRecordsNumber($shownRecordsNumber) {
        $this->shown_records_number = $shownRecordsNumber;
    }

    public function setShowCover($showCover) {
        $this->show_cover = $showCover;
    }

    public function setDescription($description) {
        $this->description = $description;
    }
}