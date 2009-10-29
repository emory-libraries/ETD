<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("models/countries.php");
require_once("models/languages.php");
require_once("models/degrees.php");


class ProQuestSubmission extends XmlObject {

  protected $schema = "http://larson.library.emory.edu/schemas/etd/proquest.xsd";

  protected $xmlconfig;

  /* reference to the etd object used to initialize this submission record */
  public $etd;

  // variables for ProQuest submission
  public $zipfile;
  public $ftped;

  private $schema_validation_errors;
  private $dtd_validation_errors;

  public function __construct($dom = null) {
    if (is_null($dom)) {	// by default, initialize from template xml with Emory defaults
      $xml = file_get_contents("PQ_Submission.xml", FILE_USE_INCLUDE_PATH); 
      $this->dom = new DOMDocument();
      $this->dom->loadXML($xml);
    } else {
      $this->dom = $dom;
    }
    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($this->dom, $config);

  }

  // define xml mappings
  protected function configure() {

    $this->xmlconfig =  array(
	"pub_option" => array("xpath" => "@publishing_option"),
	"embargo_code" => array("xpath" => "@embargo_code"),
	"third_party_search" => array("xpath" => "@third_part_search"),

	"author_info"  => array("xpath" => "DISS_authorship", "class_name" => "DISS_authorship"),
	"description"  => array("xpath" => "DISS_description", "class_name" => "DISS_description"),

	// content sections
	"abstract"  => array("xpath" => "DISS_content/DISS_abstract", "class_name" => "DISS_abstract"),
	"pdfs" => array("xpath" => "DISS_content/DISS_binary[@type='PDF']", "is_series" => true),
	"supplements" => array("xpath" => "DISS_content/DISS_attachment", "class_name" => "DISS_attachment",
			      "is_series" => true),
	
	// do we want to use this? (weren't using it before)
	//	"restriction"  => array("xpath" => "DISS_restriction", "class_name" => "DISS_restriction"),
	);
  }


  public function initializeFromEtd(etd $etd) {
    $this->etd = $etd;		// store reference (will be needed for file export)

    // set PQ embargo code according to what Grad School has approved
    /* from the proquest DTD:
     *Embargo code can be 0 corresponding to no embargo OR 
     1 - 6 months embargo
     2 - 1 year embargo
     3 - 2 year embargo
     4 - Reserved for future use
    */
    switch ($this->etd->mods->embargo) {
    case "0 days":
      $this->embargo_code = 0; break;
    case "6 months":
      $this->embargo_code = 1; break;	
    case "1 year":
      $this->embargo_code = 2; break;	
    case "2 years":
    case "6 years":
      $this->embargo_code = 3; break;		// PQ only offers 2 year embargoes
    default:
      // don't set code - error will get caught at validation  time
    }

    // author information
    $this->author_info->name->set($this->etd->mods->author);
    // 	set current and permanent contact information
    if (isset($this->etd->authorInfo->mads->current))
      $this->author_info->current_contact->set($this->etd->authorInfo->mads->current);
    if (isset($this->etd->authorInfo->mads->permanent))
      $this->author_info->permanent_contact->set($this->etd->authorInfo->mads->permanent);


    // description
    $this->description->page_count = $this->etd->mods->pages;
    $this->description->external_id = $this->etd->pid;
    if ($this->etd->mods->genre == "Dissertation")
      $this->description->type = "doctoral";
    elseif ($this->etd->mods->genre == "Master's Thesis")
      $this->description->type = "masters";
    $this->description->copyright = $this->etd->mods->copyright;
    $this->description->title = $this->etd->mods->title;	// plain-text version
    $this->description->date_completed = $this->etd->year();
    // convert date format YYYY-MM-DD to ProQuest's desired mm/dd/yyyy
    $this->description->date_accepted = date("m/d/Y", strtotime($this->etd->mods->originInfo->issued, 0));

    /* FIXME: what format does PQ want here ?
     case "MA": $code = "M.A."; break;
    case "MS": $code = "M.S."; break;
    case "PHD": $code = "Ph.D."; break;
    */
    $this->description->degree = $this->etd->mods->degree->name;
    // institution information for Emory set in template
    // *except* for "institutional contact", which ProQuest uses for department
    $this->description->department = $this->etd->mods->department;

    //    $this->description->advisor->set($this->etd->mods->advisor);
    $this->setCommittee();
    $this->setCategories();
    for ($i = 0; $i < count($this->etd->mods->keywords); $i++) {
      if (isset($this->description->keywords[$i]))
	$this->description->keywords[$i] = $this->etd->mods->keywords[$i]->topic;
      else
	$this->description->keywords->append($this->etd->mods->keywords[$i]->topic);
    }

    // FIXME: is this in the correct format for PQ ?  (should be 2-letter code? )
    $this->description->language = $this->etd->mods->language->text;



    // content
    $this->abstract->set($this->etd->html->abstract);
    $this->setFiles($this->etd->pdfs, $this->etd->supplements); 


    //    $this->prune();
  }


