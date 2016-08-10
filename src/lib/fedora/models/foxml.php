<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("api/FedoraConnection.php");
require_once("dublin_core.php");
require_once("rels_ext.php");

/**
 * Foxml class (fedora object) with dublin core + rels-ext datastreams
 * Can create a new foxml object from template or initialize an
 * existing object from Fedora; saves objects to fedora (ingest and
 * update xml datastreams), and can initialize xml datastreams and related
 * foxml objects dynamically.
 */

class FoxmlException extends Exception {}
class FoxmlBadContentModel extends Exception {}

/**
 * magic variables
 * @property string $version foxml version (1.0 or 1.1)
 * @property string $pid object pid
 * @property sting $label top-level object label
 * @property string $owner owner of the object
 * @property dublin_core $dc Dublin Core datastream
 * @property rels_ext $rels_ext RELS-EXT datastream
 * @property array of foxmlDatastream $ds access to foxmlDatastream properties for all datastreams
 *
 * @method string viewObjectProfile() object profile in html, from default Fedora object methods
 * @method string viewMethodIndex() method index in html, from default Fedora object methods
 * @method string viewItemIndex() item index in html, from default Fedora object methods
 * @method string viewDublinCore() dublin core in html, from default Fedora object methods
 *
 * @TODO This class currently extends XmlObject, and the supported DOM init mode
 * is used primarily for testing.  After the most recent refactoring to improve
 * efficiency, this class does not need to extend XmlObject at all, but should
 * perhaps use it internally where appropriate.
 */
class foxml extends XmlObject {

  /**
   * Schema to use for validation - foxml 1.1
   * canonical copy is here: http://www.fedora.info/definitions/1/0/foxml1-1.xsd
   * using local copy
   * @var string
   */
  protected $schema = "https://larson.library.emory.edu/schemas/foxml1-1.xsd";


  /**
   * array of foxmlIngestStream objects - used to add binary datastreams to foxml template for new objects
   * @var array
   */
  protected $datastreams;

  /**
   * array of XmlObjectProperty used to configure XmlObject - also used to load datastreams
   * @var array
   */
  protected $xmlconfig;

  /**
   * array of datastream information from Fedora
   * should be accessed as fedora_streams for lazy-init
   * @var array
   */
  protected $_fedora_streams = null;

  /**
   * method definitions returned from FedoraConnection listMethods
   * should be accessed as methodDefs for lazy-init
   * @var array of ObjectMethodsDef
   */
  protected $_methodDefs = null;


  /**
   * array of fedora methods: method name => service definition pid
   * @var array
   */
  protected $fedora_methods;


  /**
   * foxml namespace
   */
  const xml_namespace = "info:fedora/fedora-system:def/foxml#";

  /**
   * fedora connection object
   * @var FedoraConnection
   */
  private $fedora;

  /**
   * how this object was initialized - by pid (from Fedora) or empty (new from template)
   * @var string
   */
  protected $init_mode;



  /**
   * related foxml objects - configured in relconfig and found using RIsearch
   */
  protected $related_objects;

  /**
   * configuration for related fedora objects - used in findRelatedObjects function
   *
   * This example creates a relation pdfs (accessed as $this->pdfs) where the
   * relation is "hasPDF"; an array of etd_file objects will be
   * created and sorted using the sort_etdfiles function
   * <code>
   *  $this->relconfig["pdfs"] = array("relation" => "hasPDF",
   *		"is_series" => true, "class_name" => "etd_file",
   * 		"sort" => "sort_etdfiles");
   *
   * </code>
   * @var array
   */
  protected $relconfig;

  /**
   * checksum of top-level information
   * (will be updated in Fedora when changed and object is saved)
   * @var string
   */
  protected $infoChecksum;

  /**
   * should setting the label cascade to dc:title? default to true
   * (override this value when extending if different behavior is needed)
   * @var boolean
   */
  protected $label_is_dctitle = true;


  /**
   * state code to update object state on next save
   * set via setObjectState function
   * @var string
   */
  protected $next_object_state = null;

  /**
   * list of configured datastreams when creating a new foxml object from scratch
   * @var array
   */
  private $_ingest_datastreams = array();

  /** top-level object variables for lazy-init **/
  protected $_pid;
  protected $_label = null;
  protected $_owner = null;
  protected $_last_modified = null;
  protected $_state = null;

  protected $_info_changed = false;

  /**
   * internal flag to indicate whether object has been ingested into fedora
   * used for lazy-init api calls that expect an ingested object
   * @var boolean
   */
  protected $_is_ingested = null;

