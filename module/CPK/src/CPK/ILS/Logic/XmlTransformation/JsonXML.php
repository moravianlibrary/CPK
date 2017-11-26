<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 26.11.17
 * Time: 2:05
 */

namespace CPK\ILS\Logic\XmlTransformation;

use SimpleXMLElement;

/**
 * Class JsonXMLException
 * @package CPK\ILS\Logic\XmlTransformation
 */
class JsonXMLException extends \Exception
{
}


class XMLBuildAttributes
{
    const DEFAULT_OPTIONS = LIBXML_SCHEMA_CREATE;
    const DEFAULT_DATA_IS_URL = false; // Dunno what is this good for, but this is current SimpleXMLElement c'tor default
    const DEFAULT_PREFIX = '';
    const DEFAULT_ROOT_NAME = 'root';

    /**
     * Just classical XML namespace delimiter for element names.
     */
    const NAMESPACE_DELIMITER = ':';

    /**
     * Temporary namespace delimiter to be able of encoding the SimpleXMLElement while still being able to
     * distinguish between particular namespaces.
     *
     * Based on XML Naming Rules:
     *  Element names can contain letters, digits, hyphens, underscores, and periods
     */
    const NAMESPACE_TMP_DELIMITER = '-_._-';

    /**
     * First prefix used to decode the xmlString & then to encode from JSON array data
     *
     * @var string
     */
    public $prefix = self::DEFAULT_PREFIX;

    /**
     * Name for root XML element. It should get automatically filled when initializing the instance from xmlString.
     *
     * @var string
     */
    public $rootElementName = self::DEFAULT_ROOT_NAME;

    /**
     * XML document namespaces parsed when initializing the instance from xmlString.
     *
     * @var array
     */
    public $namespaces = [];
}

/**
 * Class JsonXML
 *
 * Array-like class made for much easier XML manipulation with both ways <strong>JSON</strong> <-> <strong>XML</strong>
 * conversion compatibility.
 *
 * Advantages:
 * <ul>
 *  <li>attribute safe manipulation</li>
 *  <li>keeps track of all namespaces used at XML import</li>
 *  <li>also remembers root element properties</li>
 * </ul>
 *
 * @package CPK\ILS\Logic\XmlTransformation
 */
class JsonXML implements \ArrayAccess
{
    /**
     * Attribute to hold the already parsed SimpleXMLElement so that it can be used if there was no change made
     *  to the JsonXML without the need to reconvert the inner XML representation to SimpleXMLElement.
     *
     * @var SimpleXMLElement
     */
    protected $lastXml = null;

    /**
     * Helper boolean for the lastXml attribute.
     *
     * @var bool
     */
    protected $changedSinceLastXml = true;

    /**
     * Classical JSON representation as an associative array, including XML attributes.
     *
     * @var array
     */
    protected $representation = array();

    /**
     * Storage for XML attributes needed to rebuild XML with identical metadata.
     *
     * @var XMLBuildAttributes
     */
    protected $xmlBuildAttributes = null;

    /**
     * Generic JsonXML constructor.
     */
    public function __construct()
    {
        $this->xmlBuildAttributes = new XMLBuildAttributes();
    }

    /**
     * Constructor from the XML string.
     *
     * @param string $xmlString
     * @param array $namespaces
     * @return JsonXML
     */
    public static function fabricateFromXmlString(string $xmlString, array $namespaces = array())
    {
        $newInstance = new JsonXML();
        $newInstance->initFromXmlString($xmlString, $namespaces);
        return $newInstance;
    }

    /**
     * Constructor from the JSON string
     *
     * @param string $jsonString
     * @return JsonXML
     */
    public static function fabricateFromJsonString(string $jsonString)
    {
        $newInstance = new JsonXML();
        $newInstance->initFromJsonString($jsonString);
        return $newInstance;
    }