  /**
   * set committee chairs  & members
   */
  public function setCommittee() {
    // chairs/advisor
    $chairs = $this->etd->mods->chair;
    for ($i = 0; $i < count($chairs); $i++) {
      if (isset($this->description->advisor[$i]))
	$this->description->advisor[$i]->set($chairs[$i]);
      else
	$this->description->addCommitteeMember($chairs[$i]);
    }

    // members
    $committee = array_merge($this->etd->mods->committee, $this->etd->mods->nonemory_committee);
    for ($i = 0; $i < count($committee); $i++) {
      if (isset($this->description->committee[$i]))
	$this->description->committee[$i]->set($committee[$i]);
      else
	$this->description->addCommitteeMember($committee[$i]);
    }
  }

  public function setCategories() {
    $fields = $this->etd->mods->researchfields;
    for ($i = 0; $i < count($fields); $i++) {
      if (isset($this->description->categories[$i]))
	$this->description->categories[$i]->set($fields[$i]);
      else
	$this->description->addCategory($fields[$i]);
    }
  }



  public function setFiles(array $pdfs, array $supplements) {	// pass as parameter for easier unit testing
    // pdf(s) of the dissertation
    for ($i = 0; $i < count($pdfs); $i++) {
      if (isset($this->pdfs[$i])) {
	$this->pdfs[$i] = $pdfs[$i]->prettyFilename();
      } else {
	$this->pdfs->append($pdfs[$i]->prettyFilename());
      }
    }

    // information for supplemental files, if there are any
    for ($i = 0; $i < count($supplements); $i++) {
      if (isset($this->supplements[$i])) {
	$this->supplements[$i]->filename = $supplements[$i]->prettyFilename();
	$this->supplements[$i]->description = $supplements[$i]->description();
      } else {
	$this->addSupplement($supplements[$i]->prettyFilename(),
			     $supplements[$i]->description());
      }
    }
      
  }


  public function addSupplement($filename, $description) {
    // deep clone first supplement
    $newnode = $this->map["supplements"][0]->domnode->cloneNode(true);
    // append at end of DISS_content
    $newnode = $this->map["supplements"][0]->domnode->parentNode->appendChild($newnode);
    $this->update();

    $new_supplement = $this->supplements[count($this->supplements) - 1];
    $new_supplement->filename = $filename;
    $new_supplement->description = $description;
  }