  /**
   * Create a foxml object in one of two ways:
   * If a pid is specified,  object information and datastreams
   * will be retrieved from Fedora.
   * Otherwise, a blank foxml object is created from a template based
   * on minimal foxml + templates from datastream objects.
   *
   * @param string $arg fedora pid (optional)
   */
  public function __construct($arg = null) {
    $this->fedora_methods = array();

    if (Zend_Registry::isRegistered("fedora"))
      $this->fedora = Zend_Registry::get('fedora');
    else trigger_error("FedoraConnection not found in Zend Registry", E_USER_WARNING);

    $this->related_objects = array();	     // private copy of related objects, once initialized

    $this->configure();

    if (is_null($arg)) {
      $dom = $this->construct_by_template();
      $this->init_mode = "template";
      $this->_is_ingested = false;
    } elseif ($arg instanceof DOMDocument) {
      // pass arg to parent constructor as is
      $dom = $arg;
      $this->init_mode = "dom";
      $this->_fedora_streams = array();
      $this->_is_ingested = false;
    } elseif (preg_match("|(info:fedora/)?.+:.+|", $arg)) {	// fedora pid
      $this->init_mode = "pid";
      $this->_pid = $arg;
      $dom = $this->construct_by_pid($arg);
      $this->_is_ingested = true;
    } else {
      throw new FoxmlException("Bad argument; constructor expects any of null, DOMDocument, or fedora pid");
    }

    $this->addNamespace("foxml", foxml::xml_namespace);

    $config = $this->config($this->xmlconfig);
    parent::__construct($dom, $config);

    if ($this->init_mode == 'template') {
       // initialize datastreams and store a list of them so we can ingest them correctly
      foreach ($this->xmlconfig as $name => $ds) {
        if (isset($ds["dsID"])) {
            $this->map{$name} = new $this->xmlconfig[$name]['class_name']();
            $this->_ingest_datastreams[] = $name;
        }
      }
    } elseif ($this->init_mode == 'dom') {
        // set internal object-profile variables from dom content
        if (isset($this->xml_pid)) $this->_pid = $this->xml_pid;
        if (isset($this->xml_label)) $this->_label = $this->xml_label;
        if (isset($this->xml_owner)) $this->_owner = $this->xml_owner;
    }

  }

  /**
   * initialize the object as a blank foxml via template
   * @return DOMDocument to be used for XmlObject initialization
   */
  private function construct_by_template() {
    $dom = new DOMDocument();
    $dom->loadXML($this->getTemplate());
    return $dom;
    $this->_fedora_streams = array();
  }

  /**
   * initialize the object as a blank foxml without datastream templates
   * @return DOMDocument to be used for XmlObject initialization
   */
  private function construct_by_pid() {
    $dom = new DOMDocument();
    $dom->loadXML($this->getTemplate());
    return $dom;
  }

  protected function _get_fedora_streams() {
      // if datastream list has not yet been set, retrieve it
      if ($this->_fedora_streams == null && $this->_is_ingested) {
          try {
            $this->_fedora_streams = $this->fedora->listDatastreams($this->pid);
          } catch (FedoraException $f) {
              // if there is an error, store empty result so we don't attempt to retrieve again
              $this->_fedora_streams = array();
          }
      }
      return $this->_fedora_streams;
  }

  protected function _get_methodDefs() {
      // if datastream list has not yet been set, retrieve it
      if ($this->_methodDefs == null && $this->_is_ingested) {
          try {
            $this->_methodDefs = $this->fedora->listMethods($this->pid);
          } catch (FedoraException $f) {
              // if there is an error, store empty result so we don't attempt to retrieve again
              $this->_methodDefs = array();
          }
      }
      return $this->_methodDefs;
  }

  /** lazy-initialization for object profile information **/
  protected function load_object_info() {
    if ($this->_is_ingested) {
        $info = $this->fedora->getObjectProfile($this->pid);
        if ($this->_label == null) $this->_label = $info->objLabel;
        // store date-time from last record modification
        if ($this->_last_modified == null) $this->_last_modified = $info->objLastModDate;
        // objCreateDate is also accessible - useful?
    }
  }

  /**
   * Common logic for accessing object info - retrieve from fedora if not yet set
   * @param string $field
   * @return string
   */
  protected function _get_object_info($field) {
      if ($this->$field == null) $this->load_object_info();
      return $this->$field;
  }
  /**
   * Common logic for updating object info - marks info as changed for object save
   * @param string $field
   * * @param string $value new value
   */
  protected function _set_object_info($field, $value) {
      if ($value != $this->$field) $this->_info_changed = true;
      $this->$field = $value;
  }

  protected function _get_label() { return $this->_get_object_info('_label'); }
  protected function _set_label($val) {
      $this->_set_object_info('_label', $val);
      // additional, optional behavior - label may cascade to DC title
      if ($this->label_is_dctitle && $this->isDatastreamLoaded("dc")) {
        $this->dc->title = $val;
      }
  }
  protected function _get_last_modified() { return $this->_get_object_info('_last_modified'); }
  protected function _get_owner() {
      // because owner is not included in SOAP API getObjectProfile, must be retrieved separately
      // TODO: use REST API for object profile information so we don't have to use this work-around
      if ($this->_owner == null && $this->_is_ingested) {
          $this->_owner = $this->fedora->getOwner($this->pid);
      }
      return $this->_owner;
  }
  protected function _set_owner($val) { return $this->_set_object_info('_owner', $val); }

  protected function _get_pid() { return $this->_pid; }
  protected function _set_pid($val) {
      $this->_pid = $val;
      $this->xml_pid = $val;
      // pid should also be stored in dc:identifier and the "about" attribute in rels-ext
      if ($this->isDatastreamLoaded("dc")) {
      if (!isset($this->dc->identifier)) {
          $this->dc->identifier = $val;
        }
      }
      if (isset($this->rels_ext) && isset($this->rels_ext->about))
    $this->rels_ext->about = $this->fedora->risearch->pid_to_risearchpid($val);

      // special datastream: if there is a policy, set pid for what resource it applies to
      if (isset($this->policy) && $this->policy) {
    $this->policy->pid = $val;
    $this->policy->policyid = str_replace(":", "-", $val);
      }
  }

