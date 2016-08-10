<?php

require_once('XmlObjectProperty.class.php');
require_once('XmlObjectException.class.php');
require_once('DOMElementArray.class.php');

/**
 * Base class for XML-backed model objects.  This abstract class encapsulates the methods
 * necessary to translate the XML into model objects.
 * 
 * NOTE: This is not a Symfony/Propel model.  Instead it interfaces with
 * eXist for data access.  Therefore, it will not support the same interace
 * as a Symfony/Propel model object.
 */
abstract class XmlObject
{  
  /**
   * The DOM document backing this object.
   *
   * @var DOMDocument
   */
  protected $dom;

  /**
   * The DOM context for this particular object
   *
   * @var DOMNode
   */
  protected $domnode;
  
  /**
   * A map of references to either strings or objects.  This map is what is
   * used to back the object properties described in the subclass configuration.
   * 
   * keys = property name (ie. "title"), value = either a string representing the value
   * of the XML node, or an object constructed from the XML node.  This depends on the config
   * passed to the constructor.
   *
   * @var array
   */
  protected $map;
  
  /**
   * An hash representing all the namespaces for the document.
   * The key is the namespace name, the value is the namespace URI.
   * 
   * @var array
   */
  protected $namespaceList;

 /**
   * A DOM XPath for querying the dom to find the nodes to be mapped
   *
   * @var DOMXPath
   */
  protected $xpath;

  /**
   * An array of XmlObjectProperties used to configure mapping from xml
   *
   * @var array
   */
  protected $properties;

  /**
   * Schema location for validation
   * @var string
   */
  protected $schema;

  /**
   * If a field is configured but not present in the current xml, add it when setting values.
   * NOTE: only works for simple top-level elements or attributes (no filters, not series).
   * Disabled by default, can be enabled when extending this class.
   * @var boolean
   */
  protected $ADD_MISSING_FIELDS_ON_SET = false;
  
  /**
   * Takes a chunk of XML and creates an array of XmlObject objects from it.  The chunk
   * is assumed to be a series of same-typed XML elements.
   *
   * @param string $xml The XML to translate to an object array.
   * @param string $className The name of the XmlObject subclass to instantiate.
   * @return array An array of XmlObject objects.
   */
  public static function createXmlObjectArray($xml, $className) {
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    
    $subclass = new ReflectionClass($className);
    
    $xmlObjects = array();
    $root = $dom->documentElement;
    foreach ($root->childNodes as $child) {
      $childXml = $dom->saveXML($child);
      $xmlObjects[] = $subclass->newInstance($childXml);
    }
    
    return $xmlObjects;
  }
  
  
  
  /**
   * Protected constructor for use by subclasses.  See the config() method for an example
   * on how to pass the properties.
   * 
   * @param DOMNode or DOMDdocument $domnode - either a DOM object or
   * the context DOM node for an XmlObject that is part of a larger XmlObject
   * @param array $properties An array of XmlObjectProperty objects describing the configuration of the
   * properties.
   * @param DOMXPath $xpath xpath object for this DOM, to be shared within sub-classes (optional)
   * @see config()
   */
  protected function __construct($domnode, $properties, $xpath = null) {
    if ($domnode instanceof DOMDocument) {
      $this->dom = $domnode;
      $this->domnode = $domnode->documentElement;
    } elseif ($domnode instanceof DOMNode) {
      $this->dom = $domnode->ownerDocument;
      $this->domnode = $domnode;
    } else {
      // invalid input - throw an exception
      throw new XmlObjectException("Error in constructor: first argument must be either DOMDocument or DOMnode");
      return;

    }

    if (is_null($xpath)) {
      $this->xpath = new DOMXpath($this->dom);
    } else {
      $this->xpath = $xpath;
    }
    
    // Register any namespaces
    if(is_array($this->namespaceList)) {
      foreach($this->namespaceList as $name => $uri) {
        $this->xpath->registerNamespace($name, $uri);
      }
    }
    
    // Make sure that $properties is an array
    if(!($properties instanceof XmlObjectPropertyCollection)) {
      throw new XmlObjectException("\$properties must be an XmlObjectPropertyCollection");
    }

    $this->properties =  $properties;

    $this->update();

    // set all sub-objects to use same configuration for dynamically adding missing fields
    /*
    if ($domnode == $this->dom->documentElement) {
        print "setting missing fields on subobjects<br/>";
        foreach ($this->map as $name => $object) {
            if ($object instanceof XmlObject) {
                print "DEBUG: $name is XmlObject<br/>\n";
                $object->ADD_MISSING_FIELDS_ON_SET = $this->ADD_MISSING_FIELDS_ON_SET;
            } elseif (is_array($object)) {
                print "DEBUG: $name is array<br/>\n";
                foreach ($object as $obj) {
                    if ($obj instanceof XmlObject) {
                        print "DEBUG: obj is XmlObject<br/>\n";
                        $obj->ADD_MISSING_FIELDS_ON_SET = $this->ADD_MISSING_FIELDS_ON_SET;
                    }
                }
            }
        }
    }*/   
  }