    /**
     * Initializer of an existing JsonXML instance from the input XML string.
     *
     * @param string $xmlString
     * @param array $namespaces
     */
    public function initFromXmlString(string $xmlString, array $namespaces = array())
    {
        $this->stealthAllNamespaces($xmlString, $namespaces);

        // Note: Should also hack resolving attributes belonging to element with no child, but value.
        // Element with both value & attribute will be stored only with it's value, leaving the attribute
        // This is also a bug of json_encode(SimpleXMLElement):
        // https://stackoverflow.com/a/25681763/3075836

        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', XMLBuildAttributes::DEFAULT_OPTIONS);

        // Now store metadata which gets lost when we convert the SimpleXML to the array
        $this->xmlBuildAttributes->rootElementName = $xml->getName();

        // Pre-coded namespaces may actually not include all namespaces sent from remote XML server, thus merge those
        $this->xmlBuildAttributes->namespaces = array_merge(
            $xml->getDocNamespaces(true, true),
            $namespaces
        );

        // Encode the SimpleXMLElement to the JSON string (all attributes are stored as "@ATTRIBUTE_NAME")
        $jsonString = json_encode($xml, JSON_UNESCAPED_UNICODE);

        // Propagate that string to parent JsonXml initiator :)
        $this->initFromJsonString($jsonString);
    }

    /**
     * Initializer of an existing JsonXML instance using the input JSON string.
     *
     * @param string $jsonString
     */
    public function initFromJsonString(string $jsonString)
    {
        // Store the JSON string as a key-valued associative array (this is actually the most productive format)
        $this->representation = json_decode($jsonString, TRUE);

        // Changed inner representation ...
        $this->changedSinceLastXml = true;

        $this->assertInitialized("JsonXML wasn't initialized properly (have nothing to parse from the input, please check you're passing the right namespaces).");
    }

    /**
     * Returns new SimpleXMLElement built from the inner XML representation.
     *
     * @return SimpleXMLElement
     */
    public function toSimpleXml()
    {
        // It is desired to be returned something already parsed once, thus there is no need to convert to XML again
        if ($this->lastXml !== null && $this->changedSinceLastXml === false) {
            return $this->lastXml;
        }

        $this->assertInitialized("JsonXML has nothing to convert into SimpleXMLElement");

        // We have out-of-date lastXml attribute, so actualize it & return it :)
        $this->lastXml = $this->simple_xmlify($this->representation, $this->xmlBuildAttributes->rootElementName);

        // Setting to { XML <-> inner representation } not modified state
        $this->changedSinceLastXml = false;

        return $this->lastXml;
    }

    /**
     * Returns XML string representation of the XML.
     *
     * @return string
     * @throws JsonXMLException
     */
    public function toXmlString()
    {
        $xmlString = $this->toSimpleXml()->asXML();

        if ($xmlString === false) {
            throw new JsonXMLException("Unknown error occurred while converting XML to string");
        }

        return $xmlString;
    }

    /**
     * Returns JSON representation of the XML as an array.
     *
     * @return array
     */
    public function toJsonObject()
    {
        // The inner representation is actually JSON ...
        return $this->representation;
    }

    /**
     * Returns JSON string representation of the XML.
     *
     * @return string
     * @throws JsonXMLException
     */
    public function toJsonString()
    {
        $jsonString = json_encode($this->representation);

        if ($jsonString === false) {
            throw new JsonXMLException("Unknown error occurred while converting array to JSON string");
        }

        return $jsonString;
    }

