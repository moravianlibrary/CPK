<?php
use Zend\Loader\AutoloaderFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;
if (! (php_sapi_name() != 'cli' OR defined('STDIN') || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0))) {
    if (isset($_SERVER['VUFIND_ENV'])) {
        if ($_SERVER['VUFIND_ENV'] == 'production') {
            error_reporting(E_ALL); // Report all PHP errors
            ini_set("display_errors", 0);
        } else if ($_SERVER['VUFIND_ENV'] == 'development') { // DEVELOPMENT
            error_reporting(E_ALL); // Report all PHP errors
            ini_set("display_errors", 1);
        } else {
            exit('Variable VUFIND_ENV has strange value in Apache config! [Ignore this message when in CLI]');
        }
    } else {
        exit('Variable VUFIND_ENV is not set in Apache config! [Ignore this message when in CLI]');
    }
}

function friendlyErrorType($type)
{
    switch($type)
    {
        case E_ERROR: // 1 //
            return 'E_ERROR';
        case E_WARNING: // 2 //
            return 'E_WARNING';
        case E_PARSE: // 4 //
            return 'E_PARSE';
        case E_NOTICE: // 8 //
            return 'E_NOTICE';
        case E_CORE_ERROR: // 16 //
            return 'E_CORE_ERROR';
        case E_CORE_WARNING: // 32 //
            return 'E_CORE_WARNING';
        case E_COMPILE_ERROR: // 64 //
            return 'E_COMPILE_ERROR';
        case E_COMPILE_WARNING: // 128 //
            return 'E_COMPILE_WARNING';
        case E_USER_ERROR: // 256 //
            return 'E_USER_ERROR';
        case E_USER_WARNING: // 512 //
            return 'E_USER_WARNING';
        case E_USER_NOTICE: // 1024 //
            return 'E_USER_NOTICE';
        case E_STRICT: // 2048 //
            return 'E_STRICT';
        case E_RECOVERABLE_ERROR: // 4096 //
            return 'E_RECOVERABLE_ERROR';
        case E_DEPRECATED: // 8192 //
            return 'E_DEPRECATED';
        case E_USER_DEPRECATED: // 16384 //
            return 'E_USER_DEPRECATED';
        default:
            return 'UNKNOWN ERROR TYPE';
    }
    return "";
}

/**
 * throw exceptions based on E_* error types
 */
set_error_handler(function ($err_severity, $err_msg, $err_file, $err_line, array $err_context)
{
    // error was suppressed with the @-operator
    if (0 === error_reporting()) { return false;}

    $logDetails = date("Y-m-d H:i:s ");

    $logDetails .= friendlyErrorType($err_severity)." \n";
    $logDetails .= "$err_msg\n";
    $logDetails .= "Error on line $err_line in file $err_file\n\n";

    $logFile = __DIR__."/../log/fatal-errors.log";
    $fp = fopen($logFile, "a");
    fwrite($fp, $logDetails);
    fwrite($fp, "");
    fclose($fp);

    if (! (php_sapi_name() != 'cli' OR defined('STDIN') || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0))) {
        if (isset($_SERVER['VUFIND_ENV'])) {
            if ($_SERVER['VUFIND_ENV'] == 'production') {

                $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $extra = 'error.php';
                @header("Location: http://$host$uri/$extra");

                include_once(__DIR__."/../themes/cpk-devel/templates/error/fatal-error.phtml");
                exit;

            } else if ($_SERVER['VUFIND_ENV'] == 'development') { // DEVELOPMENT

                // continue with showing stacktrace
                echo $logDetails;
                //var_dump($err_context);
                exit();

            } else {
                exit('Variable VUFIND_ENV has strange value in Apache config! [Ignore this message when in CLI]');
            }
        } else {
            exit('Variable VUFIND_ENV is not set in Apache config! [Ignore this message when in CLI]');
        }
    }

/*
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
    }*/
});
/*
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
*/
//trigger_error('error');
/*
function exceptionHandler($exception) {
    $logDetails = date("Y-m-d H:i:s ");
    $logDetails .= "EXCEPTION \n";
    $logDetails .= $exception->getMessage()."\n";
    $logDetails .= $exception->getTraceAsString()."\n";
    $logDetails .= "Error on line ".$exception->getLine()." in file ".$exception->getFile()."\n\n";

    $logFile = __DIR__."/../fatal-errors.log";
    $fp = fopen($logFile, "a");
    fwrite($fp, $logDetails);
    fwrite($fp, "");
    fclose($fp);

    if (isset($_SERVER['VUFIND_ENV'])) {
        if ($_SERVER['VUFIND_ENV'] == 'production') {

            $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $extra = 'error.php';
            @header("Location: http://$host$uri/$extra");
            exit;

        } else if ($_SERVER['VUFIND_ENV'] == 'development') { // DEVELOPMENT

            // continue with showing stacktrace
            echo $logDetails;
            exit();

        } else {
            exit('Variable VUFIND_ENV has strange value in Apache config! [Ignore this message when in CLI]');
        }
    } else {
        exit('Variable VUFIND_ENV is not set in Apache config! [Ignore this message when in CLI]');
    }
}

set_exception_handler('exceptionHandler');
*/
// If the XHProf profiler is enabled, set it up now:
$xhprof = getenv('VUFIND_PROFILER_XHPROF');
if (!empty($xhprof) && extension_loaded('xhprof')) {
    xhprof_enable();
} else {
    $xhprof = false;
}