  /*
   * create or update the in-memory map from the XML based on configuration properties
   */
  protected function update() {
    $this->map = array();
    foreach ($this->map as $name => $value) {
      unset($this->map{$name});
    }

    foreach ($this->properties as $prop) {
      // Make sure each property is the correct type
      if(!($prop instanceof XmlObjectProperty)) {
        throw new XmlObjectException("\$properties must be an XmlObjectPropertyCollection");
      }
      
      // Run the xpath for the property
      $nodeList = $this->xpath->query($prop->xpath, $this->domnode);

      // for a series of DOMElements, use DOMElement array (for read/write/append)
      if ($prop->is_series && is_null($prop->class_name)) {
	// if there is no class, must be an array of DOM elements
	$tmp = new DOMElementArray();
      } elseif (($prop->is_series) && !is_null($prop->array_class)) {
	$arrayclass = new ReflectionClass($prop->array_class);
	$tmp = $arrayclass->newInstance();
      } else {
	$tmp = array();
      }
      
      foreach($nodeList as $node) {
        // If it's a sub-class property, then we need to take the returned
        // XML and use it to instantiate an object
        if (!is_null($prop->class_name)) {
	        $class = new ReflectionClass($prop->class_name);

	        // Instantiate the class, with the context node
		// pass in shared xpath object
		$tmp[] = $class->newInstance($node, $this->xpath);

	      } else {
	  	$tmp[] = $node;
	      }
      }
      
      // If it's a series property, then we store all the values
      if($prop->is_series) {
	$this->map{$prop->name} = $tmp;
      }
      // If not a series property, we either take the first one, or if
      // there are none, we set the value to null.
      else {
        if(count($tmp) == 0) {
          $this->map{$prop->name} = null;
        }
        else {
          $this->map{$prop->name} = $tmp[0];
        }
      } 
    }
  }
  
  /**
   * Adds a namespace to the document.
   *
   * @param string $name
   * @param string $uri
   */
  protected function addNamespace($name, $uri) {
    $this->namespaceList[$name] = $uri;
  }

  /**
   * An error handling function for checking the validity of of an XML string by loading
   * it into the DOM and seeing if it errors out.
   *
   * @param unknown_type $errno
   * @param unknown_type $errstr
   */
  static function HandleXmlError($errno, $errstr) {
      if(substr_count($errstr, "DOMDocument::loadXML()") > 0) {
        throw new XmlObjectException();
      }
      else {
        return false;
      }
  }
  
  /**
   * A convenience method for setting the object
   * properties
   *
   * @param array $options A hash where the keys are property
   * names and the values are hashes w/ keys of the XmlObjectProperty
   * properties (ie. 'class_name', 'is_series') and the values
   * are the values to set those properties to.
   * 
   * For example, the following array would be for an object that has
   * a string title, a list of string subjects, and a list of 
   * Reference objects.
   * 
   * $x = array(
   *   "title" => array("xpath" => "/title"),
   *   "subjects" => array("xpath" => "/subject", "is_series" => true),
   *   "references" => array("xpath" => "/reference", "is_series" => true, "class_name" => "Reference)
   * );
   * 
   * @return XmlObjectPropertyCollection to pass to the XmlObject constructor.
   */
  protected function config($options) {
    $props = new XmlObjectPropertyCollection();
    
    foreach($options as $key => $prop_map) {
      $props[$key] = new XmlObjectProperty($key, $prop_map);
    }
    
    return $props;
  }
  