  /**
   * check if the top-level record information has changed
   * @return boolean
   */
  public function hasInfoChanged() {
    return $this->_info_changed;
  }

  /**
   * check if a datastream is present in Fedora, according to saved output of list datastreams api call
   * @param string $id datastream id
   * @return boolean
   */
  public function has_datastream($id) {
    // check datastreams fedora knows about
    if (is_array($this->fedora_streams)) {
      // loop through datastreams and check IDs
      // 		FIXME: is there a more efficient way to do this?
      foreach ($this->fedora_streams as $datastream) {
  if ($datastream->ID == $id) return true;
      }
    } elseif ($this->fedora_streams) {	// single datastream ?
      return ($this->fedora_streams->ID == $id);
    }

    return false;
  }


  /**
   * return the label for a datastream by datastream id
   * @param string $id datastream id
   * @return string label or false if datastream not found
   *
   * @deprecated - access on datastream object label property instead
   */
  public function datastream_label($id) {
    if (is_array($this->fedora_streams)) {
      // loop through datastreams and check IDs (not very efficient)
      foreach ($this->fedora_streams as $datastream) {
        if ($datastream->ID == $id) return $datastream->label;
      }

      // single datastream
    } elseif ($this->fedora_streams && $this->fedora_streams->ID == $id) {
      return $this->fedora_streams->label;
    }

    return false;
  }

  /**
   * check if a datastream has been loaded from Fedora (when initializing by pid)
   * (added for unit tests of dynamic datastream loading)
   * @param string $name datastream local access name, as configured (not datastream id)
   * @return boolean
   */
  public function isDatastreamLoaded($name) {
    // FIXME: for now, assuming $name is a valid datastream
    return (isset($this->map{$name}));
  }


  /**
   * return the date a datastream was last modified
   * @param string $id datastream id
   * @return string date last modified
   *
   * @deprecated - access on datastream object last_modified property instead
   */
  public function lastModified($ds) {
    // loaded datastream now include datastream info - if available, use that
    if ($this->isDatastreamLoaded($ds)) return $this->$ds->created;

    // otherwise, request datastream information
    try {
      $info = $this->fedora->getDatastreamInfo($this->pid, $ds);
      /* NOTE: as far as I can tell, the "createDate" is actually the
         creation date of the *current* version of the datastream, so this
         is equivalent to the last modifiation time of a datastream.
      */
      return $info->createDate;
    } catch (Exception $e) {
      // don't choke on any un-authorized errors, etc.
      trigger_error("Error accessing datastream info for " . $this->pid . " " . $ds,
        E_USER_NOTICE);
    }

  }

  /**
   * define datastreams, declare namespaces, and configure xml object mappings
   * (this function is separate so it can be more easily extended)
   */
  protected function configure() {
    // NOTE: datastream names should match mappings in xmlconfig

    // dublin core (required for all foxml)
    //    $this->datastreams[] = "dc";
    $this->addNamespace("oai_dc", "http://www.openarchives.org/OAI/2.0/oai_dc/");

    // rels-ext
    //    $this->datastreams[] = "rels_ext";
    $this->addNamespace("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");

    $this->xmlconfig =  array(
      "version" => array("xpath" => "/foxml:digitalObject/@VERSION"),
      "xml_pid" => array("xpath" => "/foxml:digitalObject/@PID"),
      "xml_label" => array("xpath" => "//foxml:property[@NAME='info:fedora/fedora-system:def/model#label']/@VALUE"),
      "state" => array("xpath" => "//foxml:property[@NAME='info:fedora/fedora-system:def/model#state']/@VALUE"),
      "xml_owner" => array("xpath" => "//foxml:property[@NAME='info:fedora/fedora-system:def/model#ownerId']/@VALUE"),

      // specific datastream mappings
      // FIXME/TODO: does not need to be xmlconfig anymore ? (needed for dom init/test-mode...)
      "dc" => array("xpath" => "foxml:datastream[@ID='DC']/foxml:datastreamVersion/foxml:xmlContent/oai_dc:dc",
        "class_name" => "dublin_core", "dsID" => "DC"),
      "rels_ext" => array("xpath" =>
        "foxml:datastream[@ID='RELS-EXT']/foxml:datastreamVersion/foxml:xmlContent/rdf:RDF",
        "class_name" => "rels_ext", "dsID" => "RELS-EXT"),

      // generic access to properties for all datastreams
      // NOTE: only available when init from dom, not when initializing from template
      "ds" => array("xpath" => "foxml:datastream", "class_name" => "foxmlDatastream",
              "is_series" => true)



      );

    // config for related objects
    $this->relconfig =  array();
  }


