<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxml.php");
require_once("foxmlDatastreamAbstract.php");

/**
 * datastream object for RELS-EXT
 *
 * @property string $description
 * @property string $about pid of the object to which these relations apply
 * @property string $memberOf pid of object that is related to this object by isMemberOf relation
 * @property string $hasPart pid of object that is related to this object by hasPart relation
 * @property string $isPartOf pid of object that is related to this object by isPartOf relation
 * @property string $hasModel pid of object's content model (fedora-model:hasModel relation)
 * @property DOMElementArray $hasModels array of object's content model (fedora-model:hasModel relations)
 * @property string $isConstituentOf
 * @property string $isMemberOfCollection
 * @property DOMElementArray $isMemberOfCollections
 * @property string $hasConstituent pid of object that is related to this object by hasConstituent relation
 * @property DOMElementArray $hasConstituents array of objects related by hasConstituent
 * @property string $oaiID  oai identifier
 */
class rels_ext extends foxmlDatastreamAbstract {
  /**
   * rdf namespace
   */
  const RDF_NS = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";

  /**
   * fedora-rel namespace
   */
  const FEDORA_REL_NS = "info:fedora/fedora-system:def/relations-external#";

  /**
   * fedora-model namespace
   */
  const FEDORA_MODEL_NS = "info:fedora/fedora-system:def/model#";

  /**
   * oai namespace
   */
  const OAI_NS = "http://www.openarchives.org/OAI/2.0/";

  /**
   * default label for Fedora datastream
   * @var string
   */
  public $dslabel = "Relationships to other objects";
  public $control_group = FedoraConnection::MANAGED_DATASTREAM;
  public $state = FedoraConnection::STATE_ACTIVE;
  public $versionable = true;
  public $mimetype = "application/rdf+xml";
  public $format_uri = "info:fedora/fedora-system:FedoraRELSExt-1.0";

  /**
   * @var array of namespace uris and short-hand names
   */
  protected $namespaces;

  /**
   * @var array of fedora relation terms
   */
  protected $fedora_rel;

  /**
   * @var array of fedora-model relation terms
   */
  protected $fedora_model;


  protected $xmlconfig;

  public function __construct($xml = null, $xpath = null) {
    if (is_null($xml)) {
      $xml = $this->construct_from_template();
    }

    $this->namespaces = array("rdf" => rels_ext::RDF_NS,
                              "fedora-model" => rels_ext::FEDORA_MODEL_NS,
                              "rel" => rels_ext::FEDORA_REL_NS,
                  "oai" => rels_ext::OAI_NS);
    foreach ($this->namespaces as $ns => $uri)
      $this->addNamespace($ns, $uri);


    //     NOTE: not yet used...
    $this->fedora_rel = array("isPartOf", "hasPart", "isConstituentOf", "hasConstituent",
           "isMemberOf", "hasMember", "isSubsetOf", "hasSubset",
           "isMemberOfCollection", "hasCollectionMember",
           "isDerivationOf", "hasDerivation",
           "isDependentOf", "hasDependent",
           "isDescriptionOf", "HasDescription",
           "isMetadataFor", "HasMetadata",
           "isAnnotationOf", "HasAnnotation",
           "hasEquivalent");
    $this->fedora_model = array("hasModel");

    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config, $xpath);
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {
    // TODO: add mappings for all default rels (using fedora_rel and fedora_model)
    // NOTE: how to know which ones should be set as series ?

    $this->xmlconfig =  array(
     "description" => array("xpath" => "rdf:Description"),
     "about" => array("xpath" => "rdf:Description/@rdf:about"),
     "memberOf" => array("xpath" => "rdf:Description/rel:isMemberOf/@rdf:resource"),
     "hasPart" => array("xpath" => "rdf:Description/rel:hasPart/@rdf:resource"),
     "isPartOf" => array("xpath" => "rdf:Description/rel:isPartOf/@rdf:resource"),
     "hasModel" => array("xpath" => "rdf:Description/fedora-model:hasModel/@rdf:resource"),
     "hasModels" => array("xpath" => "rdf:Description/fedora-model:hasModel/@rdf:resource", "is_series" => true),

     "isConstituentOf" => array("xpath" => "rdf:Description/rel:isConstituentOf/@rdf:resource"),
     "isMemberOfCollection" => array("xpath" => "rdf:Description/rel:isMemberOfCollection/@rdf:resource"),
     "isMemberOfCollections" => array("xpath" => "rdf:Description/rel:isMemberOfCollection/@rdf:resource",
                                    "is_series" => true),
      "hasConstituent" => array("xpath" => "rdf:Description/rel:hasConstituent/@rdf:resource"),
      // plural form of same relation
      "hasConstituents" => array("xpath" => "rdf:Description/rel:hasConstituent/@rdf:resource",
                              "is_series" => true),

       "oaiID" => array("xpath" => "rdf:Description/oai:itemID"),
       "oaiSetSpec" => array("xpath" => "rdf:Description/oai:setSpec"),
       "oaiSetName" => array("xpath" => "rdf:Description/oai:setName"),


       "hasService" => array("xpath" => "rdf:Description/fedora-model:hasService/@rdf:resource"),
       "isDeploymentOf" => array("xpath" => "rdf:Description/fedora-model:isDeploymentOf/@rdf:resource"),
       "isContractorOf" => array("xpath" => "rdf:Description/fedora-model:isContractorOf/@rdf:resource"),
     );
  }


