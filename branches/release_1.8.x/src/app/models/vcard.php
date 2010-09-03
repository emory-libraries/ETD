<?php

/**
 * vcard xml object - no longer in use? (Fez hold-over?)
 *
 * @category   Etd
 * @package    Etd_Models
 * @subpackage Author_Info
 * @deprecated
 */

require_once("xml-utilities/XmlObject.class.php");
require_once("fedora/models/foxml.php");

class vcard extends XmlObject {
  const namespace = "http://www.w3.org/2001/vcard-rdf/3.0#";
  
  public function __construct($dom, $xpath = null) {
    $this->addNamespace("v", self::namespace);

    $config = $this->config(array(
     "fullname" => array("xpath" => "v:FN"),
     "name" => array("xpath" => "v:N", "class_name" => "vcard_name"),
     "telephone" => array("xpath" => "v:TEL"),
     "email" => array("xpath" => "v:EMAIL[@TYPE='current']"),
     "permanent_email" => array("xpath" => "v:EMAIL[@OTHERTYPE='permanent']"),
     "street" => array("xpath" => "v:ADR/v:Street"),
     "city" => array("xpath" => "v:ADR/v:Locality"),
     "state" => array("xpath" => "v:ADR/v:Region"),
     "zip" => array("xpath" => "v:ADR/v:Pcode"),
     "country" => array("xpath" => "v:ADR/v:Country"),
     "uid" => array("xpath" => "v:UID"),
     "degree" => array("xpath" => "v:NOTE[@TYPE='degree']"),
      ));
    parent::__construct($dom, $config, $xpath);
  }


  /* fixme: store template in a file? in Fedora?
   */
  public static function getTemplate() {
    return '<v:VCARD xmlns:v="http://www.w3.org/2001/vcard-rdf/3.0#">
  <v:FN/>
  <v:N>
    <v:Family/>
    <v:Given/>
    <v:Other/>
    <v:Prefix/>
    <v:Suffix/>
  </v:N>
  <v:TEL/>
  <v:EMAIL OTHERTYPE="current"/>
  <v:EMAIL OTHERTYPE="permanent"/>
  <v:ADR>
    <v:Street/>
    <v:Locality/>
    <v:Region/>
    <v:Pcode/>
    <v:Country/>
  </v:ADR>
  <v:UID TYPE="netid"/>
</v:VCARD>';
  }

  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("vCard", "User Information", self::getTemplate());
  }

  /*  public function getDOM() {
    return $this->dom;
    }*/
}

class vcard_name extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "last" => array("xpath" => "v:Family"),
     "first" => array("xpath" => "v:Given"),
     "middle" => array("xpath" => "v:Other"),
     "prefix" => array("xpath" => "v:Prefix"),
     "suffix" => array("xpath" => "v:Suffix"),
     ));
    parent::__construct($xml, $config, $xpath);
    }
}

?>