  /**
   * generate foxml template
   * called from construct_by_pid to generate minimal foxml for top-level properties
   * @return string foxml template
   */
  public function getTemplate() {
    $template = '<foxml:digitalObject xmlns:foxml="info:fedora/fedora-system:def/foxml#" PID="" VERSION="1.1">
  <foxml:objectProperties>
    <foxml:property NAME="info:fedora/fedora-system:def/model#state" VALUE="Active"/>
    <foxml:property NAME="info:fedora/fedora-system:def/model#label" VALUE=""/>
    <foxml:property NAME="info:fedora/fedora-system:def/model#ownerId" VALUE=""/>
  </foxml:objectProperties>
</foxml:digitalObject>';
    return $template;
  }


  /**
   * Save xml out to a file
   * Note: save function in XmlObject class saves to file,
   * but default save for foxml ingests/updates in Fedora
   * @param string $filename
   * @return boolean success
   */
  public function saveXMLtoFile($filename) {
    return parent::save($filename);
  }


  /**
   * Ingest a new object or update an existing object in Fedora
   *
   * If pid has not been set, object is ingested; otherwise, loops
   * through all xml datastreams and the ones that have been modified
   * are updated in Fedora.
   *
   * @param string $message message to
   * @return string timestamp of last completed save, or null if failure
   */
  public function save($message) {
    // if pid is not defined, call getnextpid and ingest
    $result = null;

    // make sure object label is within allowable bounds of 255 characters
    $this->truncateLabel();


    if ($this->_pid == "") {
      return $this->ingest($message);
    } else {
      // this is not a new object: datastreams must be updated individually

      // if the top-level information has changed or object state has been set, update in Fedora
      if ($this->hasInfoChanged() || $this->next_object_state != null) {
        try {
          $result = $this->fedora->modifyObject($this->pid, $this->_label, $message,
            $this->next_object_state,	// if null, fedora leaves object state as is
            $this->_owner);
            $this->_info_changed = false;
        }
        catch (FedoraAccessDenied $e) {
            // FIXME: why is this only a warning? why not let the access denied be caught elsewhere?
          trigger_error("Access Denied to modify object properties", E_USER_WARNING);
        }
        // if update was successfull, null out next state for any future updates
        if ($result) $this->next_object_state = null;
      }

      //loop through xml datastreams and save them if they have changed
      foreach ($this->xmlconfig as $name => $opts) {
        if (isset($opts['dsID']) && isset($opts['class_name'])) {
          if ($this->isDatastreamLoaded($name) && $this->$name->hasChanged()) {
            // special datastream - if invalid xacml is saved to fedora, object cannot be accessed
            if ($opts['dsID'] == "POLICY" && !$this->$name->isValid(&$errors)) {
              // this is a very severe enough error - don't let it slip by unnoticed
              throw new FoxmlException("POLICY is not valid; cannot save because saving will break record: " . print_r($errors, true));
            }

            try {
                  // handle inline/managed datastreams appropriately
                  switch ($this->$name->control_group) {
                      case FedoraConnection::XML_DATASTREAM:
                          $result = $this->fedora->modifyXMLDatastream($this->pid, $opts['dsID'],
                          $this->$name->dslabel,
                          $this->$name->saveXML(), $message);
                          break;
                      case  FedoraConnection::MANAGED_DATASTREAM:
                          // fedora massages xml before checksumming in a way that we can't duplicate,
                          // so don't send checksums for xml datastreams
                          if ($this->is_xml_mimetype($this->$name->mimetype)) {
                              if ($this->$name->has_content_for_ingest()){
                                   // use checksum for new xml content to be saved
                                   //$checksum = $this->$name->dscontent_checksum();
                                   // above should be equivalent, but seems to cause an error for some datastreams
                                    $checksum =  md5($this->$name->saveXML());
                               }
                          }
                          // for binary content, it's possible to update datastream properties
                          // without changing the datastream content - handle that case
                          if ($this->$name->has_content_for_ingest()) {
                              $upload_id = $this->fedora->upload($this->$name->content_for_upload());
                          } else {
                              // content has not changed - send null for location so fedora will keep current content
                              $upload_id = null;
                              // if not sending content, don't send a checksum
                              $checksum = null;
                          }
                          $result = $this->fedora->modifyBinaryDatastream($this->pid, $opts['dsID'],
                                  $this->$name->dslabel, $this->$name->mimetype, $upload_id, $message,
                                  null, $checksum, $this->$name->checksum_type);
                          break;

                  } //end switch

                  // datastream successfully saved
                  if ($result)  {
                      // update checksum for future checks on whether or not datastream has changed
                      $this->$name->markUnchanged();
                      // update locally stored datastream last-modification date
                      $this->$name->last_modified = $result;
                  }
            } //end try
            catch (FedoraAccessDenied $e) {
                  trigger_error("Access Denied to modify datastream " . $opts['dsID'], E_USER_WARNING);
            }
          } //end if
        } //end if
      } //end foreach
    } //end else: not a new object

    // return timestamp of last completed save
    return $result;
  }

