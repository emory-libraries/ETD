<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxmlDatastreamInterface.php");

/**
 * common functionality that should be useful for all foxml datastreams
 *
 * @TODO this class should be split into actual abstract datastream logic
 * and xml-specific datastream logic (it currently is a mixture of both)
 */
abstract class foxmlDatastreamAbstract extends XmlObject implements foxmlDatastreamInterface {

  /**
   * internal checksum, used to determine if datastream contents have changed
   * @var string
   */
  protected $_checksum;

  /**
   * datastream label
   * @var string
   */
  public $dslabel;
  /**
   * datastream control group
   * @var string
   */
  public $control_group;
  /**
   * datastream state
   * @var state
   */
  public $state;
  /**
   * whether datastream should be versioned in fedora
   * @var bool
   */
  public $versionable;
  /**
   * datastream mimetype
   * @var string
   */
  public $mimetype;
  public $format_uri;
  public $checksum_type;
  public $checksum;
  /**
   * last modification time in fedora (should not be set by user)
   * @var string
   */
  public $last_modified;
  /**
   * datastream id from fedora (should not be modified by user)
   * @var string
   */
  public $dsid;
  /**
   * datastream size from fedora (should not be modified by user)
   * @var string
   */
  public $size;

  /**
   * reference to the object this datastream belongs to, when initialized via foxml class
   * @var foxml
   */
  protected $_obj;

  // pass on all values to XmlObject constructor, but save the checksum for initial xml
  public function __construct($xml, $config) {
    parent::__construct($xml, $config);

    $this->calculateChecksum();
  }

  /**
   * Save the checksum for current datastream contents & properties,
   * in order to determine if either have been modified and need to be saved.
   */
  public function calculateChecksum() {
    $this->_checksum = $this->getChecksum();
  }

  /**
   * calculate and return a checksum for the current datastream
   * (both datastream contents & datastream properties)
   * @return string
   */
  public function getChecksum() {
    return $this->dsinfo_checksum() . $this->dscontent_checksum();
  }

  /**
   * Check if the datastream has changed and should be saved to Fedora.
   * Should return true if either the datastream contents OR any datastream
   * properties that can be updated on a modify datastream API call have been
   * modified.
   *
   * @return boolean
   */
  public function hasChanged() {
    return ($this->_checksum != $this->getChecksum());
  }

  /**
   * Mark the current version of the datastream as unchanged (e.g.,
   * after a modified datastream has been successfully updated in Fedora).
   */
  public function markUnchanged() {
      // calculate checksum for this point of comparison
      $this->calculateChecksum();
  }

  /**
   * update the xml for the entire dom
   * @param string $xml
   */
  public function updateXML($xml) {
    $newdom = new DOMDocument();
    $newdom->loadXML($xml);

    // import new xml into this dom
    $newdomnode = $this->dom->importNode($newdom->documentElement, true); // deep import - include all child nodes
    $this->domnode->parentNode->replaceChild($newdomnode, $this->domnode);

    // point domnode for this datastream at the new domnode
    $this->domnode = $newdomnode;

    //update all the xml/object mappings so in-memory map reflects new xml
    $this->update();
  }

  /**
   * datastream content in a form appropriate for upload to fedora for ingest/update
   * @return string
   */
  public function content_for_upload() {
      return $this->saveXML();
  }

  /**
   * currently, all xml datastreams start with a fixture - assuming true for now
   * @return boolean
   */
  public function has_content_for_ingest() {
      return true;
  }

  /**
   * initialize datastream profile information from fedora
   * @param Datastream $info - result of a FedoraConnection::getDatastreamInfo call
   * @param foxml $obj - foxml object this datastream belongs to
   */
  public function setDatastreamInfo($info, $obj) {
      // check if the datastream has changed before we update ds info
      $has_changed = $this->hasChanged();

      $this->dslabel = $info->label;
      $this->control_group = $info->controlGroup;
      $this->mimetype = $info->MIMEType;
      $this->state = $info->state;
      $this->versionable = $info->versionable;
      $this->checksum = $info->checksum;
      $this->checksum_type = $info->checksumType;
      $this->format_uri = $info->formatURI;
      // the create date of this version of the datastream is last modified for datastream as a whole
      $this->last_modified = $info->createDate;
      // datastream id
      $this->dsid = $info->ID;
      $this->size = $info->size;
      // reference to the object this datastream belongs to
      $this->_obj = $obj;
      /* currently not mapped:
         - versionID
         - altIDs
         - location
       */

      // if previously unchanged, mark as unchanged based on the newly set dsinfo
      if (! $has_changed) {
          $this->markUnchanged();
      }
  }

  /**
   * calculate a checksum of all datastream properties that can be updated
   * when the datastream is saved to fedora in order to detect changes
   * @return string
   */
  protected function dsinfo_checksum() {
      return md5(implode(' ', array($this->dslabel, $this->control_group,
            $this->mimetype, $this->checksum, $this->checksum_type)));
  }
  /**
   * calculate a checksum of datastream content to track when it has changed
   * @return string
   */
  protected function dscontent_checksum() {
      return md5($this->saveXML());
  }

}
