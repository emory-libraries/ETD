<?php

require_once("etd.php");

class Etd_Feed {

  private $zfeed;
  
  public function __construct($title, $url, $etds) {
    $builder = new Etd_Feed_Builder($title, $url, $etds);
    $this->zfeed = Zend_Feed::importBuilder($builder, 'rss');
  }

  public function saveXml() { return $this->zfeed->saveXml(); }
  
}



class Etd_Feed_Builder implements Zend_Feed_Builder_Interface {

  protected $header;
  protected $entries;
  
  public function __construct($title, $link, array $etds) {
    $this->header = new Zend_Feed_Builder_Header($title, $link);


    $front  = Zend_Controller_Front::getInstance();
    $request = $front->getRequest();
    $baseurl = ($request->getServer("HTTPS") == "") ? "http://" : "https://";
    $baseurl .=  $request->getServer("SERVER_NAME") . $request->getBaseUrl() .
      "/view/record/pid/";	// FIXME: use a helper somehow?


    $this->entries = array();
    foreach ($etds as $etd) { 
      $link = $baseurl . $etd->pid;
      $entry = new Zend_Feed_Builder_Entry($etd->label,			// plain-text title
					   $link,			// link within site
					   $etd->dc->description	// brief non-html description
					   );
      $entry->guid = $etd->mods->ark;	// globally unique identifier (ark)	no way to set isPermaLink="true" ?
      $entry->setLastUpdate(strtotime($etd->mods->originInfo->issued));
      $entry->author = $etd->mods->author->full;	// adds but doesn't output

      // add program, research fields, and keywords as categories (appropriate/useful?)
      $entry->addCategory(array("term" => $etd->program(),
				"scheme" => "Program"));
      foreach (array_merge($etd->mods->researchfields, $etd->mods->keywords) as $subject) {
	$entry->addCategory(array("term" => $subject->topic,
				  "scheme" => $subject->authority));
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