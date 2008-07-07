<?php

require_once("models/foxmlDatastreamAbstract.php");

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
  
}


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

// generic premis identifier to work for *identifierType and *identifierValue
class premis_identifier extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "type" => array("xpath" => "(premis:objectIdentifierType | premis:eventIdentifierType | premis:linkingAgentIdentifierType)"),
     "value" => array("xpath" => "(premis:objectIdentifierValue | premis:eventIdentifierValue | premis:linkingAgentIdentifierValue)"),
     ));
      parent::__construct($xml, $config, $xpath);
  }
}

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