  public function prune() {
    $prunelist = $this->xpath->query("//DISS_citizenship[. = ''] 
		| //DISS_phone_fax[DISS_area_code = '' and DISS_phone_num = '']
		| //DISS_middle[. = ''] | //DISS_suffix[. = ''] | //DISS_affiliation[. = '']");
    for ($i = 0; $i < $prunelist->length; $i++) {
      $node = $prunelist->item($i);
      $node->parentNode->removeChild($node);
    }
    
    /*    $prunelist = $this->xpath->query("//DISS_citizenship[. = '']");
    for ($i = 0; $i < $prunelist->length; $i++) {
      $node = $prunelist->item($i);
      $node->parentNode->removeChild($node);
    }

    // special cases

    // if all components were removed from the phone number node, remove the parent node as well
    $prunelist = $this->xpath->query("//DISS_phone_fax[not(./DISS_area_code) and not(./DISS_phone_num)]");
    for ($i = 0; $i < $prunelist->length; $i++) {
      $node = $prunelist->item($i);
      $node->parentNode->removeChild($node);
      }*/
    
  }
  

  public function isValid() {
    // suppress errors so they can be handled and displayed in a more controlled manner
    libxml_use_internal_errors(true);
    
    // validate against PQ DTD
    $dtd_valid = $this->dom->validate();
    $this->dtd_validation_errors = libxml_get_errors();
    libxml_clear_errors();
      
    // also validate against customized & stricter schema, which should
    // be a better indication that this is the data PQ actually wants
    if (isset($this->schema) && $this->schema != '')
     $schema_valid = $this->dom->schemaValidate($this->schema);

    $this->schema_validation_errors = libxml_get_errors();
    // is only valid if both of the validations pass
    return ($dtd_valid && $schema_valid);
  }

  public function dtdValidationErrors() {
    return $this->dtd_validation_errors;
  }
  public function schemaValidationErrors() {
    return $this->schema_validation_errors;
  }


  /**
   * create a zip file in the format requested by ProQuest
   * includes metadata, pdf, and any supplemental files
   */
  public function create_zip($tmpdir) {
    // all filenames start with (or include) authorlastname_authorfirstname
    $filebase = strtolower($this->author_info->name->last . "_" . $this->author_info->name->first);
    $nonfilechars = array("'", ",");	// what other characters are likely to occur in names?
    $replace = array();	// replace all with empty strings 
    $filebase =  str_replace($nonfilechars, $replace, $filebase);


    $this->zipfile = $tmpdir . "/upload_" . $filebase . ".zip";
    
    $zip = new ZipArchive();
    if ($zip->open($this->zipfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE | ZIPARCHIVE::CHECKCONS) != true) {
      trigger_error("Could not initialize zip file", E_USER_WARNING);
      return false; // ??
    }
    // add data directly from a string instead of saving to files first

    // add metadata to the zip file as lastname_firstname_DATA.xml
    // 	  - ProQuest doesn't want the Doctype line included - export starting at root element
    $zip->addFromString($filebase . "_DATA.xml", $this->saveXML($this->dom->documentElement));

    // add PDF(s) to the zip file as lastname_firstname.pdf  (adding #s if multiple)
    for ($i = 0; $i < count($this->etd->pdfs); $i++) {
      $pdf = $this->etd->pdfs[$i];
      $filename = $filebase;
      if ($i > 0) $filename .= ($i + 1);	// use numbers to distinguish after the first pdf
      $filename .= ".pdf";
      $zip->addFromString($filename, $pdf->getFile()); 
    }

    // if there are any supplemental files, they should be placed in a subfolder: lastname_firstname_media
    if (count($this->etd->supplements)) {
      if (!$zip->addEmptyDir($filebase . "_media")) {
	trigger_error("Could not create supplemental file directory in zipfile " . $this->zipfile, E_USER_WARNING);
      }
      foreach ($this->etd->supplements as $supplement) {
	$filename = $filebase . "_media/" . $supplement->prettyFilename();
	$zip->addFromString($filename, $supplement->getFile()); 
      }
    }

    if (!$zip->close()) {
      trigger_error("Could not save zip file $this->zipfile", E_USER_WARNING);
    }

    // return name of the zipfile
    return $this->zipfile;	
  }
  
    

}



class DISS_authorship extends XmlObject {
    public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "author_type" => array("xpath" => "DISS_author/@type"),		// should always be primary
	"name" => array("xpath" => "DISS_author/DISS_name", "class_name" => "DISS_name"),
	"current_contact" => array("xpath" => "DISS_author/DISS_contact[@type='current']",
				   "class_name" => "DISS_contact"),
	"permanent_contact" => array("xpath" => "DISS_author/DISS_contact[@type='future']",
				   "class_name" => "DISS_contact"),
	"citizensip" => array("xpath" => "DISS_author/DISS_citizensip"),	// are we using this?
	));
    parent::__construct($xml, $config, $xpath);
  }
}

