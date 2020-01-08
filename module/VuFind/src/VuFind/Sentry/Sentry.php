<?php
/**
 * Sentry support.
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
 * @package  Sentry
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Vufind\Sentry;

/**
 * Sentry support.
 *
 * Helper class for sending errors to sentry.io.
 *
 * @category VuFind2
 * @package  Sentry
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Sentry {

    private static $errorHandler = null;

    public static function initialize()
    {
        if (!empty($_SERVER['SENTRY_DSN'])) {
            $dsn = $_SERVER['SENTRY_DSN'];
            $sentryClient = new \Raven_Client($dsn);
            self::$errorHandler = new \Raven_ErrorHandler($sentryClient);
            self::$errorHandler->registerExceptionHandler();
            $errorTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING
                | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED;
            self::$errorHandler->registerErrorHandler(true, $errorTypes);
            self::$errorHandler->registerShutdownFunction();
        }
    }

    public static function handleErrorException($exception)
    {
        if (self::$errorHandler == null) {
            cpkExceptionHandler($exception);
        } else {
            self::$errorHandler->handleException($exception, true);
        }
    }

}