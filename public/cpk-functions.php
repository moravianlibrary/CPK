<?php
function notEmpty($arr, $key) {
    return isset($arr[$key]) && ! empty($arr[$key]);
}