  /**
   * Get a new pid and ingest a new object into Fedora
   *
   * Note: this function has been separated out from save() so it can
   * be extended where custom pids are needed (e.g, using ARKs for pids)
   *
   * @param string $message message for Fedora audit trail
   */
  public function ingest($message) {
    // ONLY get a new pid if the pid is not already set
    if ($this->pid == "")
      $this->pid = $this->fedora->getNextPid('changeme');	// fixme: how to set pid namespace?

    // top-level object properties that need to be set in the xml before ingest
    if ($this->_label != null) $this->xml_label = $this->_label;
    if ($this->_owner != null) $this->xml_owner = $this->_owner;

    // if datastreams were created to be ingested, construct the full ingest xml
    // based on top-level object info and configured datastreams
    foreach ($this->_ingest_datastreams as $name) {
        $dsid = $this->xmlconfig[$name]['dsID'];
        $dsobj = $this->map[$name];
        // if the datastream object has content to be ingested,
        // build the appropriate ingest content and add to foxml ingest
        if ($dsobj->has_content_for_ingest()) {
            switch ($dsobj->control_group) {
                case FedoraConnection::XML_DATASTREAM:
                    $ds_content = $this->inline_datastream_content($dsobj);
                    break;
                case FedoraConnection::MANAGED_DATASTREAM:
                    $ds_content = $this->managed_datastream_content($dsobj);
                    break;
                    // default?
            }
            $this->build_ingest_datastream($dsid, $dsobj, $ds_content);
        }
    }
    $pid = $this->fedora->ingest($this->saveXML(), $message);
    // set fedora streams to null so it will be retrieved from fedora the
    // next time it is requested
    $this->_fedora_streams = null;
    $this->_is_ingested = true;

    // possible TODO: clear out ingest versions of datastreams so any
    // further access will pull them from fedora (?)

    return $pid;
  }

  /**
   * construct foxml for datastream portion of ingest object
   * @param string $dsid
   * @param object $dsobj datastream object
   * @param DOMNode $ds_content datastream content, as a DOMDocument element or node
   */
  private function build_ingest_datastream($dsid, $dsobj, $ds_content) {
    $datastream = $this->dom->createElementNS(self::xml_namespace, 'datastream');
    $datastream->setAttribute('CONTROL_GROUP', $dsobj->control_group);
    $datastream->setAttribute('ID', $dsid);
    $datastream->setAttribute('STATE', $dsobj->state);
    $datastream->setAttribute('VERSIONABLE', $dsobj->versionable ? 'true' : 'false');
    $ds_version = $this->dom->createElementNS(self::xml_namespace, 'datastreamVersion');
    $ds_version = $datastream->appendChild($ds_version);
    $ds_version->setAttribute('ID', $dsid . '.0');
    $ds_version->setAttribute('LABEL', $dsobj->dslabel);
    $ds_version->setAttribute('MIMETYPE', $dsobj->mimetype);
    // optional, not always set
    if ($dsobj->format_uri) $ds_version->setAttribute('FORMAT_URI', $dsobj->format_uri);
    // content digest/checksum?
    if ($dsobj->checksum_type) {
        $digest = $this->dom->createElementNS(self::xml_namespace, 'contentDigest');
        $digest->setAttribute('TYPE', $dsobj->checksum_type);
        if ($dsobj->checksum) $digest->setAttribute('DIGEST', $dsobj->checksum);
        $ds_version->appendChild($digest);
    }
    $ds_version->appendChild($ds_content);

    // add the new datastream to the top-level document element
    $this->dom->documentElement->appendChild($datastream);
  }

  /**
   * generate inline foxml datastream content for ingest
   * @param XmlObject $dsobj
   * @return DOMNode
   */
  private function inline_datastream_content($dsobj) {
    $content = $this->dom->createElementNS(self::xml_namespace, 'xmlContent');
    $ds_content = $this->dom->importNode($dsobj->domnode, true);
    $content->appendChild($ds_content);
    return $content;
  }

  /**
   * generate managed foxml datastream content for ingest
   * @param object $dsobj
   * @return DOMNode
   */
  private function managed_datastream_content($dsobj) {
    $upload_id = $this->fedora->upload($dsobj->content_for_upload());
    $content = $this->dom->createElementNS(self::xml_namespace, 'contentLocation');
    $content->setAttribute('REF', $upload_id);
    $content->setAttribute('TYPE', 'INTERNAL_ID');
    return $content;
  }


  /**
   * foxml label has a maximum of 255 characters; truncate if necessary before saving/ingesting
   */
  protected function truncateLabel() {
    if (strlen($this->label) > 255) {
      $this->label = substr($this->label, 0, 255);
    }

  }

  /**
   * purge a record from Fedora
   *
   * @param string $message
   */
  public function purge($message) {
    return $this->fedora->purge($this->pid, $message);	// allow to force?
  }


