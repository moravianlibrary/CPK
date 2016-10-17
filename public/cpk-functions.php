<?php

function notEmpty($arr, $key)
{
    return isset($arr[$key]) && ! empty($arr[$key]);
}

/*
 * The User-Agent header is optional.
 * Firewalls may filter it or people may configure their clients to omit it.
 * Simply check using isset() if it exists. Or even better, use !empty()
 * as an empty header won't be useful either
 */
$userAgent = isset($_SERVER['HTTP_USER_AGENT'])
    ? strtolower($_SERVER['HTTP_USER_AGENT'])
    : '';
defined('USES_IE') || define('USES_IE', preg_match('/MSIE|Trident/i', $userAgent));

/**
 * throw exceptions based on E_* error types
 */
set_error_handler(function ($err_severity, $err_msg, $err_file, $err_line, array $err_context)
{
    // error was suppressed with the @-operator
    if (0 === error_reporting()) { return false;}

    $logDetails = date("Y-m-d H:i:s ");

    switch ($errno) {
        case E_USER_ERROR:
            $logDetails .= "ERROR $errno \n";
            $logDetails .= "$err_msg\n";
            $logDetails .= "Error on line $err_line in file $err_file\n\n";
            break;

        case E_USER_WARNING:
            $logDetails .= "WARNING $errno \n";
            $logDetails .= "$err_msg\n";
            $logDetails .= "Error on line $err_line in file $err_file\n\n";
            break;

        case E_USER_NOTICE:
            $logDetails .= "NOTICE $errno \n";
            $logDetails .= "$err_msg\n";
            $logDetails .= "Error on line $err_line in file $err_file\n\n";
            break;

        default:
            $logDetails .= "UNKNOWN ERROR TYPE $errno \n";
            $logDetails .= "$err_msg\n";
            $logDetails .= "Error on line $err_line in file $err_file\n\n";
            break;
    }

    $logFile = __DIR__."/../fatal-errors.log";
    $fp = fopen($logFile, "a");
    fwrite($fp, $logDetails);
    fwrite($fp, "");
    fclose($fp);

    switch($err_severity)
    {
        case E_ERROR:               throw new ErrorException            ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_WARNING:             throw new WarningException          ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_PARSE:               throw new ParseException            ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_NOTICE:              throw new NoticeException           ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_CORE_ERROR:          throw new CoreErrorException        ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_CORE_WARNING:        throw new CoreWarningException      ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_COMPILE_ERROR:       throw new CompileErrorException     ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_COMPILE_WARNING:     throw new CoreWarningException      ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_USER_ERROR:          throw new UserErrorException        ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_USER_WARNING:        throw new UserWarningException      ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_USER_NOTICE:         throw new UserNoticeException       ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_STRICT:              throw new StrictException           ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_RECOVERABLE_ERROR:   throw new RecoverableErrorException ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_DEPRECATED:          throw new DeprecatedException       ($err_msg, 0, $err_severity, $err_file, $err_line);
        case E_USER_DEPRECATED:     throw new UserDeprecatedException   ($err_msg, 0, $err_severity, $err_file, $err_line);
    }
});

class WarningException              extends ErrorException {}
class ParseException                extends ErrorException {}
class NoticeException               extends ErrorException {}
class CoreErrorException            extends ErrorException {}
class CoreWarningException          extends ErrorException {}
class CompileErrorException         extends ErrorException {}
class CompileWarningException       extends ErrorException {}
class UserErrorException            extends ErrorException {}
class UserWarningException          extends ErrorException {}
class UserNoticeException           extends ErrorException {}
class StrictException               extends ErrorException {}
class RecoverableErrorException     extends ErrorException {}
class DeprecatedException           extends ErrorException {}
class UserDeprecatedException       extends ErrorException {}

function fatalErrorHandler() {
    include_once(__DIR__."/../themes/cpk-devel/templates/error/fatal-error.phtml");
    exit();
}

set_error_handler("standardErrorHandler");
register_shutdown_function('fatalErrorHandler');
