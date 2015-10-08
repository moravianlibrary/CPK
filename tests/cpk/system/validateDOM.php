<?php

function getSource($url) {
    $ch = curl_init();
    $timeout = 50;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function validateDOM($url) { 
    $html = new DOMDocument();
    libxml_use_internal_errors(true);
    $html->loadHTML(getSource($url));
    libxml_clear_errors();
    //$xpath = new DOMXPath($html);
    
    //$results = $xpath->query("//*[@id='results']")->item(0);
    $element = $html->getElementById('results');
    $results = $html->saveHTML($element);
    
    return $results;
}

file_put_contents(__DIR__.'/../template/data/accessibility/homepage.log', validateDOM('https://validator.w3.org/nu/?doc=https%3A%2F%2Fbeta.knihovny.cz%2F'));
file_put_contents(__DIR__.'/../template/data/accessibility/search-results.log', validateDOM('https://validator.w3.org/nu/?doc=https%3A%2F%2Fbeta.knihovny.cz%2FSearch%2FResults%3Flookfor%3Dphp%26type%3DAllFields%26limit%3D10%26sort%3Drelevance'));
file_put_contents(__DIR__.'/../template/data/accessibility/record.log', validateDOM('https://validator.w3.org/nu/?useragent=Validator.nu%2FLV+http%3A%2F%2Fvalidator.w3.org%2Fservices&doc=https%3A%2F%2Fbeta.knihovny.cz%2FRecord%2Fcaslin.SKC01-001491759'));