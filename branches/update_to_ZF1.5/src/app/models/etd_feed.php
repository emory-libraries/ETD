<?php

require_once("etd.php");

class Etd_Feed {

  private $zfeed;
  
  public function __construct($title, $url, $etds, $description = null) {
    $builder = new Etd_Feed_Builder($title, $url, $etds, $description);
    $this->zfeed = Zend_Feed::importBuilder($builder, 'rss');
  }

  public function saveXml() { return $this->zfeed->saveXml(); }
  
}



class Etd_Feed_Builder implements Zend_Feed_Builder_Interface {

  protected $header;
  protected $entries;
  
  public function __construct($title, $link, array $etds, $description = null) {
    $this->header = new Zend_Feed_Builder_Header($title, $link);
    if ($description) $this->header->description = $description;
      

    $front  = Zend_Controller_Front::getInstance();
    $request = $front->getRequest();
    $baseurl = ($request->getServer("HTTPS") == "") ? "http://" : "https://";
    $baseurl .=  $request->getServer("SERVER_NAME") . $request->getBaseUrl() .
      "/view/record/pid/";	// FIXME: use a helper somehow?


    $this->entries = array();
    foreach ($etds as $etd) { 
      $link = $baseurl . $etd->pid();
      $entry = new Zend_Feed_Builder_Entry($etd->title(),			// plain-text title
					   $link,			// link within site
					   $etd->_abstract()	// brief non-html description
					   );
      $entry->guid = $etd->ark;		// globally unique identifier (ark)	no way to set isPermaLink="true" ?
      $entry->setLastUpdate(strtotime($etd->dateIssued));
      $entry->author = $etd->author();	// adds but doesn't output

      // add program, research fields, and keywords as categories (appropriate/useful?)
      if ($etd->program())
	$entry->addCategory(array("term" => $etd->program(),
				  "scheme" => "Program"));
      foreach ($etd->researchfields() as $subject) {
	$entry->addCategory(array("term" => $subject,
				  "scheme" => "ProQuest Research Field"));
      }
      foreach ($etd->keywords() as $subject) {
	$entry->addCategory(array("term" => $subject,
				  "scheme" => "keyword"));
      }

      // add pdf ark as an enclosure ? (if available) -- needs url, length in bytes, and mime type
      
      // Zend_Atom_Feed doesn't output author?
      $this->entries[] = $entry;
    }
  }
  
  // return Zend_Feed_Builder_Header
  public function getHeader() {
    return $this->header;
  }
  
  // return array of Zend_Feed_Builder_Entry instances
  public function getEntries() {
    return $this->entries;
  }
  
}