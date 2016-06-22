<?php
/**
 * Object Definition for WidgetContent
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
 * Object Definition for WidgetContent
 *
 * @category VuFind2
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class WidgetContent {
    private $id;
    private $widget_id;
    private $value;
    private $preferred_value;
    private $recordDriver;

    public function getId() {
        return $this->id;
    }

    public function getWidgetId() {
        return $this->widget_id;
    }

    public function getValue() {
        return $this->value;
    }

    public function getPreferredValue() {
        return $this->preferred_value;
    }

    public function getRecordDriver() {
        return $this->recordDriver;
    }

    public function setRecordDriver($recordDriver) {
        $this->recordDriver = $recordDriver;
    }
}