  /**
   * add a new relation to a resource and append to the rdf:Description
   * @param string $relation relation to add, e.g. rel:isPartOf
   * @param string $resource id of the fedora resource, e.g. changeme:123
   */
  public function addRelationToResource($relation, $resource) {
    // add as normal relation, but save value as rdf:resource
    return $this->addRelation($relation, $resource, true);
  }

  /**
   * add a new relation and append to the description
   * @param string $relation relation to add, e.g. rel:isPartOf
   * @param string $value id of the fedora resource, e.g. changeme:123
   * @param boolean $isResource (optional, default is false) value is a resource
   */
  public function addRelation($relation, $value, $isResource = false) {
    list($ns,$tagname)  = split(':',$relation);

    if(! array_key_exists($ns, $this->namespaces)) {
      throw new Exception("Namespace $ns is not defined");
      return;
    }

    $newrel = $this->dom->createElementNS($this->namespaces[$ns], $relation);
    // if relation is to a resource, value should be stored in rdf:resource attribute
    if ($isResource)
      $newrel->setAttributeNS($this->namespaces["rdf"], "rdf:resource", $this->pidToResource($value));
    // NOTE: resource means fedora resource (prepending info:fedora)
    else
      $newrel->nodeValue = $value;

    $this->map{"description"}->appendChild($newrel);

    // update memory map from changed xml
    $this->update();

    // if there was no configuration for the new relation, add one based on tag name
    // NOTE: must be done *after* the update or the mapping will be lost
    if (!isset($this->map[$tagname])) {
      $this->map[$tagname] = $value;
    }

  }

  /**
   * shortcut to add content model relationship hasModel
   * @param string $resource id of the content model object
   */
  public function addContentModel($resource) {
    // FIXME: more generic way to do this for any configured relationship?
    // use list of relation terms / configured xml object properties ?
    $this->addRelationToResource("fedora-model:hasModel", $resource);
  }

  /**
   * set/update oai id; adds a new id if not already present
   * @param string $id oai id
   */
  public function setOAIidentifier($id) {
      if (isset($this->oaiID)) $this->oaiID = $id;
      else $this->addRelation("oai:itemID", $id);
  }

  /**
   * remove a relation from the rels-ext RDF
   * NOTE: only currently handles resources specified as rdf:resource in the xml
   * @param string $relation name of the relation in format ns:relation
   * @param string $resource value of the resource (e.g., changeme:123)
   */
  public function removeRelation($relation, $resource) {
    $fedora_resource = "info:fedora/" . $resource;
    $nodeList = $this->xpath->query("//" . $relation . "[@rdf:resource = '" . $fedora_resource . "']");
    if ($nodeList->length == 1) {
      $rel = $nodeList->item(0);
      $rel = $rel->parentNode->removeChild($rel);	// fixme: check success?
      if ($rel instanceOf DOMNode) {
  // update in-memory map from changed xml
  $this->update();
  return true;
      } else {
  trigger_error("Error removing relation from XML DOM?", E_USER_NOTICE);
      }

    } elseif ($nodeList->length > 1) {
      // warn/notice if couldn't find a single relation to remove (too many / too few?)
      trigger_error("Found more than one relation to remove for $relation $resource, not removing",
        E_USER_NOTICE);
    } elseif ($nodeList->length == 0) {
      trigger_error("Could not find relation to remove: $relation $fedora_resource", E_USER_NOTICE);
    }
  }



  // extend get & set to make info:fedora/ prefix on fedora ids transparent

  public function &__get($name) {
    $value = parent::__get($name);
    if (!($value instanceof DOMElementArray)) {
      $value = str_replace("info:fedora/", "", $value);
    }
    return $value;
  }

  public function __set($name, $value) {
    // if a field is a fedora resource, the value should start with 'info:fedora/'
    if (preg_match("/@rdf:resource/", $this->properties->getXpath($name))) {
        $value = $this->pidToResource($value);
    }

    return parent::__set($name, $value);
  }

  /**
   * Convert fedora pid into rdf:resource format required within rels-ext;
   * if already in that format, it will be unchanged
   * @param string $pid
   * @return string
   */
  public function pidToResource($pid) {
      if (!preg_match("/^info:fedora/", $pid))
        return "info:fedora/" . $pid;
      else
        return $pid;
  }


  /**
   * convert rdf:description to rdf:Description
   */
  public function cleanDescription() {
      $nodelist = $this->domnode->getElementsByTagNameNS(rels_ext::RDF_NS, "description");
      if ($nodelist->length) {
          $desc = $nodelist->item(0);
          $newdesc = $this->dom->createElementNS(rels_ext::RDF_NS, "Description");
          // copy any attributes
          foreach ($desc->attributes as $attrName => $attrNode) {
              $newdesc->setAttributeNodeNS($attrNode);
          }
          // move all child nodes from old description node to the new one
          // store length, since nodes will be moved & childNodes length will change
          $length = $desc->childNodes->length;
          for ($i = 0; $i < $length; $i++) {
            $child = $desc->childNodes->item(0);
            $newdesc->appendChild($child);
          }
          // remove old description and append the new one
          $this->domnode->removeChild($desc);
          $this->domnode->appendChild($newdesc);
      }
      $this->update();
  }

   protected function construct_from_template() {
        $dom = new DOMDocument();
        $dom->loadXML(self::getTemplate());
        return $dom;
    }

  public static function getTemplate() {
    return '<rdf:RDF xmlns:rdf="' . rels_ext::RDF_NS . '"
  xmlns:rel="' . rels_ext::FEDORA_REL_NS . '">
  <rdf:Description rdf:about=""/>
</rdf:RDF>';
  }

}
