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
class JsonXML implements \ArrayAccess, \Iterator
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
     * @throws JsonXMLException
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
     * @throws JsonXMLException
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
     * @throws JsonXMLException
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
        $rootElementName = $xml->getName();
        $this->unstealthNamespace($rootElementName);
        $this->xmlBuildAttributes->rootElementName = $rootElementName;

        // Pre-coded namespaces may actually not include all namespaces sent from remote XML server, thus merge those
        $this->xmlBuildAttributes->namespaces = array_merge(
            $xml->getDocNamespaces(true, true),
            $namespaces
        );

        // WTF ???
        //$jsonString = json_encode(simplexml_load_string(utf8_encode($xmlString), 'SimpleXMLElement', LIBXML_SCHEMA_CREATE), JSON_UNESCAPED_UNICODE);
        // This just gives nothing ?!

        $xml = new SimpleXMLNestedAttributesSerializable($xml);

        // Encode the SimpleXMLElement to the JSON string (all attributes are stored as "@ATTRIBUTE_NAME")
        $jsonString = json_encode($xml, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);

        // Propagate that string to parent JsonXml initiator :)
        $this->initFromJsonString($jsonString);

        // Unstealth all namespaces because it is not needed anymore
        $this->representation = $this->unStealthAllNamespaces($this->representation, $namespaces);
    }

    /**
     * Initializer of an existing JsonXML instance using the input JSON string.
     *
     * @param string $jsonString
     * @throws JsonXMLException
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
     * @throws JsonXMLException
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
     * Creates key in provided referenced array & returns the value assigned to it.
     *
     * If key is something like 'key_name[1001]', then the value will be put into a new array at index 1001 of
     * the newly assigned key in provided referenced array.
     *
     * @param $array
     * @param $key
     * @param $value
     * @return mixed
     */
    protected function &createArrayKeyedValue(&$array, $key, $value)
    {
        preg_match('/^(.*)\[(\d+)\]$/', $key, $matches);

        if (sizeof($matches) == 3) {
            $key = $matches[1];
            $index = $matches[2];
            $array[$key] = array();
            $array[$key][$index] = $value;
            return $array[$key][$index];
        } else {
            $array[$key] = $value;
            return $array[$key];
        }
    }

    /**
     * Tries to unset a data value at given path.
     *
     * Note that by using unset on an array element the index will be taken by it's succeeder if any exists.
     *
     * Simply spoken: By removing index 1 out of [0,1,2] you will get array with indices [0,1]
     *
     * @param array ...$absolutePathParts
     * @return bool
     */
    public function unsetDataValue(...$absolutePathParts)
    {
        $rootElement = array_shift($absolutePathParts);

        if (sizeof($absolutePathParts) === 0) {

            if (!isset($this->representation[$rootElement]))
                return false;

            unset($this->representation[$rootElement]);
            return true;
        }

        $unsetCandidateParent = &$this->representation[$rootElement];

        while (sizeof($absolutePathParts) > 1) {
            $unsetCandidateParent = &$unsetCandidateParent[array_shift($absolutePathParts)];
        }

        $unsetCandidate = array_shift($absolutePathParts);

        // We have to support to unset array key
        preg_match('/^(.*)\[(\d+)\]$/', $unsetCandidate, $matches);

        if (sizeof($matches) == 3) {
            $key = $matches[1];
            $index = $matches[2];

            if (!isset($unsetCandidateParent[$key][$index]))
                return false;

            unset($unsetCandidateParent[$key][$index]);

            // Reset array indices ..
            $unsetCandidateParent[$key] = array_values($unsetCandidateParent[$key]);
            return true;
        } else {
            if (!isset($unsetCandidateParent[$unsetCandidate]))
                return false;

            unset($unsetCandidateParent[$unsetCandidate]);
            return true;
        }


    }

    /**
     * Sets data value at specified absolute data path.
     *
     * You may omit namespace names. But if there is more elements with the same name, but different namespaces,
     * only the first one will be set. Thus it is advised to use full element names.
     *
     * Paths may not contain OR operator '|'.
     *
     * @param $dataValue
     * @param array ...$absolutePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function setDataValue($dataValue, ...$absolutePathParts)
    {
        if (sizeof($absolutePathParts) === 0)
            throw new JsonXMLException('You must specify the path');

        $foundRoot = FALSE;
        foreach(array_keys($this->representation) as $rootElement) {
            $colonIndex = strpos($rootElement, ':');

            if ($colonIndex === FALSE)
                continue;

            if (
                substr($rootElement, 0, $colonIndex) === substr($absolutePathParts[0], 0, $colonIndex)
                && $rootElement === $absolutePathParts[0]
            )
                $foundRoot = TRUE;
        }

        // Remove the namespace if not operating in any ..
        if (!$foundRoot) {
            $newAbsolutePathParts = array();
            foreach ($absolutePathParts as $absolutePathPart) {
                $colonIndex = strpos($absolutePathPart, ':');
                $newAbsolutePathParts[] = substr($absolutePathPart, ++$colonIndex);
            }
            $absolutePathParts = $newAbsolutePathParts;
        }

        // Setting data value to null means we want to unset that path
        if ($dataValue === null) {
            return $this->unsetDataValue(...$absolutePathParts);
        }

        $rootElement = array_shift($absolutePathParts);
        $childTree = array();

        $treeDepth = sizeof($absolutePathParts);

        $hisChild = $childTree;
        $i = 0;
        do {

            if ($i + 1 === $treeDepth)
                $value = $dataValue;
            else
                $value = array();

            if ($i == 0)
                $hisChild = &$this->createArrayKeyedValue($childTree, $absolutePathParts[$i], $value);
            else
                $hisChild = &$this->createArrayKeyedValue($hisChild, $absolutePathParts[$i], $value);

            ++$i;
        } while ($i < $treeDepth);

        // TODO: there should be custom function for cases when creating array at a key, where was value only previously
        // ... in those cases it is possible, that the value will get overwritten (in case of string)
        // or will get merged in a weird way (in case of an simple child object [array indexed not numerically])
        $this->representation[$rootElement] = array_replace_recursive($this->representation[$rootElement], $childTree);
    }

    /**
     * Returns data value at specified absolute data path.
     *
     * Paths may contain OR operator '|', in which case only the first match will be considered.
     *
     * No lookup after another match will take place if full path returns null inside first matched OR statement.
     *
     * @param array ...$absolutePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function get(...$absolutePathParts)
    {
        // Even if it isn't text, there will be returned whole array ..
        return $this->getRelative($this->representation, ...$absolutePathParts);
    }

    /**
     * Returns data value at specified relative data path to the provided rootElement.
     *
     * You may omit namespace names. But if there is more elements with the same name, but different namespaces,
     * only the first one will be returned. Thus it is advised to use full element names.
     *
     * Paths may contain OR operator '|', in which case only the first match will be considered.
     *
     * No lookup after another match will take place if full path returns null inside first matched OR statement.
     *
     * @param array $rootElement
     * @param array ...$relativePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    protected function getRelativeObject($rootElement, ...$relativePathParts)
    {

        if (!is_array($rootElement))
            throw new JsonXMLException('RootElement cannot be a value, it has to be array.');

        // Relative path part may also be provided as a string with '/' element old-style delimiter
        if (sizeof($relativePathParts) === 1)
            $relativePathParts = explode('/', $relativePathParts[0]);

        $currentChildren = $rootElement;

        foreach ($relativePathParts as $relativePathPart) {

            // First we have to detect, whether is current element an XML array or not
            $currentChildrenKeys = array_keys($currentChildren);

            $currentChildrenIsArray = empty(array_filter(
                $currentChildrenKeys,
                function ($key) {
                    return !is_int($key);
                }
            ));

            // If it isn't, than make it, so that we can have only one code path
            if (!$currentChildrenIsArray) {
                $currentChildren = array($currentChildren);
            }

            $foundOneOfOrStatement = false;

            foreach ($currentChildren as $currentChildrenElement) {
                // We can have logical OR operator inside name
                foreach (explode('|', $relativePathPart) as $relativePathOr) {
                    if (
                        $this->hasAnyKnownNamespace($relativePathOr, $namespacedRelativePath, $currentChildrenElement)
                        || (
                            $this->existsExactElementName($relativePathOr, $currentChildrenElement)
                            && ($namespacedRelativePath = $relativePathOr)
                        )
                    ) {

                        $currentChildren = $currentChildrenElement[$namespacedRelativePath];

                        // This should never happen, but one never knows ..
                        if ($currentChildren === null)
                            return null;

                        $foundOneOfOrStatement = true;
                        break 2;
                    }
                }

            }

            if (!$foundOneOfOrStatement)
                return null;
        }

        $currentChildrenIsArray = is_array($currentChildren);

        // At XML there is nothing like empty array
        // Empty array as a value has only flag elements
        if ($currentChildrenIsArray && sizeof($currentChildren) === 0)
            $currentChildren = true;

        return $currentChildren;
    }

    /**
     * @param $metadata
     * @param $mustExist
     * @param $rootElement
     * @param array ...$relativePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    protected function getRelativeMetadata($metadata, $mustExist, $rootElement, ...$relativePathParts)
    {
        // Root element must be an array, not a value!
        if (!is_array($rootElement))
            return null;

        $element = $this->getRelativeObject($rootElement, ...$relativePathParts);

        if (is_array($element)) {

            if (isset($element[$metadata]))
                return $element[$metadata];

            elseif ($mustExist)
                return null;
        }

        return $element;
    }

    /**
     * @param $rootElement
     * @param array ...$relativePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function getRelative($rootElement, ...$relativePathParts)
    {
        // Get text if possible (doesn't have to exist ..)
        return $this->getRelativeMetadata('@text', false, $rootElement, ...$relativePathParts);
    }

    /**
     * @param $rootElement
     * @param array ...$relativePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function getRelativeAttributes($rootElement, ...$relativePathParts)
    {
        // Get attributes or return nothing (even though there would be children - we don't want those ..)
        return $this->getRelativeMetadata('@attributes', true, $rootElement, ...$relativePathParts);
    }

    /**
     * Same as getDataValue, but returns empty array if not found anything.
     *
     * Paths may contain OR operator '|', in which case only the first match will be considered.
     *
     * No lookup after another match will take place if full path returns null inside first matched OR statement.
     *
     * @param array ...$absolutePaths
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function getArray(...$absolutePaths)
    {
        return $this->getArrayRelative($this->representation, ...$absolutePaths);
    }

    /**
     * Same as getDataValueFromRelativePath, but returns empty array if not found anything.
     *
     * Paths may contain OR operator '|', in which case only the first match will be considered.
     *
     * No lookup after another match will take place if full path returns null inside first matched OR statement.
     *
     * @param JsonXML | array | null $rootElement
     * @param array ...$relativePathParts
     * @return array|mixed|null
     * @throws JsonXMLException
     */
    public function getArrayRelative($rootElement, ...$relativePathParts)
    {
        $data = $this->getRelative($rootElement, ...$relativePathParts);

        if ($data === null)
            return array();

        // If it's value & we're expecting array, let's make it an array ..
        if (!is_array($data))
            return array($data);

        reset($data);
        // Or it already may be an associative array, but that's considered an element in JsonXML, so cast to an array
        if (! is_numeric(key($data)))
            return array($data);

        return $data;
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
            $namespace = $this->getNamespace($root_candidate);

            if (false === strpos($root_candidate, '/>') && 0 !== strpos(trim($root_candidate), '<')) {
                $root_candidate = "<?xml version=\"1.0\" encoding=\"UTF-8\"?> <$root_candidate/>";
            }

            // Lots of errors occur here, but that's due to really really weird SimpleXMLElement implementation,
            // so let's just ignore those .. this code is working like a charm, so don't let user think it's not ;)
            $previous_error_handler = set_error_handler(function() {return true;});

            $root = new SimpleXMLElement(
                $root_candidate,
                0,
                XMLBuildAttributes::DEFAULT_DATA_IS_URL,
                $namespace,
                false
            );

            // Restore previous error handler if any ..
            set_error_handler($previous_error_handler);

            foreach ($this->xmlBuildAttributes->namespaces as $prefix => $namespace) {
                $root->registerXPathNamespace($prefix, $namespace);
            }
        }

        if (is_array($potential_elements)) {
            foreach ($potential_elements as $element_name => $element_value) {

                // special: attributes
                if (is_string($element_name) && $element_name === '@attributes') {

                    $attributes = $element_value;
                    foreach ($attributes as $attributeName => $attributeValue) {

                        $namespace = $this->getNamespace($attributeName);
                        $root->addAttribute($attributeName, $attributeValue, $namespace);
                    }

                } else if (is_string($element_name) && $element_name === '@text') {

                    $text = $element_value;
                    $root[0] = $text;

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

                    $namespace = $this->getNamespace($element_name);

                    // Not attribute or numeric .. standard element to be appended
                    // This appendix should ensure that the above JsonXMLException is never thrown ;)
                    $this->simple_xmlify($element_value, $root_candidate, $root->addChild($element_name, null, $namespace), $root);
                }
            }
        } else if (is_string($potential_elements) && $potential_elements !== '') {
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
                "/([<\s]\/?${prefix})" . XMLBuildAttributes::NAMESPACE_DELIMITER . "([^=>\s]+)/",
                '$1' . XMLBuildAttributes::NAMESPACE_TMP_DELIMITER . '$2',
                $xmlString
            );
        }
    }

    /**
     * Unstealths all namespaces within a JSON representation and returns the result.
     *
     * @param array $jsonArray
     * @param array $namespaces
     * @return array
     */
    protected function unStealthAllNamespaces(array $jsonArray, array $namespaces)
    {
        $newRepresentation = array();
        foreach ($jsonArray as $key => $item) {

            $this->unstealthNamespace($key);

            if (is_array($item)) {
                $item = $this->unStealthAllNamespaces($item, $namespaces);
            }

            $newRepresentation[$key] = $item;
        }
        return $newRepresentation;
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

            if (isset($this->xmlBuildAttributes->namespaces[$namespacePrefix]))
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
     * Obtains namespace value from element or attribute name.
     *
     * @param string $elementOrAttributeName
     * @return mixed
     */
    protected function getNamespace(string $elementOrAttributeName)
    {
        $delimiter = XMLBuildAttributes::NAMESPACE_DELIMITER;
        if (strpos($elementOrAttributeName, XMLBuildAttributes::NAMESPACE_TMP_DELIMITER) !== false) {
            $delimiter = XMLBuildAttributes::NAMESPACE_TMP_DELIMITER;
        }

        list($namespacePrefix) = explode($delimiter, $elementOrAttributeName, 2);
        return $this->xmlBuildAttributes->namespaces[$namespacePrefix];
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
        return $this->hasAnyKnownNamespace($elementName);
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
        $foundIt = $this->hasAnyKnownNamespace($elementName, $fullElementName);

        return $foundIt ? $this->representation[$fullElementName] : null;
    }

    /**
     * Checks whether provided elementName exists.
     *
     * @param $elementName
     * @param JsonXML | array | null $rootElement
     * @return bool
     */
    protected function existsExactElementName($elementName, $rootElement = null)
    {
        if ($rootElement === null)
            $rootElement = $this->representation;

        return isset($rootElement[$elementName]);
    }

    /**
     * Returns true if given elementName exists in any known namespace, else false.
     *
     * If it exists, then the elementName, including it's namespace, is written to the fullElementName variable.
     *
     * @param $elementName
     * @param string $fullElementName
     * @param JsonXML | array | null $rootElement
     * @return bool
     */
    protected function hasAnyKnownNamespace($elementName, string &$fullElementName = null, $rootElement = null)
    {
        if (!is_string($elementName) || $elementName === '')
            return false;

        if ($rootElement === null)
            $rootElement = $this->representation;

        if (count($this->xmlBuildAttributes->namespaces) === 0) {

            if ($this->existsExactElementName($elementName, $rootElement)) {

                // This is the real key for desired element
                $fullElementName = $elementName;
                return true;
            }

            // No namespaces defined, so we don't even try to find one
            return false;
        }

        // Delimiter can't start the name, thus that offset
        if (strpos($elementName, XMLBuildAttributes::NAMESPACE_DELIMITER, 1) !== false) {

            if ($this->existsExactElementName($elementName, $rootElement)) {

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
            $currentElementName = $prefix . XMLBuildAttributes::NAMESPACE_DELIMITER . $elementName;

            if ($this->existsExactElementName($currentElementName, $rootElement)) {

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

            if ($this->hasAnyKnownNamespace($elementName, $fullElementName)) {
                $elementName = $fullElementName;
            } // Delimiter can't start the name, thus that offset
            else if (count($this->xmlBuildAttributes->namespaces) > 0) {
                // No namespace provided, so pick the first one available ..

                reset($this->xmlBuildAttributes->namespaces);
                $firstNsPrefix = key($this->xmlBuildAttributes->namespaces);

                $elementName = $firstNsPrefix . XMLBuildAttributes::NAMESPACE_DELIMITER . $elementName;
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
        if ($this->hasAnyKnownNamespace($elementName, $fullElementName)) {
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

    protected $iterator_index = 0;

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        $keys = array_keys($this->representation);
        return $this->representation[$keys[$this->iterator_index]];
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        ++$this->iterator_index;
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        $keys = array_keys($this->representation);
        return $keys[$this->iterator_index];
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        if ($this->iterator_index >= sizeof($this->representation))
            return false;
        return true;
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->iterator_index = 0;
    }
}