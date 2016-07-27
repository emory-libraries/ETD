<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxml.php");
require_once("foxmlDatastreamAbstract.php");

class dublin_core extends foxmlDatastreamAbstract {
  protected $xmlconfig;

  protected $fields;
  protected $additional_fields;

  /**
     * location of schema to use for validating xml
     * NOTE: canonical oai_dc schema is here:  http://www.openarchives.org/OAI/2.0/oai_dc.xsd
     * using a local copy referencing local simple dc xsd to avoid network problems
     * @var string
     */
    protected $schema = "https://larson.library.emory.edu/schemas/oai_dc.xsd";
  const xml_namespace = "http://purl.org/dc/elements/1.1/";
  protected $dcns = dublin_core::xml_namespace;

  public $dslabel = "Dublin Core Metadata";
    public $control_group = FedoraConnection::MANAGED_DATASTREAM;
    public $state = FedoraConnection::STATE_ACTIVE;
    public $versionable = true;
    public $mimetype = 'text/xml';
    // format_uri ?  http://www.openarchives.org/OAI/2.0/oai_dc/ == ?

  public function __construct($xml = null) {
        if (is_null($xml)) {
            $xml = $this->construct_from_template();
        }
    $this->addNamespace("dc", $this->dcns);

    $this->fields = array("contributor", "coverage", "creator", "date", "description",
        "format", "identifier", "language", "publisher", "relation",
        "source", "subject", "title", "type", "rights");
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config);
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {
    // since all paths are the same, generate config array by a list of field names
    foreach ($this->fields as $dcterm) {
      // configure one singular mapping that will match the first entry (e.g., creator)
      $this->xmlconfig[$dcterm] = array("xpath" => "dc:$dcterm");
      // configure a second, _plural_ mapping that will be a series (e.g., creators)
      $this->xmlconfig[$dcterm . "s"] = array("xpath" => "dc:$dcterm", "is_series" => true);
    }

    // a few special cases
    $this->xmlconfig["ark"] = array("xpath" => "dc:identifier[contains(., 'ark')]");
    $this->xmlconfig["fedora_pid"] = array("xpath" => "dc:identifier[contains(., 'info:fedora')]");

    $this->additional_fields = array("ark", "fedora_pid");
  }

    protected function construct_from_template() {
        $dom = new DOMDocument();
        $dom->loadXML(file_get_contents("dublin_core.xml", FILE_USE_INCLUDE_PATH));
        return $dom;
    }


  // simple check - should only be dc terms
  protected function validDCname($name) {
    return in_array($name, $this->fields)
      || in_array(rtrim($name, "s"), $this->fields)	// pluralized version of field name
      || in_array($name, $this->additional_fields);
  }


  public function &__get($name) {
    if (!$this->validDCname($name)) {
      throw new XmlObjectException("$name is not a dublin core field");
    }
    return parent::__get($name);
  }

  // FIXME: extend __set function to create a new node if there isn't one?
  public function __set($name, $value) {
    if (!$this->validDCname($name)) {
      throw new XmlObjectException("$name is not a dublin core field");
    }
    // NOTE: for now, assuming no repeats/series
    if (! isset($this->map{$name})) {
      $value = str_replace("& ", "&amp; ", $value);
      $newdc = $this->dom->createElementNS($this->dcns, "dc:" . $name, $value);
      $newdc = $this->domnode->appendChild($newdc);
      $this->map{$name} = $newdc;
    }

    return parent::__set($name, $value);
  }

  public function addDCNode($name, $value) {
    if (! $this->validDCname ( $name )) {
      throw new XmlObjectException ( "$name is not a dublin core field" );
    }
    $value = str_replace ( "& ", "&amp; ", $value );
    $newdc = $this->dom->createElementNS ( $this->dcns, "dc:" . $name, $value );
    $newdc = $this->domnode->appendChild ( $newdc );
    $this->map {$name} = $newdc;

    return parent::__set ( $name, $value );
  }


  // generic function to set any DC element that is a series
  private function setSeries($name, array $values) {
    if (!isset($this->{$name})) {
      trigger_error("element $name is not set", E_USER_WARNING);
      return;
    } elseif (!($this->{$name} instanceof DOMElementArray)) {
      trigger_error("element $name is not a series", E_USER_ERROR);
      return;
    }

    // initialized properly but no elements
    if (count($this->{$name}) == 0) {
      $this->{rtrim($name, "s")} = "";	// allow __set to initialize object
      $this->update();
    }

    for ($i = 0; $i < count($values); $i++) {
      if (isset($this->{$name}[$i])) $this->{$name}[$i] = $values[$i];
      else $this->{$name}->append($values[$i]);
    }

    // remove any elements beyond the set of new ones
    while (isset($this->{$name}[$i]) && $this->{$name}[$i] != '') {	// don't remove blank elements
      $this->removeNode(rtrim($name, "s"),  $this->{$name}[$i]);
    }
    $this->update();
  }

  public function removeNode($name, $value) {
    // remove the node from the xml dom
    $nodelist = $this->xpath->query("//dc:" . $name . "[. = '$value']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);
      $node->parentNode->removeChild($node);
    }
    // update in-memory array so it will reflect the change
    $this->update();
  }


  public function setSubjects(array $subjects) {
    $this->setSeries("subjects", $subjects);
  }

  public function setCreators(array $creators) {
    $this->setSeries("creators", $creators);
  }

  public function setContributors(array $contrib) {
    $this->setSeries("contributors", $contrib);
  }

  public function setRelations(array $contrib) {
    $this->setSeries("relations", $contrib);
  }

  public function setTypes(array $contrib) {
    $this->setSeries("types", $contrib);
  }

  // set ark : if an ark identifier node does not currently exist, add a new one
  public function setArk($ark) {
    $this->update();
    if (isset($this->ark)) {
      // is this an error? ark shouldn't change
      $this->ark = $ark;
    } else if (! count($this->identifiers)) {
      $this->identifier = $ark;
    } else {
      $this->identifiers->append($ark);
      $this->update();	// update so the 'ark' mapping will get picked up
    }
  }

}