  /**
   * automagic variables
   *  - related objects are initialized the first time they are accessed and stored in related_objects array
   *  - datastreams are initialized the first time they are accessed and stored in XmlObject map array
   * @return foxml object or foxmlDatastream (if found)
   */
  public function &__get($name) {
    // NOTE: modified to return by reference so sub- and related objects can be handled properly
    // (change in php behavior)
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    // generic access to 'lazy-init' fedora-based content
    if (method_exists($this, "_get_$name")) {
        // NOTE: storing as a variable so it can be returned by reference
       $var = call_user_func(array($this, "_get_$name"));
       return $var;
    }

    // related objects - initialized the first time they are accessed
    if (isset($this->related_objects[$name]))	// if already set, simply return
      return $this->related_objects[$name];
    elseif (isset($this->relconfig[$name])) {	// otherwise, initialize if <configured
      $this->findRelatedObjects($name);
      return $this->related_objects[$name];
    }

    // datastreams are initialized the first time they are accessed
    if(!isset($this->map{$name}) && is_null($this->map{$name}) &&
       array_key_exists($name, $this->xmlconfig) &&
       isset($this->xmlconfig[$name]['dsID']) && isset($this->xmlconfig[$name]['class_name'])) {
       // previously, called listDatastreams to confirm datastream existed -
       // but that is an extra, usually unnecessary API call to Fedora

        $info = $this->fedora->getDatastreamInfo($this->pid, $this->xmlconfig[$name]['dsID']);
        // assume xml content should be loaded and initialized like an XmlObject
        if ($this->is_xml_mimetype($info->MIMEType)) {
            $dom = new DOMDocument();
            $xml = $this->fedora->getDatastream($this->pid, $this->xmlconfig[$name]['dsID']);
            if ($xml) {
              $dom->loadXML($xml, LIBXML_NSCLEAN);
              // clean up namespaces - ensure RELS-EXT doesn't get into a state Fedora can't handle
              $this->map{$name} = new $this->xmlconfig[$name]['class_name']($dom);
            } else {
              return null;
            }
        } else {
            // init non-xml datastream (currently assumed to be a file object)
            $this->map{$name} = new $this->xmlconfig[$name]['class_name']();
        }
        // set datastream info
        $this->map{$name}->setDatastreamInfo($info, $this);
        return $this->map{$name};
    }

    $value = parent::__get($name);
    return $value;
  }

  /**
   * magic function to check if an automagic variable is set/defined
   *  - for datastreams, checks if this object has the datastream defined
   *  - for related objects, initializes them if configured
   *  - otherwise, returns XmlObject isset() for any xml-mappings within the current object
   *
   * @return boolean
   */
  public function __isset($name) {
    if (property_exists($this, $name))
      return isset($this->$name);
    // generic access to 'lazy-init' fedora-based content - if method exists, consider value 'set'
    if (method_exists($this, "_get_$name")) {
        return true;
    }

    // datastreams are initialized the first time they are accessed
    if(array_key_exists($name, $this->xmlconfig)
       && isset($this->xmlconfig[$name]['dsID'])
       && isset($this->xmlconfig[$name]['class_name'])
       && (isset($this->map[$name]) || $this->has_datastream($this->xmlconfig[$name]['dsID'])))
      return true;

    // related objects
    if (isset($this->relconfig[$name])) {	// there is a related object configured
      if (isset($this->related_objects[$name]))		// already initialized
  return isset($this->related_objects[$name]);	// - note: could be null if initialized but not found
      else {
  // no other way to tell but by initializing
  $this->findRelatedObjects($name);
  return isset($this->related_objects[$name]);
      }
    }

    else
      return parent::__isset($name);
  }


  /*
   * magic function to handle setting special values
   * (e.g., set multiple fields simultaneously where a value is used several places systematically)
   *  - setting pid also stores pid in DC, RELS-EXT about, and POLICY id (when present)
   *  - setting label also sets DC title (depending on setting  label_is_dctitle)
   *
   *
   * @param $name name of the variable
   * @param $value value to be set
   */
  public function __set($name, $value) {
    // when initializing from Fedora, other datastreams may not yet be instantiated

    // generic access for setting 'lazy-init' fedora-based content
    if (method_exists($this, "_set_$name")) {
        // NOTE: storing as a variable so it can be returned by reference
       return call_user_func(array($this, "_set_$name"), $value);
    }
    // set any other field in standard XmlObject fashion
    return parent::__set($name, $value);
  }


  /**
   * find related foxml objects using risearch and $relconfig
   * called in __get the first time a related object is accessed
   *
   * @param string $name name of the related object
   */
  protected function findRelatedObjects($name) {
    $relation = $this->relconfig[$name]['relation'];
    $namespace = "fedora-rels-ext";	// default (risearch alias for rel namespace)
    if (isset($this->relconfig[$name]['namespace']))	// override default namespace if configured
      $namespace = $this->relconfig[$name]['namespace'];

    // prefix pid with info:fedora/ (required format for risearch)
    $pid = $this->pid;
    if (strpos($pid, "info:fedora/") === false)
      $pid = "info:fedora/$pid";

    // tuple query
    $query = '<' . $pid . '> <' . $namespace . ':' . $relation . '> *';
    $rdf = $this->fedora->risearch->triples($query);

    if(is_null($rdf) || $rdf == "")  {
      trigger_error("No data returned from resource index", E_USER_NOTICE);
      return;
    }

    $ns = $rdf->getNamespaces();	// get the namespaces directly from the simplexml object
    $descriptions = $rdf->children($ns['rdf']);

    if (count($descriptions) == 0) {		// no matches found
      if (isset($this->relconfig[$name]["is_series"]) && $this->relconfig[$name]["is_series"])
  $this->related_objects[$name] = array();	// if series, still needs to be array even if empty
      else
  $this->related_objects[$name] = null;
      return;
    }

    $tmp = array();

    $rels = $descriptions[0]->children();		// not in rdf namespace, just the relation, e.g. hasPart
    foreach ($rels as $result) {
      $pid = $result->attributes($ns['rdf']);	// rdf:resource
      $pid = str_replace("info:fedora/", "", $pid);
      // FIXME: better way to filter? seems to happen when there isn't a relation
      if ($pid == "http://localhost/") {
  $this->related_objects[$name] = null;
  return;
      }

      // a user may not have permission to see all the related objects; handle errors on initialization
      try {
  $tmp[] = new $this->relconfig[$name]['class_name']($pid, $this);	// pass reference to self
      } catch (FedoraObjectNotFound $e) {
  trigger_error("Object not found in fedora: " . $this->relconfig[$name]['class_name']
          . " $pid", E_USER_NOTICE);
      } catch (FoxmlException $e) {
  // should this warn or not? regular users should NOT see it
  trigger_error("Access denied on $pid (" . $this->relconfig[$name]['class_name']
          . ")", E_USER_NOTICE);
      } catch (FedoraAccessDenied $e) {
  // should this warn or not? regular users should NOT see it
  trigger_error("Access denied creating " . $this->relconfig[$name]['class_name']
          . " $pid", E_USER_NOTICE);
      }
    }

    if (isset($this->relconfig[$name]["is_series"]) && $this->relconfig[$name]["is_series"]) {
      $this->related_objects[$name] = $tmp;
      // optional sort function  (only makes sense for series with more than one element)
      if (isset($this->relconfig[$name]["sort"]) && count($this->related_objects[$name]) > 1) {
  usort($this->related_objects[$name], $this->relconfig[$name]["sort"]);
      }
    } else {
      $this->related_objects[$name] = $tmp[0];
    }


    return;
  }


