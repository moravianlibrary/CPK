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
    ? ! empty($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : ''
    : '';
defined('USES_IE') || define('USES_IE', preg_match('/MSIE|Trident/i', $userAgent));

/*
 * This functions is used like standard urlencode,
 * but insted of double encode, this creates url friedly string for
 * base64 encoding/decoding.
 *
 * @param   string  $input
 *
 * @return  string
 */
function specialUrlEncode($input) {
    return strtr($input, '+/=', '-_.');
}

/*
 * This functions is used like standard urldecode,
 * but insted of double decode, this creates url friedly string for
 * base64 encoding/decoding.
 *
 * @param   string  $input
 *
 * @return  string
 */
function specialUrlDecode($input) {
    if (is_array($input)) {
        $input = $input[0];
    }
    return strtr($input, '-_.', '+/=');
}