class DISS_name extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "last" => array("xpath" => "DISS_surname"),
	"first" => array("xpath" => "DISS_fname"),
	"middle" => array("xpath" => "DISS_middle"),
	"affiliation" => array("xpath" => "DISS_affiliation"),
	));
    parent::__construct($xml, $config, $xpath);
  }
  
  public function set(mods_name $name) {
    $this->last = $name->last;
    $names = split(' ', $name->first);	// given name in mods is first + middle
    $this->first = $names[0];
    if (isset($names[1])) $this->middle = $names[1];
    if (isset($name->affiliation)) $this->affiliation = $name->affiliation;
  }
}

class DISS_contact extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "type" => array("xpath" => "@type"),
	"date" => array("xpath" => "DISS_contact_effdt"),
	"phone" => array("xpath" => "DISS_phone_fax", "class_name" => "DISS_phone"),
	"address" => array("xpath" => "DISS_address", "class_name" => "DISS_address"),
	"email" => array("xpath" => "DISS_email"),
	));
    parent::__construct($xml, $config, $xpath);
  }

  public function __set($name, $value) {
    switch ($name) {
    case "date":	// no matter input date format, save in PQ desired date form
      $value = date("m/d/Y", strtotime($value, 0));  break;
    }
    
    return parent::__set($name, $value);
  }
  
  public function set(mads_affiliation $mads) {
    $this->date =  $mads->date;		// FIXME: date format?
    $this->email = $mads->email;
    if (isset($mads->address)) $this->address->set($mads->address);
    if (isset($mads->phone))   $this->phone->set($mads->phone);
    
  }
    
}

class DISS_address extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "street" => array("xpath" => "DISS_addrline", "is_series" => true),
	"city" => array("xpath" => "DISS_city"),
	"state" => array("xpath" => "DISS_st"),
	"zipcode" => array("xpath" => "DISS_pcode"),
	"country" => array("xpath" => "DISS_country"),
	));
    parent::__construct($xml, $config, $xpath);
  }
  
  public function set(mads_address $address) {
    for ($i = 0; $i < isset($address->street[$i]); $i++) {
      if (isset($this->street[$i])) $this->street[$i] = $address->street[$i];
      else $this->street->append($this->street[$i]);
    }
    $this->city = $address->city;
    $this->state = $address->state;
    $this->zipcode = $address->postcode;
    // translate country name to PQ two-letter code
    $countrylist = new countries();
    $this->country = $countrylist->codeByName($address->country);
  }
}

class DISS_phone extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "country_code" => array("xpath" => "DISS_cntry_cd"),
	"area_code" => array("xpath" => "DISS_area_code"),
	"number" => array("xpath" => "DISS_phone_num"),
	"extension" => array("xpath" => "DISS_phone_ext"),
	));
    parent::__construct($xml, $config, $xpath);
  }

  public function set($number) {
    $number = trim($number);
    if ($number == "") return;	// no value - don't attempt to parse
    // this should work for most US phone numbers and possibly some international ones 
    if (preg_match("/^((\+\d{1,3}[- ]?\(?\d\)?[- ]?\d{1,5})|\(?(\d{2,6})\)?)[- ]?(\d{3,4})[- ]?(\d{4})( x| ext)?(\d{1,5})?$/", $number,  $matches)) {
      // parse number into parts and save in the correct fields
      $this->country_code = $matches[2];
      $this->area_code = $matches[3];
      $this->number = $matches[4] . "-" . $matches[5];
      if (isset($matches[7])) $this->extension = $matches[7];
    } else {
      trigger_error("Cannot parse phone number $number", E_USER_WARNING);
      // pass along to ProQuest as is (?)
      $this->number =  $number;
    }
  }
}

