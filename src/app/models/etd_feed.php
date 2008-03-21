<?php

require_once("etd.php");

class Etd_Feed {

  private $zfeed;
  
  public function __construct($title, $url, $etds) {
    $builder = new Etd_Feed_Builder($title, $url, $etds);
    $this->zfeed = Zend_Feed::importBuilder($builder);
  }

  public function saveXml() { return $this->zfeed->saveXml(); }
  
}



class Etd_Feed_Builder implements Zend_Feed_Builder_Interface {

  protected $header;
  protected $entries;
  
  public function __construct($title, $link, array $etds) {
    $this->header = new Zend_Feed_Builder_Header($title, $link);
    
    $this->entries = array();
    foreach ($etds as $etd) {
      $entry = new Zend_Feed_Builder_Entry($etd->label,		// title
					   $etd->mods->ark,		// link
					   $etd->dc->description	// brief non-html description
					   );
      $entry->setLastUpdate(strtotime($etd->mods->originInfo->issued));
      //       $entry->author->name = $etd->mods->author->full;	// adds but doesn't output
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