  /**
   * make fedora object methods available as class methods, if user has permission
   * calls fedora listMethods and stores method definitions
   * - warns if fedora access is denied; notice if listMethods does not retrieve anything
   */
  protected function getFedoraMethods() {
  if (is_array($this->methodDefs)) {
    foreach ($this->methodDefs as $method) {
      $this->fedora_methods[$method->methodName] = $method->serviceDefinitionPID;
    }
    }
  }


  /**
   * expose fedora object methods as magic functions
   * if name or _name is in list of fedora_methods, calls dissemination
   * If the first argument is an array, it will be passed to the
   * dissemination as method parameters; should be in key/value format
   * NOTE: where response time is important, this method is less efficient
   * as it requires an extra Fedora API call to identify the methods to be called.
   *
   * @param string $name name of the functions
   * @param array $arguments
   */
  public function __call($name, $arguments) {
    // ensure that method definitions have been loaded from fedora
    $this->getFedoraMethods();

    // strip off any preceding _ from method name (used to differentiate from local methods)
    $name = preg_replace("/^_/", "", $name);
      if (array_key_exists($name, $this->fedora_methods)) {
  // if first argument is an array, pass the array as method without parameters
  $params = (isset($arguments[0]) && is_array($arguments[0])) ? $arguments[0] : array();
  $result = $this->fedora->getDisseminationSOAP($this->pid, $this->fedora_methods[$name], $name,
                  $params);
          if ($result) return $result->stream;
      } else {
          throw new FoxmlException("Method '" . $name . "' not found");
      }
  }


  /**
   * upload a file and set upload id as datastream url for ingest
   *
   * @param string $datastream name of datastream
   * @param string $filename path to file to be uploaded
   */
  public function setDatastreamFile($datastream, $filename) {
   if (! file_exists($filename)) {
      trigger_error("File $filename does not exist", E_USER_ERROR);
    } else {
      $upload_id = $this->fedora->upload($filename);
         // FIXME: check that upload returns a valid upload id
      if (!isset($this->{$datastream})) {
        trigger_error("datastream $datastream is not configured", E_USER_WARNING);
      }
      $this->{$datastream}->url = $upload_id;
    }

  return $upload_id;
  }


  /**
   * add or update a content model to the rels-ext
   * @param string $pid fedora pid for the content model object
   */
  public function setContentModel($pid) {
    // if the content model is not already present, add it
    if (! $this->hasContentModel($pid)) {
      $this->rels_ext->addContentModel($pid);
    }
  }


  public function contentModelName() {
    return $this->contentModelInfo("name");
  }
  public function contentModelVersion() {
    return $this->contentModelInfo("version");
  }

  protected function contentModelInfo($mode) {
    if (!isset($this->rels_ext->hasModel) || $this->rels_ext->hasModel == "")
      throw new XmlObjectException("Cannot determine content model information: hasModel relation is not set");

    // expect the contentModel to look something like this:
    // pid_namespace:cmodel_v1 (content model name, version number)
    // OR namespace:cmodel-1.0

    // split pid namespace from id portion
    list($namespace, $pid) = split(":", $this->rels_ext->hasModel);

    if (preg_match("/^(.*)[_-]v?([0-9.]*)?$/", $pid, $matches)) {
      // first match is the content model name
      if ($mode == "name") return $matches[1];
      // second match is the content model name
      if ($mode == "version") return $matches[2];
    } elseif ($mode == "name") {
      // if name was requested but no version match found, return the whole thing
      return $pid;
    }
  }


  /**
   * determine if the specified cmodel pid is one of this objects contentmodels
   * @param string $pid
   * @return boolean
   */
  public function hasContentModel($pid) {
    if (!isset($this->rels_ext) || !isset($this->rels_ext->hasModels))
      throw new XmlObjectException("Cannot determine content model information: rels_ext datastream or hasModels relation is not set");

    if ($this->rels_ext && $this->rels_ext->hasModels) {
        return $this->rels_ext->hasModels->includes($this->rels_ext->pidToResource($pid));
    }
  }

