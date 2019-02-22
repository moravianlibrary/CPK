<?php

namespace Vufind\Sentry;

class Sentry {

    private static $errorHandler = null;

    public static function initialize()
    {
        if (!empty($_SERVER['SENTRY_DSN'])) {
            $dsn = $_SERVER['SENTRY_DSN'];
            $sentryClient = new \Raven_Client($dsn);
            self::$errorHandler = new \Raven_ErrorHandler($sentryClient);
            self::$errorHandler->registerExceptionHandler();
            $errorTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED;
            self::$errorHandler->registerErrorHandler(true, $errorTypes);
            self::$errorHandler->registerShutdownFunction();
        }
    }

    public static function handleErrorException($exception)
    {
        if (self::$errorHandler != null) {
            self::$errorHandler->handleException($exception, true);
        }
    }

}