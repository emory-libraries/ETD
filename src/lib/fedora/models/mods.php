<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxml.php");
require_once("foxmlDatastreamAbstract.php");

// FIXME: mappings for dates


/**
 *  MODS XmlObject / Fedora Foxml datastream
 *
 * @property string $title
 * @property array of mods_name $name
 * @property string $typeOfResource
 * @property mods_originInfo $originInfo
 * @property string $date originInfo date with attribute keyDate='yes'
 * @property mods_language $language
 * @property mods physicalDescription $physicalDescription
 * @property string $abstract
 * @property string $tableOfContents
 * @property string $genre
 * @property array of mods_subject $subjects
 * @property string $identifier
 * @property mods_location $location
 * @property array of string $accessCondition
 * @property string $useAndReproduction
 * @property mods_recordInfo $recordInfo
 * @property string $id top-level id attribute
 * @property mods $relatedItem 
 * 
 */
class mods extends foxmlDatastreamAbstract {
  
  public $dslabel = "Descriptive Metadata";
  public $control_group = FedoraConnection::MANAGED_DATASTREAM;
  public $state = FedoraConnection::STATE_ACTIVE;
  public $versionable = true;
  public $mimetype = 'text/xml';
  // format uri?
  /**
    * schema location for validating MODS xml
    * canonical schema location is here: http://www.loc.gov/standards/mods/v3/mods-3-2.xsd
    * @var string
    */
  protected $schema = "https://larson.library.emory.edu/schemas/mods-3-2.xsd";
  const MODS_NS = "http://www.loc.gov/mods/v3";
  protected $namespace = mods::MODS_NS;

  protected $xmlconfig;

  // enable adding certain configured xml fields by setting them
  protected $ADD_MISSING_FIELDS_ON_SET = true;
  
  public function __construct($dom = null, $xpath = null) {
    if (is_null($dom)) {
      $dom = $this->construct_from_template();
    }
    $this->addNamespace("mods", $this->namespace);
 
    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($dom, $config, $xpath);

  
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {

    $this->xmlconfig =  array(
  "title" => array("xpath" => "mods:titleInfo/mods:title"),
  "name" => array("xpath" => "mods:name", "class_name" => "mods_name", "is_series" => true),
  "typeOfResource" => array("xpath" => "mods:typeOfResource"),
  "originInfo" => array("xpath" => "mods:originInfo", "class_name" => "mods_originInfo"), 
  // also map sub elements ? (dates, key date)
  
  // pick up key date as top-level date
  "date" => array("xpath" => "mods:originInfo/*[@keyDate='yes']"),

  "language" => array("xpath" => "mods:language", "class_name" => "mods_language"),
  
  "physicalDescription" => array("xpath" => "mods:physicalDescription",
               "class_name" => "mods_physicalDescription"),
  
  "abstract" => array("xpath" => "mods:abstract"),
  "tableOfContents" => array("xpath" => "mods:tableOfContents"),
  "genre" => array("xpath" => "mods:genre"),
  "subjects" => array("xpath" => "mods:subject", "is_series" => true,
          "class_name" => "mods_subject"),
  "identifier" => array("xpath" => "mods:identifier"),
  "location" => array("xpath" => "mods:location", "class_name" => "mods_location"),
  "accessCondition" => array("xpath" => "mods:accessCondition", "is_series" => true),
  "useAndReproduction" => array("xpath" => "mods:accessCondition[@type='useAndReproduction']"),
  "recordInfo" => array("xpath" => "mods:recordInfo",
            "class_name" => "mods_recordInfo"),

  "id" => array("xpath" => "@ID"),  // probably only used for related Items
  // any top-level mods item can be under relatedItem-- basically a nested mods 
  "relatedItem" => array("xpath" => "mods:relatedItem",
             "class_name" => "mods"),
      );
            
  }

  protected function construct_from_template() {
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents("mods.xml", FILE_USE_INCLUDE_PATH));
    return $dom;
  }
}

// common settings that should be inherited by all mods sub-objects
abstract class modsXmlObject extends XmlObject {
  protected $ADD_MISSING_FIELDS_ON_SET = true;
  protected $namespace = mods::MODS_NS;
}

/**
 *  MODS name XmlObject
 *
 * @property string $id
 * @property string $type
 * @property string $full full name from displayForm
 * @property string $first namePart given
 * @property string $last namePart family
 * @property string $affiliation
 * @property string $description
 * @property string $role role term
 * @property string $role_authority role authority attribute
 */
class mods_name extends modsXmlObject {
  public function __construct($xml, $xpath) {
      $config = $this->config(array(
     "id" => array("xpath" => "@ID"),           
     "type" => array("xpath" => "@type"),
     "full" => array("xpath" => "mods:displayForm"),
     // generic namePart
     "namePart" => array("xpath" => "mods:namePart"),
     "first" => array("xpath" => "mods:namePart[@type='given']"),
     "last" => array("xpath" => "mods:namePart[@type='family']"),
     "affiliation" => array("xpath" => "mods:affiliation"),
     "description" => array("xpath" => "mods:description"),
     "role" => array("xpath" => "mods:role/mods:roleTerm"),
     "role_authority" => array("xpath" => "mods:role/@authority"),
     ));
      parent::__construct($xml, $config, $xpath);
    }

  // full name is default display content
  public function __toString() {
    return $this->full;
  }
}