    /**
     *  Actual JSON array -> SimpleXMLElement converter.
     *
     *  Inspired by <a href="https://stackoverflow.com/a/24763517/3075836">this Stack Overflow post</a>.
     *
     * @param $potential_elements
     * @param string $root_candidate
     * @param SimpleXMLElement|null $root
     * @param null $parent
     * @return SimpleXMLElement
     * @throws JsonXMLException
     */
    protected function simple_xmlify($potential_elements, $root_candidate = 'root', SimpleXMLElement $root = null, $parent = null)
    {
        // Create root element, if not provided
        if (!isset($root) || null == $root) {

            // First of all, get back the namespace
            $this->unstealthNamespace($root_candidate, $namespacePrefix, $namespace);

            if (false === strpos($root_candidate, '/>') && 0 !== strpos(trim($root_candidate), '<')) {
                $root_candidate = "<?xml version=\"1.0\" encoding=\"UTF-8\"?> <$root_candidate/>";
            }

            $root = new SimpleXMLElement(
                $root_candidate,
                0,
                XMLBuildAttributes::DEFAULT_DATA_IS_URL,
                $namespace,
                false
            );

            foreach ($this->xmlBuildAttributes->namespaces as $prefix => $namespace) {
                $root->addAttribute('xmlns:' . $prefix, $namespace);
            }
        }

        if (is_array($potential_elements)) {
            foreach ($potential_elements as $element_name => $element_value) {

                // special: attributes
                if (is_string($element_name) && $element_name === '@attributes') {

                    $attributes = $element_value;
                    foreach ($attributes as $attributeName => $attributeValue) {

                        $this->unstealthNamespace($attributeName, $namespacePrefix, $namespace);
                        $root->addAttribute($attributeName, $attributeValue, $namespace);
                    }

                } // special: a numerical index only should mean repeating nodes
                else if (is_numeric($element_name)) {
                    if ($element_name == 0) {

                        // first time, just add it to the existing element
                        $this->simple_xmlify($element_value, $root_candidate, $root, $parent);

                    } else if ($parent !== null) {

                        $this->simple_xmlify($element_value, $root_candidate, $parent->addChild($root->getName()), $parent);

                    } else {

                        throw new JsonXMLException('$parent argument got null in recursion ?! WTF ?!');
                    }
                } else {

                    $this->unstealthNamespace($element_name, $namespacePrefix, $namespace);

                    // Not attribute or numeric .. standard element to be appended
                    // This appendix should ensure that the above JsonXMLException is never thrown ;)
                    $this->simple_xmlify($element_value, $root_candidate, $root->addChild($element_name, null, $namespace), $root);
                }
            }
        } else if (!empty($potential_elements)) {
            // it's an element value, not an array :)
            $element_value = $potential_elements;
            $root[0] = $element_value;
        }

        return $root;
    }

    /**
     * Stealth mode for all the real namespaces for all the element & attribute names.
     *
     * This is really needed if we want to convert XML to JSON without losing all the elements & attributes with
     * differentiating namespace. The tricky function simplexml_load_string() would otherwise get rid of all other
     * than one chosen namespace ...
     *
     * @param string $xmlString
     * @param array $namespaces
     */
    protected function stealthAllNamespaces(string &$xmlString, array $namespaces)
    {
        // Replace all namespace delimiters with custom delimiter to force json_encode(SimpleXMLElement)
        // produce all elements, no matter what namespace it is .. UGLY HACK, but it works ;)
        foreach ($namespaces as $prefix => $ns) {
            $xmlString = preg_replace(
                "/([<\s]${prefix})" . XMLBuildAttributes::NAMESPACE_DELIMITER . "([^=>]+[=>])/",
                '$1' . XMLBuildAttributes::NAMESPACE_TMP_DELIMITER . '$2',
                $xmlString
            );
        }
    }

