<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxml.php");
require_once("foxmlDatastreamAbstract.php");

/**
 * Base class to handle non-xml Fedora object datastreams.
 *
 * Extend to customize datastream profile settings, e.g.:
 * <code>
 * class ImageDatastream extends fileDatastream {
 *   public $label = 'image';
 *   public $mimetype = 'image/jpeg';
 * }
 * class ImageFoxml extends foxml {
 * ...
 *   protected function configure() {
 *     parent::configure();
 *     $this->xmlconfig["img"] = array("xpath" => "foxml:datastream[@ID='IMAGE']",
 *           "class_name" => "ImageDatastream", "dsID" => 'IMAGE');
 *   }
 * }
 * </code>
 */
class fileDatastream extends foxmlDatastreamAbstract {

  public $label;
    public $control_group = FedoraConnection::MANAGED_DATASTREAM;
    public $state = FedoraConnection::STATE_ACTIVE;
    public $versionable = true;
    public $mimetype;

    public $filename = null;
    private $_filename;

  public function __construct($filename = null) {
        // calculating checksum before setting any init filename
        // - new file should be considered a change
        $this->calculateChecksum();

        if (! is_null($filename)) {
            $this->filename = $filename;
        }
        $this->_filename = $this->filename;
  }

  /**
   * datastream content in a form appropriate for upload to fedora for ingest/update
   * @return string filename
   */
    public function content_for_upload() {
        return $this->filename;
    }

   /**
    * Does this object have content to be uploaded/ingested?
    * For a file datastream, returns true if filename has been set.
    * @return boolean
    */
    public function has_content_for_ingest() {
        return isset($this->filename);
    }

    /**
     * Mark the datastream as unchanged (e.g., has just been saved).
     */
    public function markUnchanged() {
        // FIXME: should this be unset ?
        $this->_filename = $this->filename;
        parent::markUnchanged();
    }

    // TODO: will probably want to add methods for:
    // - updating file (set filename and calculate checksum)

    /**
     * get the contents of this datastream from fedora 
     * uses REST api in order to support large datastreams
     * @return string|binary
     */
    public function content() {
        if (isset($this->_obj)) {
            return $this->_obj->fedora->getDatastreamREST($this->_obj->pid, $this->dsid);
        }
    }

    /**
     * override parent content checksum for file specific logic
     * @return string
     */
    protected function dscontent_checksum() {
        return $this->filename;
    }

}
