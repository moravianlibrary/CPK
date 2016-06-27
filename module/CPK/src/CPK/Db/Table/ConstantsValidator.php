<?php
/**
 * Abstract class for checking validity of deefined constants within a class.
 *
 * PHP version 7
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
 * @package  Service
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

abstract class ConstantsValidator extends Gateway
{

    protected static $constCache = [];

    /**
     * Throws an exception if there is provided unknown constant value.
     *
     * @param string $constantValue
     * @throws \Exception
     */
    public static function assertValid($constantValue) {

        $calledClass = get_called_class();

        if (! isset(static::$constCache[$calledClass])) {
            $constants = (new \ReflectionClass(get_called_class()))->getConstants();

            static::$constCache[$calledClass] = array_values($constants);
        }

        if (! in_array($constantValue, static::$constCache[$calledClass])) {
            throw new \Exception(static::getInvalidConstantValueMessage());
        }
    }

    /**
     * Returns string which is about to be inserted into Exception as a message
     * when the constant provided to assertValid method is not found.
     *
     * @return string $exceptionMessage
     */
    protected static function getInvalidConstantValueMessage() {
        return "Invalid constant value provided to " . get_called_class();
    }
}