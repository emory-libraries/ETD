<?php

require_once("dublin_core.php");

class qualified_dublin_core extends dublin_core {
  protected $dctermsns = "http://purl.org/dc/terms/";
  protected $xsins = "http://www.w3.org/2001/XMLSchema-instance";

  private $dcterms;

  protected $additional_fields;
  
  protected function configure() {
    parent::configure();

    // list of dcterms (not including original dc fields)
    $this->dcterms = array("abstract", "accessRights", "accrualMethod", "accrualPeriodicity", "accrualPolicy", "alternative", "audience", "available", "bibliographicCitation", "conformsTo", "created", "dateAccepted", "dateCopyrighted", "dateSubmitted", "educationLevel", "extent", "hasFormat", "hasPart", "hasVersion", "instructionalMethod", "isFormatOf", "isPartOf", "isReferencedBy", "isReplacedBy", "isRequiredBy", "issued", "isVersionOf", "license", "mediator", "medium", "modified", "provenance", "references", "replaces", "requires", "rights", "rightsHolder", "spatial", "tableOfContents", "temporal", "valid");

    
    $this->addNamespace("dcterm", $this->dctermsns);
    $this->addNamespace("xsi", $this->xsins);

    // configure dcterms similar to dc 
    foreach ($this->dcterms as $term) {
      $this->xmlconfig[$term] = array("xpath" => "dcterm:$term");
    }

    // any adjustments to stock dc fields
    $this->xmlconfig['identifier']['is_series'] = true;

  }

  // simple check - should only be dc terms
  protected function validDCname($name) {
    return parent::validDCname($name) || in_array($name, $this->dcterms);
  }




}