<?php

function notEmpty($arr, $key)
{
    return isset($arr[$key]) && ! empty($arr[$key]);
}

defined('USES_IE') || define('USES_IE', preg_match('/Windows/', $_SERVER['HTTP_USER_AGENT']));