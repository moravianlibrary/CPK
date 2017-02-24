<?php
/**
 * Exceptions Trait
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
 * @package  Controller
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

trait ExceptionsTrait
{

    /**
     * This method represents an exception flashing system for backend without access to
     * rendering the template
     *
     * Just add an array value to $_ENV['exceptions'] inside any Backend class to flash it later ..
     *
     * If the key of appended array value will match any of User's registered institutions, it'll be
     * shown as related to that institution.
     *
     * @param \Zend\Mvc\Controller\Plugin\FlashMessenger $flashMessenger
     *
     * @return void
     */
    protected function flashExceptions(
        \Zend\Mvc\Controller\Plugin\FlashMessenger $flashMessenger)
    {
        if (isset($_ENV['exceptions'])) {
            foreach ($_ENV['exceptions'] as $source => $exception) {

                // We actually cannot print multi-lined exception -> divide it into separate ones ..
                $exceptions = explode("\n", $exception);

                if ($exceptions == null) // It is probably an array
                    $exceptions = $exception;

                foreach ($exceptions as $exception) {

                    if (! is_numeric($source))
                        $exception = $source . ':' . $exception;

                    $flashMessenger->addErrorMessage($exception);
                }
            }

            unset($_ENV['exceptions']);
        }
    }
}