  /**
   * A magic method to handle retrieving the dynamic properties that are set in the config
   * passed to the constructor.
   * 
   *
   * @param string $name The name of the property to get.  This must be a property that was
   * declared as part of the config.
   * @return unknown Either a string (in the case of a simple property), an array of strings (in the
   * case of a series of simple properties), an object (in the case of a singular related object), or
   * an array of objects (in the case of a series of related objects). NOTE: returned by reference.
   */
  public function &__get($name) {
    /** NOTE: php magic functions changed to explicitly NOT return by
        reference; since this breaks the way the xmlobject class is
        designed to be used, this function has been updated to return
        *all* results by reference, even if it is a simple value,
        because return by reference is all or nothing.
    */

    $value = null;
    if (property_exists($this, $name)) {
      return $this->$name;
    } elseif(!isset($this->map{$name})) {
      // Output a notice if name is not mapped
      trigger_error("Undefined attribute: $name", E_USER_NOTICE);
      $value = null;
    } elseif($this->map{$name} instanceof ArrayObject) {	//any class derived from ArrayObject
      $value = $this->map{$name};
    } elseif(is_array($this->map{$name})) {
      $value = $this->translateArray($name);
    } else {
      $value = $this->getMapValue($this->map{$name});
    }
    return $value;
  }
  
  /**
   * Pulls an array from the map and creates a copy, using either strings, in the case
   * of a series of simple values, or an array of objects, in the case of a series of
   * related objects.
   *
   * @param string $name The key in the map for the array to translate.
   * @return array Either an array of strings, or an array of XmlObject objects.
   */
  private function translateArray($name) {
    $newArray = array();
    foreach($this->map{$name} as $x) {
      $newArray[] = $this->getMapValue($x);
    }
    
    return $newArray;
  }
  
  /**
   * Translates an object from the $map into its correct value for return.
   * This could be a string (for a simple value) or an object.
   * 
   * @param unknown_type $obj The object to translate.
   */
  private function getMapValue($obj) {
    switch(get_class($obj)) {
    case "DOMElement":
      return $obj->nodeValue;
    case "DOMAttr":
      return $obj->value;
    case "DOMText":	// NOTE: DOMText is read-only, so cannot be set
      return $obj->wholeText;
    default:
      return $obj;
    }
  }


  /**
   * A magic method to set the dynamic class properties
   *
   * @param string $name The name of the property to set
   * @param string $value The value which should be stored in the specified property
   *
   * Note that setting an element of an array actually calls get on
   * the array object and then uses the accessors on the array object,
   * not this function.
   */
  public function __set($name, $value) {
    if(!isset($this->map{$name})) {  
        // if configured to add missing fields on set,
        // and if node is not present and is a simple top-level element, add the node
        if($this->ADD_MISSING_FIELDS_ON_SET  && !preg_match("|[/\[@]|", $this->properties[$name]->xpath)) {          
          // create element based on configured xpath, update in-memory map, then let parent handle setting
          if (strpos($this->properties[$name]->xpath, ':')) {
              // if xpath contains a namespace, split and use configured namespace to create name-spaced el
              list($el_ns, $path) = (split(":", $this->properties[$name]->xpath));
	      if (isset($this->namespaceList[$el_ns])) $ns = $this->namespaceList[$el_ns];
	      elseif (isset($this->namespace)) $ns = $this->namespace;
	      else trigger_error("Could not determine namespace", E_USER_WARNING);
              $new_el = $this->dom->createElementNS($ns, $this->properties[$name]->xpath);
          } else {
            $new_el = $this->dom->createElement($this->properties[$name]->xpath);
          }
          $this->domnode->appendChild($new_el);
          $this->update();
        } elseif ($this->ADD_MISSING_FIELDS_ON_SET && strpos($this->properties[$name]->xpath, "@") === 0) {
          $attribute_name = substr($this->properties[$name]->xpath, 1, strlen($this->properties[$name]->xpath));
          $this->domnode->setAttribute($attribute_name, $value);
          $this->update();
        } else {
        // Output a notice if name is not mapped
        trigger_error("Undefined attribute: $name", E_USER_NOTICE);
        return null;
        }
    }
    // encode any characters that need to be escaped for valid xml
    //    $value = htmlentities($value);
    // FIXME: this will turn entities like &#x0033; into &amp;#x0033;
    //        need a better solution for cleaning up text
    $value = str_replace("& ", "&amp; ", $value);

    // FIXME: use this once we get to php 5.2.3
    //    $value = htmlentities($value, ENT_NOQUOTES, "UTF-8", false);
    // leave quotes unconverted; use utf-8 character set; and don't encode existing html entities
    return $this->setMapValue($this->map{$name}, $value);
  }