// Define path to application directory
defined('APPLICATION_PATH')
    || define(
        'APPLICATION_PATH',
        (getenv('VUFIND_APPLICATION_PATH') ? getenv('VUFIND_APPLICATION_PATH')
            : dirname(__DIR__))
    );

// Define application environment
defined('APPLICATION_ENV')
    || define(
        'APPLICATION_ENV',
        (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'production')
    );

// Define default search backend identifier
defined('DEFAULT_SEARCH_BACKEND') || define('DEFAULT_SEARCH_BACKEND', 'Solr');

// Define path to local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define(
        'LOCAL_OVERRIDE_DIR',
        (getenv('VUFIND_LOCAL_DIR') ? getenv('VUFIND_LOCAL_DIR') : '')
    );

// Define path to cache directory
defined('LOCAL_CACHE_DIR')
    || define(
        'LOCAL_CACHE_DIR',
        (getenv('VUFIND_CACHE_DIR')
            ? getenv('VUFIND_CACHE_DIR')
            : (strlen(LOCAL_OVERRIDE_DIR) > 0 ? LOCAL_OVERRIDE_DIR . '/cache' : ''))
    );

// Save original working directory in case we need to remember our context, then
// switch to the application directory for convenience:
define('ORIGINAL_WORKING_DIRECTORY', getcwd());
chdir(APPLICATION_PATH);

// Ensure vendor/ is on include_path; some PEAR components may not load correctly
// otherwise (i.e. File_MARC may cause a "Cannot redeclare class" error by pulling
// from the shared PEAR directory instead of the local copy):
$pathParts = array();
$pathParts[] = APPLICATION_PATH . '/vendor';
$pathParts[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $pathParts));

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    $loader = include 'vendor/autoload.php';
}

// Support for ZF2_PATH environment variable
if ($zf2Path = getenv('ZF2_PATH')) {
    if (isset($loader)) {
        $loader->add('Zend', $zf2Path . '/Zend');
    } else {
        include $zf2Path . '/Zend/Loader/AutoloaderFactory.php';
        AutoloaderFactory::factory();
    }
}

if (!class_exists('Zend\Loader\AutoloaderFactory')) {
    throw new RuntimeException('Unable to load ZF2.');
}

$cpkFunction = __DIR__ . '/cpk-functions.php';

if (file_exists($cpkFunction))
    require_once($cpkFunction);

// Run the application!
Zend\Mvc\Application::init(require 'config/application.config.php')->run();

// Handle final profiling details, if necessary:
if ($xhprof) {
    $xhprofData = xhprof_disable();
    include_once "xhprof_lib/utils/xhprof_lib.php";
    include_once "xhprof_lib/utils/xhprof_runs.php";
    $xhprofRuns = new XHProfRuns_Default();
    $suffix = 'vufind2';
    $xhprofRunId = $xhprofRuns->save_run($xhprofData, $suffix);
    $url = "$xhprof?run=$xhprofRunId&source=$suffix";
    echo "<a href='$url'>Profiler output</a>";
}