/**
 *  MODS originInfo XmlObject
 *
 * @property string $issued dateIssued
 * @property string $created dateCreated
 * @property string $captured dateCaptured
 * @property string $valid dateValid
 * @property string $modified dateModified
 * @property string $copyright copyrightDate
 * @property string $dateOther
 * @property string $place
 * @property string $publisher
 * @property string $edition
 * @property string $issuance
 * @property string $frequency
 */
class mods_originInfo extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "issued" => array("xpath" => "mods:dateIssued"),
     "created" => array("xpath" => "mods:dateCreated"),
     "captured" => array("xpath" => "mods:dateCaptured"),
     "valid" => array("xpath" => "mods:dateValid"),
     "modified" => array("xpath" => "mods:dateModified"),
     "copyright" => array("xpath" => "mods:copyrightDate"),
     "dateOther" => array("xpath" => "mods:dateOther"),
     // FIXME: may need mods_date class to set keyDate, start/end, qualifier attributes
     "place" => array("xpath" => "mods:place"),
     "publisher" => array("xpath" => "mods:publisher"),
     "edition" => array("xpath" => "mods:edition"),
     "issuance" => array("xpath" => "mods:issuance"),
     "frequency" => array("xpath" => "mods:frequency"),
     ));
      parent::__construct($xml, $config, $xpath);
    }
}

/**
 *  MODS physicalDescription XmlObject
 *
 * @property string $form
 * @property string $mimetype internet media type
 * @property string $digitalOrigin
 * @property string $extent
 * @property string $details  mods:note - physical details
 * @property string $description mods:note - physical description
 */
class mods_physicalDescription extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "form" => array("xpath" => "mods:form"),
     "mimetype" => array("xpath" => "mods:internetMediaType"),
     "digitalOrigin" => array("xpath" => "mods:digitalOrigin"),
     "extent" => array("xpath" => "mods:extent"),

     // notes that can be used
     "details" => array("xpath" => "mods:note[@type='phsyical details']"),
     "description" => array("xpath" => "mods:note[@type='phsyical description']"),
     ));
      parent::__construct($xml, $config, $xpath);
  }
}

/**
 *  MODS language XmlObject
 *
 * @property string $text
 * @property string $code
 */
class mods_language extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "text" => array("xpath" => "mods:languageTerm[@type='text']"),
     "code" => array("xpath" => "mods:languageTerm[@type='code']"),
     ));
      parent::__construct($xml, $config, $xpath);
  }

  // default display content
  public function __toString() {
    return $this->text;
  }
  
}

/**
 *  MODS subject XmlObject
 *
 * @property string $id id attribute
 * @property string $authority
 * @property string $topic
 * @property string $continent
 * @property string $area
 * @property string $country
 * @property string $region
 * @property string $city
 */
class mods_subject extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "id" => array("xpath" => "@ID"),
     "authority" => array("xpath" => "@authority"),
     "topic" => array("xpath" => "mods:topic"),
     // could also have others (geographic, temporal, etc.)

     // hierarchical geography
     "continent" => array("xpath" => "mods:hierarchicalGeographic/mods:continent"),
     "area" => array("xpath" => "mods:hierarchicalGeographic/mods:area"),    
     "country" => array("xpath" => "mods:hierarchicalGeographic/mods:country"),
     "region" => array("xpath" => "mods:hierarchicalGeographic/mods:region"),
     "province" => array("xpath" => "mods:hierarchicalGeographic/mods:province"),
     "state" => array("xpath" => "mods:hierarchicalGeographic/mods:state"),
     "city" => array("xpath" => "mods:hierarchicalGeographic/mods:city"),
     ));
      parent::__construct($xml, $config, $xpath);
  }

  // default display content
  public function __toString() {
    return $this->topic;
  }
}

/**
 *  MODS recordInfo XmlObject
 *
 * @property string $source record content source
 * @property string $created record creation date
 * @property string $modified record change date
 * @property string $identifier record identifier
 * @property string $origin record origin
 */
class mods_recordInfo extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "source" => array("xpath" => "mods:recordContentSource"),
     "created" => array("xpath" => "mods:recordCreationDate"),
     "modified" => array("xpath" => "mods:recordChangeDate"),
     "identifier" => array("xpath" => "mods:recordIdentifier"),
     "origin" => array("xpath" => "mods:recordOrigin"),
     ));
      parent::__construct($xml, $config, $xpath);
  }
}

/**
 *  MODS location XmlObject
 *
 * @property string $url
 * @property string $primary  url with usage 'primary display'
 */
class mods_location extends modsXmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
          // fixme: could be an array?
  "url" => array("xpath" => "mods:url"),
  "primary" => array("xpath" => "mods:url[@usage='primary display']"),
   ));
    parent::__construct($xml, $config, $xpath);
  }

  public function __set($name, $value) {
      if ($this->ADD_MISSING_FIELDS_ON_SET && $name == "primary" && !isset($this->primary)) {
          // if primary display url is not in mods, add the node before setting
          $url = $this->dom->createElementNS(mods::MODS_NS, "mods:url");
          $url->setAttribute("usage", "primary display");
          $this->domnode->appendChild($url);
          $this->update();
      }
      parent::__set($name, $value);
  }
}

/**
 *  MODS note XmlObject
 *
 * @property string $id record id
 * @property string $topic record topic
 */
class mods_note extends modsXmlObject {    
  public function __construct($xml, $xpath) {     
    $config = $this->config(array(
     "id" => array("xpath" => "@ID"),
     "topic" => array("xpath" => ".")  
     ));
    parent::__construct($xml, $config, $xpath);
  }
}
