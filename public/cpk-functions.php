<?php

function notEmpty($arr, $key)
{
    return isset($arr[$key]) && ! empty($arr[$key]);
}

defined('USES_IE') || define('USES_IE', preg_match('/Windows/', $_SERVER['HTTP_USER_AGENT']));

function standardErrorHandler($errno, $errstr, $errfile, $errline) {
    echo "<b>Custom error:</b> [$errno] $errstr<br>";
    echo " Error on line $errline in $errfile<br>";
}


function fatalErrorHandler() {
    include(__DIR__."/../themes/cpk-devel/templates/error/fatal-error.phtml");
}

set_error_handler("standardErrorHandler");
register_shutdown_function('fatalErrorHandler');