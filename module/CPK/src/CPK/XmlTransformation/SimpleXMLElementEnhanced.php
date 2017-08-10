<?php
/**
 * Created by PhpStorm.
 * User: kozlovsky
 * Date: 5.5.17
 * Time: 19:49
 */

namespace CPK\XmlTransformation;


/**
 * Class SimpleXMLElementEnhanced
 *
 * This class extends the very limiting options of manipulating XML using built-in SimpleXMLElement.
 *
 * It should enhance XML manipulation & make programmer's life much much easier :)
 *
 * @package CPK\XmlTransformation
 */
class SimpleXMLElementEnhanced extends \SimpleXMLElement
{

    /**
     * @param string $path
     * @return SimpleXMLElementEnhanced[]
     */
    public function xpath($path)
    {
        return parent::xpath($path);
    }

    /**
     * Get me only those elements, which are having all the attributes specified.
     *
     * Example:
     * $admins = $xmlEnhanced->get("employee", "roles", "admin")
     *
     * $admins variable now contains an array of objects, where each of them certainly contains whole subtree specified
     * in arguments list
     *
     * For better example, suppose XML below was parsed into $xmlEnhanced:
     * <employees>
     *  <employee>
     *   <name>Peter</name>
     *   <roles>
     *    <admin/>
     *    <dev-ops/>
     *   </roles>
     *  </employee>
     *  <employee>
     *   <name>Patrick</name>
     *   <roles>
     *    <developer/>
     *   </roles>
     *  </employee>
     * </employees>
     *
     * And now we will print all admins names:
     * foreach($admins as $admin) {
     *   echo $admin->name;
     * }
     *
     * Changing attributes will always edit also the root SimpleXMLElementEnhanced object.
     * $admins[0]->name = "Fake name"
     * echo $xmlEnhanced->employee[0]->name
     *  -> outputs "Fake name"
     *
     * @param \string[] $tags
     * @return SimpleXMLElementEnhanced[]
     */
    public function all(...$tags)
    {
        $thisInstance = $this;

        while (!empty($tags)) {

            $tag = array_shift($tags);
            $isLastTag = count($tags) === 0;

            $path = "*[local-name()='$tag']";

            $matches = $thisInstance->xpath($path);

            if ($isLastTag)
                return $matches;

            $matchesCount = count($matches);

            if ($matchesCount > 1) {
                for ($j = 0; $j < $matchesCount; ++$j) {
                    $match = $matches[$j];

                    $match = $match->all(...$tags);

                    if (count($match) === 0)
                        unset($matches[$j]);
                }

                // Now just reset the indices
                $matches = array_values($matches);

                return $matches;

            } elseif ($matchesCount === 1) {
                $thisInstance = $matches[0];
            } else
                return $matches;
        }
    }

    /**
     * @param array $tags
     * @return SimpleXMLElementEnhanced
     */
    public function first(...$tags)
    {
        $matches = $this->all(...$tags);

        if (count($matches) > 0)
            return $matches[0];

        return null;
    }

    /**
     * @param $name
     * @param $value
     * @param int $index
     * @return SimpleXMLElementEnhanced
     */
    public function prependChild($name, $value = null, $index = 0)
    {
        $dom = dom_import_simplexml($this);

        $target = $dom->firstChild;

        # Move right
        for ($i = 0; $i < $index; ++$i) {
            if ($target->nextSibling != null)
                $target = $target->nextSibling;
        }

        $names = explode('/', $name);
        $namesCount = count($names);

        $namespaces = $this->getNamespaces();

        if (count($namespaces) > 0) {
            reset($namespaces);
            $firstNamespace = key($namespaces);

            foreach ($names as &$name)
                // Use the first namespace ..
                $name = $firstNamespace . ':' . $name;
        }

        $new = $dom->insertBefore(
            $dom->ownerDocument->createElement(
                $names[0],
                $namesCount == 1 ? $value : null
            ),
            $target
        );

        $last = $new;
        for ($i = 1; $i < $namesCount; ++$i) {
            $last = $last->appendChild(
                $dom->ownerDocument->createElement(
                    $names[$i],
                    $i == $namesCount - 1 ? $value : null
                )
            );
        }

        $simpleXml = simplexml_import_dom($new, get_class($this));

        // Just for code completion in PHPStorm ..
        if (!$simpleXml instanceof SimpleXMLElementEnhanced)
            return null;

        // Register namespaces again
        foreach ($namespaces as $key => $value)
            $simpleXml->registerXPathNamespace($key, $value);

        return $simpleXml;
    }
}