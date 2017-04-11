<?php

namespace Debug\ILS\Driver;

use CPK\ILS\Driver\XCNCIP2 as XCNCIP2Base;
use VuFind\Exception\ILS as ILSException;

class XCNCIP2 extends XCNCIP2Base
{
    protected function sendRequest($xml)
    {
        $locale = $this->translator->getLocale();

        if ($locale == 'dg')
        {
            $e = null;
            try {
                $response = parent::sendRequest($xml);
            }
            catch (ILSException $e) {
                $this->makeLog($this->formatXml($xml), $e->getMessage());
                throw $e;
            }
            $this->makeLog($this->formatXml($xml), $this->formatXml($response));
        }
        else {
            $response = parent::sendRequest($xml);
        }
        return $response;
    }

    protected function makeLog($request, $response) {
        $requestFormatted = '<pre>' . $this->xml_highlight($request) . '</pre>';
        $responseFormatted = '<pre>' . $this->xml_highlight($response) . '</pre>';

        $logContent = file_get_contents("log/ncip-messages.html");

        $time = strtok($logContent, "\n");
        if (time() - $time < 5) {
            $file = fopen("log/ncip-messages.html", "a");
        }
        else {
            $file = fopen("log/ncip-messages.html", "w");
        }

        fwrite($file, time() . "\n");
        fwrite($file, $requestFormatted . $responseFormatted);
        fclose($file);

        $logContent = file_get_contents("log/ncip-messages.html");

        if ($logContent) {
            $GLOBALS['dg'] = $logContent;
        }
        else {
            $GLOBALS['dg'] .= $requestFormatted . $responseFormatted;
        }
    }

    protected function formatXml($xml) {
        $domxml = new \DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        if ((is_object($xml)) && (get_class($xml) == 'SimpleXMLElement')) {
            $domxml->loadXML($xml->asXML());
        }
        else {
            $domxml->loadXML($xml);
        }
        return $domxml->saveXML();
    }

    function xml_highlight($s)
    {
        $s = htmlspecialchars($s);
        $s = preg_replace("#&gt;&lt;#sU", "&gt;\n&lt;",$s);
        $s = preg_replace("#&lt;([/]*?)(.*)([\s]*?)&gt;#sU",
            "<font color=\"#0000FF\">&lt;\\1\\2\\3&gt;</font>",$s);
        $s = preg_replace("#&lt;([\?])(.*)([\?])&gt;#sU",
            "<font color=\"#800000\">&lt;\\1\\2\\3&gt;</font>",$s);
        $s = preg_replace("#&lt;([^\s\?/=])(.*)([\[\s/]|&gt;)#iU",
            "&lt;<font color=\"#808000\">\\1\\2</font>\\3",$s);
        $s = preg_replace("#&lt;([/])([^\s]*?)([\s\]]*?)&gt;#iU",
            "&lt;\\1<font color=\"#808000\">\\2</font>\\3&gt;",$s);
        $s = preg_replace("#([^\s]*?)\=(&quot;|')(.*)(&quot;|')#isU",
            "<font color=\"#800080\">\\1</font>=<font color=\"#333\">\\2\\3\\4</font>",$s);
        $s = preg_replace("#&lt;(.*)(\[)(.*)(\])&gt;#isU",
            "&lt;\\1<font color=\"#800080\">\\2\\3\\4</font>&gt;",$s);
        return $s;
    }
}