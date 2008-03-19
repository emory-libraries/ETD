<?php

require_once("xml-utilities/XmlObject.class.php");

class ProQuestSubmission extends XmlObject {
  
  protected $schema = "";

  protected $xmlconfig;

  private $etd;
  
  public function __construct($dom = null) {
    if (is_null($dom)) {	// by default, initialize from template xml with Emory defaults
      $xml = file_get_contents("PQ_Submission.xml", FILE_USE_INCLUDE_PATH); 
      $dom = new DOMDocument();
      $dom->loadXML($xml);
    }
    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($dom, $config);
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
    switch ($this->etd->mods->embargo) {
    case "0 days":
      $this->embargo_code = 0; break;
    case "6 months":
      $this->embargo_code = 1; break;
    case "2 years":
    case "6 years":
      $this->embargo_code = 2; break;		// PQ only offers 2 year embargoes
    default:
      // don't set code - will get caught at validation  time
    }

    // author information
    $this->author_info->name->set_from_modsName($this->etd->mods->author);
    // 	set current and permanent contact information
    if (isset($this->etd->authorInfo->mads->current))
      $this->author_info->current_contact->set_from_madsAffiliation($this->etd->authorInfo->mads->current);
    if (isset($this->etd->authorInfo->mads->permanent))
      $this->author_info->permanent_contact->set_from_madsAffiliation($this->etd->authorInfo->mads->permanent);


    // description
    $this->description->page_count = $this->etd->mods->pages;
    $this->description->external_id = $this->etd->pid;
    if ($this->etd->mods->genre == "Dissertation")
      $this->description->type = "doctoral";
    elseif ($this->etd->mods->genre == "Masters Thesis")
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
    $this->description->advisor->set_from_modsName($this->etd->mods->advisor);
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
    $this->setAbstract();
    $this->setFiles(); 

  }


  public function setCommittee() {
    $committee = array_merge($this->etd->mods->committee, $this->etd->mods->nonemory_committee);
    for ($i = 0; $i < count($committee); $i++) {
      if (isset($this->description->committee[$i]))
	$this->description->committee[$i]->set_from_modsName($committee[$i]);
      else
	$this->description->addComitteeMember($committee[$i]);
    }
  }

  public function setCategories() {
    $fields = $this->etd->mods->researchfields;
    for ($i = 0; $i < count($fields); $i++) {
      if (isset($this->description->categories[$i]))
	$this->description->categories[$i]->set_from_modsSubject($fields[$i]);
      else
	$this->description->addCategory($fields[$i]);
    }
  }


  
  public function setAbstract() {
    /**
     ProQuest online submission form allows minimal html formatting:
	     -   strong, em, sub, sup
     Mike Visser says: use basic html formatting and they will convert to their own SGML format
    */

    // split $this->etd->html->abstract on divs and/or paragraphs, put into diss_paras
    // ? how to do this? import node from etd_html ? may need cleaning...
  }

  public function setFiles() {
    for ($i = 0; $i < count($this->etd->pdfs); $i++) {
      if (isset($this->pdfs[$i])) $this->pdfs[$i] = $this->etd->pdfs[$i]->prettyFilename();
      else
	$this->pdfs->append($this->etd->pdfs[$i]->prettyFilename());
    }

    for ($i = 0; $i < count($this->etd->supplements); $i++) {
      if (isset($this->supplements[$i])) {
	$this->supplements[$i]->filename = $this->etd->supplements[$i]->prettyFilename();
	$this->supplements[$i]->description = $this->etd->supplements[$i]->dc->description;
      } else {
	$this->addSupplement($this->etd->supplements[$i]->prettyFilename(),
			     $this->etd->supplements[$i]->dc->description);
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
	"middle" => array("xpath" => "DISS_mdidle"),
	"affiliation" => array("xpath" => "DISS_affiliation"),
	));
    parent::__construct($xml, $config, $xpath);
  }
  
  public function set_from_modsName(mods_name $name) {
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

  public function set_from_madsAffiliation(mads_affiliation $mads) {
    $this->date =  $mads->date;		// FIXME: date format?
    $this->email = $mads->email;
    if (isset($mads->address)) $this->address->set_from_madsAddress($mads->address);
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
  
  public function set_from_madsAddress(mads_address $address) {
    for ($i = 0; $i < isset($address->street[$i]); $i++) {
      if (isset($this->street[$i])) $this->street[$i] = $address->street[$i];
      else $this->street->append($this->street[$i]);
    }
    $this->city = $address->city;
    $this->state = $address->state;
    $this->zipcode = $address->postcode;
    $this->country = $address->country;		// FIXME: need to translate to two-letter code? 
  }
}

class DISS_phone extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "country_code" => array("xpath" => "DISS_country_cd"),
	"area_code" => array("xpath" => "DISS_area_code"),
	"number" => array("xpath" => "DISS_phone_num"),
	"extension" => array("xpath" => "DISS_phone_ext"),
	));
    parent::__construct($xml, $config, $xpath);
  }

  public function set($number) {
    // parse number into parts and store
    $this->number = $number;
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
	//	"institution" => array("xpath" => "DISS_institution", "class_name" => "DISS_institution"),
	"advisor" => array("xpath" => "DISS_advisor/DISS_name", "class_name" => "DISS_name"),
	"committee" => array("xpath" => "DISS_cmte_member/DISS_name", "class_name" => "DISS_name",
			     "is_series" => true),
	"categories" => array("xpath" => "DISS_categorization/DISS_category",
			      "class_name" => "DISS_category", "is_series" => true),
	"keywords" => array("xpath" => "DISS_categorization/DISS_keyword", "is_series" => true),
	"language" => array("xpath" => "DISS_categorization/DISS_language"),
	
	));
    parent::__construct($xml, $config, $xpath);
  }

  // need functions: addCommitteeMember, addCategory

  // add a new committee member node and optionally initialize values from a mods_name instance
  public function addCommitteeMember(mods_name $name = null) {
    // deep clone first committee member
    // note: using parentNode to clone DISS_cmte_member node; object mapping is on DISS_cmte_member/DISS_name
    $newnode = $this->map["committee"][0]->domnode->parentNode->cloneNode(true);	
    // insert before DISS_categorization 
    $newnode = $this->domnode->insertBefore($newnode, $this->map["categories"]->domnode->parentNode);

    $this->update();

    // if a mods_name was passed in, initialize the newly added node
    if (!is_null($name)) {
      $this->committee[count($this->committee) - 1]->set_from_modsName($name);
    }
  }

  public function addCategory(mods_subject $category = null) {
    // deep clone first committee member
    $newnode = $this->map["categories"][0]->domnode->cloneNode(true);
    // insert before first DISS_keyword
    $newnode = $this->domnode->insertBefore($newnode, $this->map["keywords"][0]->domnode);

    $this->update();

    // if a mods subject was passed in, initialize the newly added node
    if (!is_null($category)) {
      $this->categories[count($this->categories) - 1]->set_from_modsSubject($category);
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

  public function set_from_modsSubject(mods_subject $category) {
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
