<?php
/**
 * Object Definition for InfoboxItem
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
 * Object Definition for InfoboxItem
 *
 * @category VuFind2
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class InfoboxItem {
    private $id;
    private $title_en;
    private $title_cs;
    private $text_en;
    private $text_cs;
    private $date_from;
    private $date_to;

    public function getId() {
        return $this->id;
    }

    public function getTitleEn() {
        return $this->title_en;
    }

    public function getTitleCs() {
        return $this->title_cs;
    }

    public function getTextEn() {
        return $this->text_en;
    }

    public function getTextCs() {
        return $this->text_cs;
    }

    public function getDateFrom() {
        return $this->date_from;
    }

    public function getDateTo() {
        return $this->date_to;
    }

    public function setId($id) {
        return $this->id = $id;
    }

    public function setTitleEn($titleEn) {
        return $this->title_en = $titleEn;
    }

    public function setTitleCs($titleCs) {
        return $this->title_cs = $titleCs;
    }

    public function setTextEn($textEn) {
        return $this->text_en = $textEn;
    }

    public function setTextCs($textCs) {
        return $this->text_cs = $textCs;
    }

    public function setDateFrom($dateFrom) {
        return $this->date_from = $dateFrom;
    }

    public function setDateTo($dateTo) {
        return $this->date_to = $dateTo;
    }
}