<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 11.2.18
 * Time: 4:14
 */

namespace CPK\ILS\Logic\XmlTransformation;

use JsonSerializable;
use SimpleXMLElement;

/**
 * Class JsonSimpleXMLElementDecorator
 *
 * Implement JsonSerializable for SimpleXMLElement as a Decorator
 */
class SimpleXMLNestedAttributesSerializable implements JsonSerializable
{
    const DEF_DEPTH = 512;

    private $options = [
        '@attributes' => true,
        '@text' => true,
        'depth' => self::DEF_DEPTH
    ];

    /**
     * @var SimpleXMLElement
     */
    private $subject;

    public function __construct(SimpleXMLElement $element, $useAttributes = true, $useText = true, $depth = self::DEF_DEPTH)
    {

        $this->subject = $element;

        if (!is_null($useAttributes)) {
            $this->useAttributes($useAttributes);
        }

        if (!is_null($useText)) {
            $this->useText($useText);
        }

        if (!is_null($depth)) {
            $this->setDepth($depth);
        }
    }

    public function useAttributes($bool)
    {
        $this->options['@attributes'] = (bool)$bool;
    }

    public function useText($text)
    {
        $this->options['@text'] = (bool)$text;
    }

    public function setDepth($depth)
    {
        $this->options['depth'] = (int)max(0, $depth);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $subject = $this->subject;

        $array = array();

        // json encode attributes if any.
        if ($this->options['@attributes']) {
            if ($attributes = $subject->attributes()) {
                $array['@attributes'] = array_map('strval', iterator_to_array($attributes));
            }
        }

        // traverse into children if applicable
        $children = $subject;
        $this->options = (array)$this->options;
        $depth = $this->options['depth'] - 1;
        if ($depth <= 0) {
            $children = [];
        }

        // json encode child elements if any. group on duplicate names as an array.
        foreach ($children as $name => $element) {
            /* @var SimpleXMLElement $element */
            $decorator = new self($element);
            $decorator->options = ['depth' => $depth] + $this->options;

            if (isset($array[$name])) {
                if (!is_array($array[$name])) {
                    $array[$name] = [$array[$name]];
                }
                $array[$name][] = $decorator;
            } else {
                $array[$name] = $decorator;
            }
        }

        // json encode non-whitespace element simplexml text values.
        $text = trim($subject);
        if (strlen($text)) {
            if ($array) {
                $this->options['@text'] && $array['@text'] = $text;
            } else {
                $array = $text;
            }
        }

        // return empty elements as NULL (self-closing or empty tags)
        if ($array === null || $array === "") {
            $array = NULL;
        }

        return $array;
    }
}