  /**
   * Set the state of this object - updated in Fedora the next time object is saved.
   * NOTE: no way to get object state from Fedora, so unless this is
   * set the state will be left unchanged when other updates are made
   *
   * @param string $state state code (A/I/D - use FedoraConnection state constants)
   */
  public function setObjectState($state) {
    // complain & reject if not a valid state
    if (! in_array($state, array(FedoraConnection::STATE_ACTIVE, FedoraConnection::STATE_INACTIVE,
         FedoraConnection::STATE_DELETED),
       true)) { 	// doing strict search (case-sensitive)
      trigger_error("State '$state' is not a valid object state; ignoring", E_USER_WARNING);
    } else {
      $this->next_object_state = $state;
    }
  }

  /**
   * internal function - check if a mimetype should be considered xml
   * @param foxmlDatastreamAbstract $dsobj
   * @return boolean
   */
  protected function is_xml_mimetype($mimetype) {
    return ($mimetype == 'text/xml' || $mimetype == 'application/xml' ||
            preg_match('/\+xml$/', $mimetype));
  }

}


/**
 * foxmlDatastream object for access to datastream properties
 * @property string $id
 * @property string $control_group
 * @property string $state
 * @property string $versionable
 * @property array of foxmlDatastreamVersion $versions
 * @property string $label (from last foxmlDatastreamVersion)
 * @property string $mimetype (from last foxmlDatastreamVersion)
 * @property string $digest_type contentDigest type, e.g. MD5 (from last foxmlDatastreamVersion)
 * @property string $digest contentDigest value, e.g. md5sum (from last foxmlDatastreamVersion)
 */
class foxmlDatastream extends XmlObject {
   public function __construct($dom, $xpath = null, XmlObjectPropertyCollection $subconfig = null) {
    $config = $this->config(array(
       "id" => array("xpath" => "@ID"),
       "control_group" => array("xpath" => "@CONTROL_GROUP"),
       "state" => array("xpath" => "@STATE"),
       "versionable" => array("xpath" => "@VERSIONABLE"),
       "versions" => array("xpath" => "foxml:datastreamVersion",
                            "class_name" => "foxmlDatastreamVersion", "is_series" => true),
       // shortcuts to datastreamVersion properties - pick up latest instance if multiple
       "label" => array("xpath" => "foxml:datastreamVersion[position() = last()]/@LABEL"),
       "mimetype" => array("xpath" => "foxml:datastreamVersion[position() = last()]/@MIMETYPE"),
       "digest_type" => array("xpath" => "foxml:datastreamVersion[position() = last()]/foxml:contentDigest/@TYPE"),
       "digest" => array("xpath" => "foxml:datastreamVersion[position() = last()]/foxml:contentDigest/@DIGEST"),
       ));
   if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($dom, $config, $xpath);
  }

  /**
   * set checksum for a datastream (sets on latest datastreamVersion if multiple)
   * @param string $value checksum value
   * @param string $type checksum type, e.g. MD5
   */
  public function setChecksum($value = "", $type = "") {
      $this->versions[count($this->versions) - 1]->setChecksum($value, $type);
      $this->update();
  }

}

/**
 * foxmlDatastreamVersion object for access to datastreamVersion properties
 * @property string $id
 * @property string $label
 * @property string $mimetype
 * @property string $digest_type contentDigest type
 * @property string $digest contentDigest value
 */
class foxmlDatastreamVersion extends XmlObject {
   public function __construct($dom, $xpath = null, XmlObjectPropertyCollection $subconfig = null) {
    $config = $this->config(array(
       "id" => array("xpath" => "@ID"),
       "label" => array("xpath" => "@LABEL"),
       "mimetype" => array("xpath" => "@MIMETYPE"),
       // checksum values
       "digest_type" => array("xpath" => "foxml:contentDigest/@TYPE"),
       "digest" => array("xpath" => "foxml:contentDigest/@DIGEST"),
       ));
   if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($dom, $config, $xpath);
  }

  /**
   * set contentDigest for this datastreamVersion
   * @see foxmlDatastream::setChecksum
   */
  public function setChecksum($value = "", $type = "") {
      if (! isset($this->digest_type) || ! isset($this->digest)) {
        $contentDigest = $this->dom->createElementNS(foxml::xml_namespace, "foxml:contentDigest");
        $contentDigest->setAttribute("TYPE", $type);
        $contentDigest->setAttribute("DIGEST", $value);
        $this->domnode->insertBefore($contentDigest, $this->domnode->childNodes->item(0));
        $this->update();
      } else {
        $this->digest_type = $type;
        $this->digest = $value;
      }
  }

}

/**
 * simple xml class to extend for ingesting non-xml datastreams
 * @property string $url upload location url
 */
class foxmlIngestStream extends foxmlDatastream {

  public function __construct($dom, $xpath = null) {
    $config = $this->config(array(
       "url" => array("xpath" => "foxml:datastreamVersion/foxml:contentLocation/@REF")
       ));
    parent::__construct($dom, $xpath, $config);
  }
}