    /**
     * Brings back the real namespace to life again for SimpleXMLElement attribute or element name.
     *
     * @param string $elementOrAttributeName
     * @param string $namespacePrefix
     * @param string $namespace
     */
    protected function unstealthNamespace(string &$elementOrAttributeName, string &$namespacePrefix = null, string &$namespace = null)
    {
        // Return that element or attribute it's original namespace ;)
        if (strpos($elementOrAttributeName, XMLBuildAttributes::NAMESPACE_TMP_DELIMITER) !== false) {

            // Extract the namespace prefix first
            list($namespacePrefix) = explode(XMLBuildAttributes::NAMESPACE_TMP_DELIMITER, $elementOrAttributeName, 2);

            // Get the namespace value ..
            $namespace = $this->xmlBuildAttributes->namespaces[$namespacePrefix];

            // Then unstealth the name ;)
            $elementOrAttributeName = str_replace(
                XMLBuildAttributes::NAMESPACE_TMP_DELIMITER,
                XMLBuildAttributes::NAMESPACE_DELIMITER,
                $elementOrAttributeName
            );
        } else
            $namespacePrefix = null;
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $elementName
     * @return bool true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * </p>
     * @since 5.0.0
     */
    public function offsetExists($elementName)
    {
        return $this->hasAnyNamespace($elementName);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $elementName <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($elementName)
    {
        $foundIt = $this->hasAnyNamespace($elementName, $fullElementName);

        return $foundIt ? $this->representation[$fullElementName] : null;
    }

    /**
     * Checks whether provided elementName exists.
     *
     * @param $elementName
     * @return bool
     */
    protected function existsExactElementName($elementName)
    {
        return isset($this->representation[$elementName]);
    }

    /**
     * Returns true if given elementName exists in any known namespace, else false.
     *
     * If it exists, then the elementName, including it's namespace, is written to the fullElementName variable.
     *
     * @param $elementName
     * @param string $fullElementName
     * @return bool
     */
    protected function hasAnyNamespace($elementName, string &$fullElementName = null)
    {
        if (count($this->xmlBuildAttributes->namespaces) === 0) {

            if ($this->existsExactElementName($elementName)) {

                // This is the real key for desired element
                $fullElementName = $elementName;
                return true;
            }

            // No namespaces defined, so we don't even try to find one
            return false;
        }

        // Delimiter can't start the name, thus that offset
        if (strpos($elementName, XMLBuildAttributes::NAMESPACE_DELIMITER, 1) !== false) {

            // Got element name with a namespace, things are so easier then =)
            $elementName = str_replace(XMLBuildAttributes::NAMESPACE_DELIMITER, XMLBuildAttributes::NAMESPACE_TMP_DELIMITER, $elementName);

            if ($this->existsExactElementName($elementName)) {

                // This is the real key for desired element
                $fullElementName = $elementName;
                return true;
            }

            //
            return false;
        }

        // No namespace specified, so try every single one we know about and return the first match :)

        $currentElementName = null;

        foreach ($this->xmlBuildAttributes->namespaces as $prefix => $ns) {

            // Build next tip
            $currentElementName = $prefix . XMLBuildAttributes::NAMESPACE_TMP_DELIMITER . $elementName;

            if ($this->existsExactElementName($currentElementName)) {

                // This is the one real key out of many possible keys for desired element
                // to obtain the others, use the absolute name (with the namespace)
                $fullElementName = $currentElementName;
                return true;
            }
        }

        return false;
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $elementName <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $elementValue <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($elementName, $elementValue)
    {
        if (is_null($elementName)) {
            // Just append the value ..
            $this->representation[] = $elementValue;
        } else {

            if ($this->hasAnyNamespace($elementName, $fullElementName)) {
                $elementName = $fullElementName;
            } // Delimiter can't start the name, thus that offset
            else if (strpos($elementName, XMLBuildAttributes::NAMESPACE_DELIMITER, 1) !== false) {

                // Got element name with a namespace, things are so easier then =)
                $elementName = str_replace(XMLBuildAttributes::NAMESPACE_DELIMITER, XMLBuildAttributes::NAMESPACE_TMP_DELIMITER, $elementName);

            } else if (count($this->xmlBuildAttributes->namespaces) > 0) {
                // No namespace provided, so pick the first one available ..

                reset($this->xmlBuildAttributes->namespaces);
                $firstNsPrefix = key($this->xmlBuildAttributes->namespaces);

                $elementName = $firstNsPrefix . XMLBuildAttributes::NAMESPACE_TMP_DELIMITER . $elementName;
            }

            // Set the value ;)
            $this->representation[$elementName] = $elementValue;
        }

        $this->changedSinceLastXml = true;
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $elementName <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($elementName)
    {
        if ($this->hasAnyNamespace($elementName, $fullElementName)) {
            unset($this->representation[$fullElementName]);
            $this->changedSinceLastXml = true;
        }
    }

    /**
     * Throw an exception if this instance was not initialized.
     *
     * @param string|null $errorMessage
     * @throws JsonXMLException
     */
    protected function assertInitialized(string $errorMessage = null)
    {
        if (count($this->representation) === 0) {
            throw new JsonXMLException(
                $errorMessage ?:
                    "JsonXML has no contents inside ! First please initialize it ..."
            );
        }
    }
}