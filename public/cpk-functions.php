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
 * Dump and die
 * @param mixed $var
 */
function dd($var) {
    var_dump($var);
    exit();
}

/**
 * Die
 * @param mixed $var
 */
function d($var) {
    var_dump($var);
}

function remove_accent($str)
{
    $a = array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ü','ý','ÿ','Ā','ā','Ă','ă','Ą','ą','Ć','ć','Ĉ','ĉ','Ċ','ċ','Č','č','Ď','ď','Đ','đ','Ē','ē','Ĕ','ĕ','Ė','ė','Ę','ę','Ě','ě','Ĝ','ĝ','Ğ','ğ','Ġ','ġ','Ģ','ģ','Ĥ','ĥ','Ħ','ħ','Ĩ','ĩ','Ī','ī','Ĭ','ĭ','Į','į','İ','ı','Ĳ','ĳ','Ĵ','ĵ','Ķ','ķ','Ĺ','ĺ','Ļ','ļ','Ľ','ľ','Ŀ','ŀ','Ł','ł','Ń','ń','Ņ','ņ','Ň','ň','ŉ','Ō','ō','Ŏ','ŏ','Ő','ő','Œ','œ','Ŕ','ŕ','Ŗ','ŗ','Ř','ř','Ś','ś','Ŝ','ŝ','Ş','ş','Š','š','Ţ','ţ','Ť','ť','Ŧ','ŧ','Ũ','ũ','Ū','ū','Ŭ','ŭ','Ů','ů','Ű','ű','Ų','ų','Ŵ','ŵ','Ŷ','ŷ','Ÿ','Ź','ź','Ż','ż','Ž','ž','ſ','ƒ','Ơ','ơ','Ư','ư','Ǎ','ǎ','Ǐ','ǐ','Ǒ','ǒ','Ǔ','ǔ','Ǖ','ǖ','Ǘ','ǘ','Ǚ','ǚ','Ǜ','ǜ','Ǻ','ǻ','Ǽ','ǽ','Ǿ','ǿ');
    $b = array('A','A','A','A','A','A','AE','C','E','E','E','E','I','I','I','I','D','N','O','O','O','O','O','O','U','U','U','U','Y','s','a','a','a','a','a','a','ae','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','o','u','u','u','u','y','y','A','a','A','a','A','a','C','c','C','c','C','c','C','c','D','d','D','d','E','e','E','e','E','e','E','e','E','e','G','g','G','g','G','g','G','g','H','h','H','h','I','i','I','i','I','i','I','i','I','i','IJ','ij','J','j','K','k','L','l','L','l','L','l','L','l','l','l','N','n','N','n','N','n','n','O','o','O','o','O','o','OE','oe','R','r','R','r','R','r','S','s','S','s','S','s','S','s','T','t','T','t','T','t','U','u','U','u','U','u','U','u','U','u','U','u','W','w','Y','y','Y','Z','z','Z','z','Z','z','s','f','O','o','U','u','A','a','I','i','O','o','U','u','U','u','U','u','U','u','U','u','A','a','AE','ae','O','o');
    return str_replace($a, $b, $str);
}