class DISS_description extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "page_count" => array("xpath" => "@page_count"),
	"external_id" => array("xpath" => "@external_id"),
	"copyright" => array("xpath" => "@apply_for_copyright"),
	"type" => array("xpath" => "@type"),	// masters thesis or doctoral dissertation
	"title" => array("xpath" => "DISS_title"),
	"date_completed" => array("xpath" => "DISS_dates/DISS_comp_date"),
	"date_accepted" => array("xpath" => "DISS_dates/DISS_accept_date"),
	"degree" => array("xpath" => "DISS_degree"),
	// for institutional contact PQ wants department name
	"department" => array("xpath" => "DISS_institution/DISS_inst_contact"),
	"advisor" => array("xpath" => "DISS_advisor/DISS_name", "class_name" => "DISS_name",
			   "is_series" => true),
	"committee" => array("xpath" => "DISS_cmte_member/DISS_name", "class_name" => "DISS_name",
			     "is_series" => true),
	"categories" => array("xpath" => "DISS_categorization/DISS_category",
			      "class_name" => "DISS_category", "is_series" => true),
	"keywords" => array("xpath" => "DISS_categorization/DISS_keyword", "is_series" => true),
	"language" => array("xpath" => "DISS_categorization/DISS_language"),
	
	));
    parent::__construct($xml, $config, $xpath);
  }

  public function __set($name, $value) {
    switch ($name) {
    case "degree":
      // FIXME: create a degree object like countries and lookup that way?
      // would be better to have these values in a config file than in code...
      // translate our degrees to PQ desired format
      $degrees = new degrees();
      $value = $degrees->codeByAbbreviation($value);
      break;
    case "language":
      $langlist = new languages();	
      $value = $langlist->codeByName($value);	// translate into PQ 2-letter language code
      break;
      
    }
    return parent::__set($name, $value);
  }

  // add a new Advisor node and optionally initialize values from a mods_name instance
  public function addAdvisor(mods_name $name = null) {
    // deep clone first committee member
    // note: using parentNode to clone DISS_advisor node; object mapping is on DISS_cmte_member/DISS_name
    $newnode = $this->map["advisor"][0]->domnode->parentNode->cloneNode(true);	
    // insert before DISS_committee 
    $newnode = $this->domnode->insertBefore($newnode, $this->map["committee"][0]->domnode->parentNode);

    $this->update();

    // if a mods_name was passed in, initialize the newly added node
    if (!is_null($name)) {
      $this->advisor[count($this->advisor) - 1]->set($name);
    }
    
  }

  // add a new committee member node and optionally initialize values from a mods_name instance
  public function addCommitteeMember(mods_name $name = null) {
    // deep clone first committee member
    // note: using parentNode to clone DISS_cmte_member node; object mapping is on DISS_cmte_member/DISS_name
    $newnode = $this->map["committee"][0]->domnode->parentNode->cloneNode(true);	
    // insert before DISS_categorization 
    $newnode = $this->domnode->insertBefore($newnode, $this->map["categories"][0]->domnode->parentNode);

    $this->update();

    // if a mods_name was passed in, initialize the newly added node
    if (!is_null($name)) {
      $this->committee[count($this->committee) - 1]->set($name);
    }
  }

  public function addCategory(mods_subject $category = null) {
    // deep clone first committee member
    $newnode = $this->map["categories"][0]->domnode->cloneNode(true);
    // insert before first DISS_keyword
    $nodeList = $this->xpath->query("//DISS_keyword");
    // if a context node was found, insert the new node before it
    if ($nodeList->length) {
      $contextnode = $nodeList->item(0);
      $newnode = $this->map["categories"][0]->domnode->parentNode->insertBefore($newnode, $contextnode);
    } else {
      // otherwise, append inside DISS_categorization (shouldn't happen - keyword should always be present)
      $newnode = $this->map["categories"][0]->domnode->parentNode->appendChild($newnode);
    }

    $this->update();

    // if a mods subject was passed in, initialize the newly added node
    if (!is_null($category)) {
      $this->categories[count($this->categories) - 1]->set($category);
    }
  }
}

