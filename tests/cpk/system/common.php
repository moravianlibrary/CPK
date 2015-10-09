<?php
if ((isset($_SERVER['ERROR_REPORTING']))) {
    $levels = explode(",", $_SERVER['ERROR_REPORTING']);

    $value = 0;

    foreach ($levels as $level) {
        $value |= (int)constant('E_'.strtoupper($level));
    }

    if ($value > 0) {
        error_reporting($value);
        ini_set("display_errors", 1);
    } else {
        error_reporting($value);
        ini_set("display_errors", 0);
    }
}
