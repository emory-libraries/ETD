<?php
require_once("models/foxmlDatastreamAbstract.php");

/**
 * premis foxml datastream
 *
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property premis_object $object
 * @property premis_event $event
 */
class premis extends foxmlDatastreamAbstract  {

  protected $schema = "http://www.loc.gov/standards/premis/v1/PREMIS-v1-1.xsd";
  protected $namespace = "http://www.loc.gov/standards/premis/v1";

  protected $xmlconfig;
  const dslabel = "Preservation Metadata - Record History";
  
  public function __construct($xml) {
    $this->addNamespace("premis", $this->namespace);

    $this->xmlconfig =  array(
      "object" => array("xpath" => "premis:object", "class_name" => "premis_object"),
      "event" => array("xpath" => "premis:event", "class_name" => "premis_event", "is_series" => true),
      );

    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config);

  }
  
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("PREMIS", premis::dslabel,
					file_get_contents("premis.xml", FILE_USE_INCLUDE_PATH),
					"A", "false");		// datastream should NOT be versionable
  }

  public function datastream_label() {
    return premis::dslabel;
  }



  // need a function to add another event
  public function addEvent($type, $detail, $outcome, array $agent, $date = null) {
    // date/time defaults to now
    if (is_null($date)) {
      $date = date(DATE_W3C);
    }

    // if the first event is empty, use that one
    if ((count($this->map{"event"}) == 1)
	&& $this->map{"event"}[0]->identifier->value == "") {
      $event = $this->map{"event"}[0];
    } else {
      // otherwise, clone the xml for the first event and set all the values
      $eventnode = $this->map{"event"}[0]->domnode->cloneNode(true);
      $eventnode = $this->domnode->appendChild($eventnode);

      // map new dom node to xml object
      $event = new premis_event($eventnode, $this->xpath);
      
      // update in-memory map so it can be accessed normally 
      $this->update();
    }

    // calculate new identifier based on current object identifier and number of events
    $event->identifier->value = $this->object->identifier->value . "." . count($this->event);
    $event->type = $type;
    $event->date = $date;
    $event->detail = $detail;
    $event->outcome = $outcome;
    list($agentid, $agentval)  = $agent;
    $event->agent->type = $agentid;
    $event->agent->value = $agentval;
  }

  public function removeEvent($id) {
    $nodelist = $this->xpath->query("//premis:event[premis:eventIdentifier/premis:eventIdentifierValue = '$id']");
    if ($nodelist->length == 0) {
      trigger_error("No premis events found matching identifier '$id'", E_USER_NOTICE);
      return;
    }
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);
    }

    // update in-memory array so it will reflect the change
    $this->update();

    
  }
  
}

/**
 * XmlObject for the object section of premis
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $type
 * @property premis_identifier $identifier
 * @property string $category object category
 */
class premis_object extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "type" => array("xpath" => "@type"),
     "identifier" => array("xpath" => "premis:objectIdentifier", "class_name" => "premis_identifier"),
     "category" => array("xpath" => "premis:objectCategory"),
     ));
      parent::__construct($xml, $config, $xpath);
  }
}

/**
 * XmlObject for premis identifiers
 * generic premis identifier to work for *identifierType and *identifierValue
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $type object, event, or linking agent identifier type
 * @property string $value object, event, or linking agent identifier value
 */
class premis_identifier extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "type" => array("xpath" => "(premis:objectIdentifierType | premis:eventIdentifierType | premis:linkingAgentIdentifierType)"),
     "value" => array("xpath" => "(premis:objectIdentifierValue | premis:eventIdentifierValue | premis:linkingAgentIdentifierValue)"),
     ));
      parent::__construct($xml, $config, $xpath);
  }
}

/**
 * XmlObject for event section of premis
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property premis_identifier $identifier
 * @property string $type
 * @property string $date
 * @property string $detail
 * @property string $outcome
 * @property premis_identifier $agent
 */
class premis_event extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "identifier" => array("xpath" => "premis:eventIdentifier", "class_name" => "premis_identifier"),
     "type" => array("xpath" => "premis:eventType"),
     "date" => array("xpath" => "premis:eventDateTime"),
     "detail" => array("xpath" => "premis:eventDetail"),
     "outcome" => array("xpath" => "premis:eventOutcomeInformation/premis:eventOutcome"),
     //outcome detail?
     "agent" => array("xpath" => "premis:linkingAgentIdentifier", "class_name" => "premis_identifier"),
     

     ));
      parent::__construct($xml, $config, $xpath);
  }
  
}
