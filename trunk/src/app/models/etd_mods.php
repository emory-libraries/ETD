<?php

require_once("XmlObject.class.php");

// FIXME: mappings for dates

class etd_mods extends XmlObject {

  public function __construct($dom, $xpath = null) {
      $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
      
      $config = $this->config(array(
       "title" => array("xpath" => "//mods:titleInfo/mods:title"),
       "author" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'author']",
		       "class_name" => "mods_name"),
       "department" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation"),
       "advisor" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'Thesis Advisor']",
		       "class_name" => "mods_name"),

       // can't seem to filter out empty committee members added by Fez...
       "committee" => array("xpath" => "//mods:name[mods:description = 'Emory Committee Member']",
			    "class_name" => "mods_name", "is_series" => "true"),

       "nonemory_committee" => array("xpath" => "//mods:name[mods:description = 'Non-Emory Committee Member']",
			    "class_name" => "mods_name", "is_series" => "true"),
       "genre" => array("xpath" => "//mods:genre"),
       "abstract" => array("xpath" => "//mods:abstract"),
       "toc" => array("xpath" => "//mods:tableOfContents"),
       "researchfields" => array("xpath" => "//mods:subject[@authority='proquestresearchfield']/mods:topic",
				 "is_series" => true),
       "keywords" => array("xpath" => "//mods:subject[@authority='keyword']/mods:topic",
			   "is_series" => true),
       "pages" => array("xpath" => "//mods:extent[@unit='pages']/mods:total")
       ));

      parent::__construct($dom, $config, $xpath);
    }

    public function addResearchField($text) {
      $this->addSubject($text, "researchfields", "proquestresearchfield");
    }

    public function addKeyword($text) {
      $this->addSubject($text, "keywords", "keyword");
    }

    //  function to add subject/topic pair - used to add keyword & research field
    public function addSubject($text, $mapname, $authority = null) {
      // add a new subject/topic to the DOM and the in-memory map
      $subject = $this->dom->createElementNS($this->namespaceList["mods"], "mods:subject");
      if (! is_null($authority)) 
	$subject->setAttribute("authority", $authority);
      $topic = $this->dom->createElementNS($this->namespaceList["mods"],
					     "mods:topic", $text);
      $topic = $subject->appendChild($topic);

      // find first node following current type of subjects and append before
      $nodeList = $this->xpath->query("//mods:subject[@authority='$authority'][last()]/following-sibling::*");
      
      // if a context node was found, insert the new node before it
      if ($nodeList->length) {
	$contextnode = $nodeList->item(0);
	//	print "attempting to insert before:\n";
	//	print $this->dom->saveXML($nodeList->item(0)) . "\n";
	
	$contextnode->parentNode->insertBefore($subject, $contextnode);
      } else {
	// if no context node is found, new node will be appended at end of xml document
	$this->dom->documentElement->appendChild($subject);
      }

      $this->map{$mapname}[] = $topic;
    }

    public static function getFedoraTemplate(){
      return foxml::xmlDatastreamTemplate("MODS", "Descriptive Metadata", self::getTemplate());
    }
    
    public static function getTemplate() {
      return '<mods:mods xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema">
  <mods:titleInfo>
    <mods:title/>
  </mods:titleInfo>
  <mods:name type="personal">
    <mods:namePart type="given"/>
    <mods:namePart type="family"/>
    <mods:displayForm/>
    <mods:affiliation/>
    <mods:role>
      <mods:roleTerm authority="marcrelator" type="text">author</mods:roleTerm>
    </mods:role>
  </mods:name>
  <mods:name type="personal">
    <mods:namePart type="given"/>
    <mods:namePart type="family"/>
    <mods:displayForm/>
    <mods:role>
      <mods:roleTerm authority="marcrelator" type="text">Thesis Advisor</mods:roleTerm>
    </mods:role>
  </mods:name>
  <mods:name type="personal">
    <mods:namePart type="given"/>
    <mods:namePart type="family"/>
    <mods:displayForm/> 
    <mods:role>
      <mods:roleTerm type="text">Committee Member</mods:roleTerm>
    </mods:role>
    <mods:description>Emory Committee Member</mods:description>
   </mods:name>
  <mods:name type="personal">
    <mods:namePart type="given"/>
    <mods:namePart type="family"/>
    <mods:displayForm/> 
    <mods:role>
      <mods:roleTerm type="text">Committee Member</mods:roleTerm>
    </mods:role>
    <mods:description>Emory Committee Member</mods:description>
   </mods:name>
  <mods:name type="personal">
    <mods:namePart type="given"/>
    <mods:namePart type="family"/>
    <mods:displayForm/>
    <mods:affiliation/>
    <mods:role>
      <mods:roleTerm type="text">Committee Member</mods:roleTerm>
    </mods:role>
    <mods:description>Non-Emory Committee Member</mods:description>
  </mods:name>
  <mods:name type="corporate">
    <mods:namePart>Emory University</mods:namePart>
    <mods:role>
      <mods:roleTerm authority="marcrelator" type="text">Degree grantor</mods:roleTerm>
    </mods:role>
  </mods:name>
  <mods:genre authority="aat"/>
  <mods:originInfo>
    <mods:dateIssued keyDate="yes"/>
    <mods:copyrightDate qualifier="inferred"/>
    <mods:dateOther type="embargoedUntil"/>
  </mods:originInfo>
  <mods:language>
    <mods:languageTerm authority="iso639-2b" type="text">English</mods:languageTerm>
  </mods:language>
  <mods:physicalDescription>
    <mods:form authority="marcform">electronic</mods:form>
    <mods:internetMediaType>application/pdf</mods:internetMediaType>
    <mods:digitalOrigin>born digital</mods:digitalOrigin>
  </mods:physicalDescription>
  <mods:abstract/>
  <mods:tableOfContents/>
  <mods:subject ID="" authority="proquestresearchfield">
    <mods:topic/>
  </mods:subject>
  <mods:subject authority="keyword">
    <mods:topic/>
  </mods:subject>
  <mods:part>
    <mods:detail>entire dissertation (pdf format)</mods:detail>
    <mods:extent unit="pages">
      <mods:total></mods:total>
    </mods:extent>
  </mods:part>
</mods:mods>';
    }


    
}

class mods_name extends XmlObject {
  public function __construct($xml, $xpath) {
      $config = $this->config(array(
     "full" => array("xpath" => "mods:displayForm"),
     "first" => array("xpath" => "mods:namePart[@type='given']"),
     "last" => array("xpath" => "mods:namePart[@type='family']"),
     "affiliation" => array("xpath" => "mods:affiliation"),
     "role" => array("xpath" => "mods:role/mods:roleTerm"),
     "role_authority" => array("xpath" => "mods:role/@authority"),
     ));
      parent::__construct($xml, $config, $xpath);
    }
}