class DISS_category extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "code" => array("xpath" => "DISS_cat_code"),
	"text" => array("xpath" => "DISS_cat_desc"),
	));
    parent::__construct($xml, $config, $xpath);
  }

  public function set(mods_subject $category) {
    $this->code = $category->id;
    $this->text = $category->topic;
  }
}


class DISS_abstract extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "p" => array("xpath" => "DISS_para", "is_series" => true),
	));
    parent::__construct($xml, $config, $xpath);
  }



  /**
   * takes html text and parses it into one or more DISS_para tags
   */
  public function set($html) {
    /**
     ProQuest online submission form allows minimal html formatting:
	     -   strong, em, sub, sup
     Mike Visser said: use basic html formatting and they will convert to their own SGML format
    */

    // note: most of this cleaning should be shifted to the etd_html class
    $tidy = new tidy();
    $config = array(
	   'bare'	    => true,		//strip MS html, convert non-breaking spaces to regular spaces
           'clean'         => true,		// strip out / convert unneeded tags
	   'drop-empty-paras' => true,
	   'drop-font-tags'   => true,
	   'drop-proprietary-attributes' => true,
	   'output-xml'		=> true,
	   'preserve-entities'  => true,	// not supported (version of tidy?)
	   'ascii-chars'	=> true,
	   'char-encoding'	=> 'utf8',
	   'logical-emphasis'  => true,		// convert i to em and b to strong
	   
	   );
    $tidy->parseString($html, $config, 'utf8');
    $tidy->cleanRepair();
    $body = tidy_get_body($tidy);	// should be able to do $tidy->body but it doesn't work

    $text = $body->value;
    // strip out any tags that are not allowed
    $text = strip_tags($text, "<p><div><strong><em><sub><sup><body>");
    						// leave body tag-- it is the parent tag that holds all the others

    // NOTE: ignoring <br/> tags; currently not handling <p>s inside <divs>
    
    $dom = new DOMDocument();
    $dom->loadXML($text);
    for ($i = 0; $i < $dom->documentElement->childNodes->length; $i++) {
      $node = $dom->documentElement->childNodes->item($i);
      if ($node instanceof DOMText) {
	if (trim($node->data) == "") continue;		// blank text node
	$this->appendToCurrentParagraph($node);
      } else {
	switch ($node->tagName) {
	  // effectively converting divs and ps to DISS_para
	case "div":
	case "p":
	  $this->addAsParagraph($node);
	  break;
	default:
	  // any other node - append to current paragraph
	  $this->appendToCurrentParagraph($node);
	}
      }
    }

    $this->update();
  }


  // add all the contents of a p or div node to the next DISS_para element
  public function addAsParagraph(DOMNode $node) {
    if ($this->p[0] == "") {		// still on the first paragraph
      // do nothing - this is the current paragraph
    } else {
      $this->p->append('');	// create a new empty paragraph node to append to
    }

    foreach ($node->childNodes as $n) 
      $this->appendToCurrentParagraph($n);
  }

  // add a node or text to the current (last) DISS_para
  public function appendToCurrentParagraph($content) {
    // add the content to the last paragraph
    $current_node = $this->domnode->childNodes->item($this->domnode->childNodes->length - 1);
    if ($content instanceof DOMText) {
      $current_node->appendChild($this->dom->createTextNode($content->data));
    } elseif ($content instanceof DOMElement) {
      $node = $this->dom->importNode($content, true);
      $current_node->appendChild($node);
    }
  }

}

class DISS_attachment extends XmlObject {
    public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "filename" => array("xpath" => "DISS_file_name"),
	"description" => array("xpath" => "DISS_file_descr"),
	));
    parent::__construct($xml, $config, $xpath);
  }
}