  /**
   * Set the value of the object from the $map according to what type it is
   */
  private function setMapValue($obj, $value) {
    switch(get_class($obj)) {
      case "DOMElement":
        return $obj->nodeValue = $value;
      case "DOMAttr":
        return $obj->value = $value;
      default:
        return $obj = $value;
    }
  }


  /**
   * Override the default isset() to correctly test whether mapped values are set
   */
  public function __isset($name) {
    if (property_exists($this, $name)) 
      return isset($this->$name);
    else
      return isset($this->map{$name});
  }


  /**
   * save the DOM out to a file, including any changes made to sub-objects
   *
   * @param string $filename
   * @return number of bytes written or false if there was an error (return value from DOMDocument->save)
   */
  public function save($filename) {
    $this->dom->formatOutput = true;
    return $this->dom->save($filename);
  }

  /**
   * return the DOM as a string, including any changes made to sub-objects
   *
   * @return string
   */
  public function saveXML() {
      return $this->dom->saveXML($this->domnode);
  }

  
  /**
   * return xml for just a portion of the document based on a configured field
   * 
   * @param string $name
   * @return string xml
   */
  public function getXML($name) {
    if (!isset($this->map{$name}))
      trigger_error("$field is not set, cannot retrieve xml",
		    E_USER_NOTICE);
    return $this->dom->saveXML($this->map{$name});
  }

  /**
   * Update the xml for the entire dom and re-map all attributes
   * @param string $xml
   */
  public function updateXML($xml) {
    $newdom = new DOMDocument();
    $newdom->loadXML($xml);

    // import new xml into this dom
    $newdomnode = $this->dom->importNode($newdom->documentElement,
					 true);  // deep import - include all child nodes
    $this->domnode->parentNode->replaceChild($newdomnode, $this->domnode);
    $this->domnode = $newdomnode;
    $this->domnode = $this->dom->documentElement;
    
    // update all the xml/object mappings so in-memory map reflects new xml
    $this->update();
  }

    /**
   * If a schema is defined, check that xml validates
   * @param $errors optional, passed by reference to store validation errors
   * @return boolean 
   * @throws XmlObjectException if schema is not defined
   */
  public function isValid(&$errors = null) {

    /* NOTE: if the xml for this object is part of a larger object
      (e.g., a datastream in a Fedora foxml object), it will not validate properly.
       Because that condition cannot be accurately detected, simply
       importing the xml for this object into a temporary DOM for validation.    */
    
    if (!isset($this->schema) && $this->schema == '') {
      throw new XmlObjectException("Cannot validate: no schema is defined");
    }

    // capturing errors for optional return, and suppressing ugly output
    libxml_use_internal_errors(true);
    libxml_clear_errors();	// clear any errors from previous validations
	  
    $tmpdom = DOMDocument::loadXML($this->dom->saveXML($this->domnode));
    
    // if an HTTP_PROXY is defined, configure a stream context
    // to use that proxy for loading schemas
    $proxy = getenv('HTTP_PROXY');
    if ($proxy) {
      $proxy_context = stream_context_create(array('http' => array(
	   'proxy' => $proxy,
	   'request_fulluri' => True,
	   )
      ));
      libxml_set_streams_context($proxy_context);
    }

    $valid = $tmpdom->schemaValidate($this->schema);
    $errors = libxml_get_errors();
    return $valid;
  }

}