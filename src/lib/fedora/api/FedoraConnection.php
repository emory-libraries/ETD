<?php

require_once("Fedora_API_A_Service.php");
require_once("Fedora_API_M_Service.php");
require_once("risearch.php");

/**
 * FedoraConnection - wrapper around API-A and API-M SOAP clients
 * also includes function to upload files to fedora and an risearch instance
 */


class FedoraException extends Exception {}
class FedoraObjectNotFound extends FedoraException {}
class FedoraObjectNotValid extends FedoraException {}
class FedoraObjectExists extends FedoraException {}
class FedoraAccessDenied extends FedoraException {}
class FedoraNotAuthorized extends FedoraException {}
class FedoraNotAvailable extends FedoraException {}
class FedoraDatastreamNotFound extends FedoraException {}
class FedoraChecksumMismatch extends FedoraException {}

class FedoraConnection {

  /**
   * constants for datastream and object states
   */
  const STATE_ACTIVE   = "A";
  const STATE_INACTIVE = "I";
  const STATE_DELETED  = "D";

  /**
   * constants for datastream control group code
   */
  const XML_DATASTREAM     = "X";
  const MANAGED_DATASTREAM   = "M";
  const REDIRECT_DATASTREAM    = "R";
  const EXTERNAL_REF_DATASTREAM  = "E";

  /**
   * configuration settings (defaults + user-specified params)
   * @var array
   */
  private $config;

  /**
   * URI for Fedora API
   * @var string
   */
  private $uri = "http://www.fedora.info/definitions/1/0/api/";

  /**
   * @var Fedora_API_A_Service
   */
  private $apia;

  /**
   * @var Fedora_API_M_Service
   */
  private $apim;

  /**
   * @var risearch
   */
  public $risearch;

  /**
   * base fedora url, built based on configuration parameters
   * @var string
   */
  public $location;

  /**
   * upload url, built based on configuration parameters
   * @var string
   */
  private $upload_url;

  /**
   * initialize API-A and API-M SOAP classes as well as an risearch
   * instance, based on Fedora location and credentials specified
   *
   * @param array|Zend_Config configuration parameters, including the following
   *      defaults
   *  username
   *  password
   *  server*
   *  port        (8080)
   *  nonssl_port - if default config is https/ssl, optionally specify nonssl port
   *    protocol      (http)
   *  resourceIndex
   *    path    (risearch)
   *    requiresAuth  (false)
   *    username
   *    password
   *
   * If resource index requiresAuth is specified but username/password are not set,
   * fedora credentials will be used.
   *
   * @throws FedoraNotAvailable if wsdl files cannot be retrieved/accessed
   */
  public function __construct($params) {

    $this->loadConfiguration($params);

    $access_location = $this->location . "/services/access";
    $access_wsdl = $this->location . "/wsdl?api=API-A";
    $mgmt_location = $this->location . "/services/management";
    $mgmt_wsdl = $this->location . "/wsdl?api=API-M";

    $options = array(
             "location" => $mgmt_location,
         "uri" => $this->uri,
         "trace" => true,
         "features" => SOAP_USE_XSI_ARRAY_TYPE,
   // NOTE: not enabling single element array because then too many things get converted to arrays
   //"features" => SOAP_SINGLE_ELEMENT_ARRAYS,
         );
    // username & password are optional - pass to SoapClients when set
    if (isset($this->config['username'])) {
        $options['login'] = $this->config['username'];
        $options['password'] = $this->config['password'];
    }

    try {
      // catch errors if Fedora is not accessible
      $this->apim = new Fedora_API_M_Service($mgmt_wsdl, $options);
      $options["location"] = $access_location;
      $this->apia = new Fedora_API_A_Service($access_wsdl, $options);
    } catch (SoapFault $e) {
      // can't even retrieve wsdl file - Fedora is unavailable or misconfigured
      throw new FedoraNotAvailable(); return;
    }


    $this->risearch = new risearch($this->config["server"], $this->config["risearch"]["port"],
           $this->config["risearch"]["path"], $this->config["risearch"]["protocol"]);
    if ($this->config["risearch"]["requiresAuth"])
      $this->risearch->setAuthCredentials($this->config["risearch"]["username"],
            $this->config["risearch"]["password"]);
  }

  private function setDefaults() {
    $this->config = array("port" => "8080",
        "protocol" => "http",
        "risearch" => array("path" => "risearch",
                "requiresAuth" => false)
        );
  }

  private function loadConfiguration($params) {
    if ($params instanceof Zend_Config) {
      $params = $params->toArray();
    }

    if (!is_array($params))
      throw new FedoraException("Parameters must be in an array or Zend Config object");

    $this->setDefaults();

    // required fields - error if not set
    if (!isset($params["server"]))
      throw new FedoraException("server parameter must be specified");
    $this->config["server"] = $params["server"];

    // credentials are optional, but password should be set if username is
    if (isset($params['username'])) {
        if (!isset($params['password']))
            throw new FedoraException("password parameter must be specified when username is specified");
        $this->config["username"] = $params["username"];
        $this->config['password'] = $params['password'];

    }

    // optional parameters - override defaults if specified
    if (isset($params["port"])) $this->config["port"] = $params["port"];
    if (isset($params["protocol"])) $this->config["protocol"] = $params["protocol"];
    // if nonssl port is specified, save in local config array
    if (isset($params["nonssl_port"])) $this->config["nonssl_port"] = $params["nonssl_port"];
    if (isset($params["proxy_server"])) $this->config["proxy_server"] = $params["proxy_server"];

    // risearch settings
    if (!isset($params["risearch"])) $params["risearch"] = array();

    if (isset($params["risearch"])) {
      if (isset($params["risearch"]["path"]))
  $this->config["risearch"]["path"] = $params["risearch"]["path"];
      if (isset($params["risearch"]["requiresAuth"]))
  $this->config["risearch"]["requiresAuth"] = $params["risearch"]["requiresAuth"];
      if (isset($params["risearch"]["username"]))
  $this->config["risearch"]["username"] = $params["risearch"]["username"];
      if (isset($params["risearch"]["password"]))
  $this->config["risearch"]["password"] = $params["risearch"]["password"];

      // if risearch requires auth but user/pass not specified, use fedora user/pass
    if ($this->config["risearch"]["requiresAuth"] &&
    !isset($params["risearch"]["username"]))
        $this->config["risearch"]["username"] = $this->config["username"];
    if ($this->config["risearch"]["requiresAuth"] &&
    !isset($params["risearch"]["password"]))
        $this->config["risearch"]["password"] = $this->config["password"];

    if (isset($params["risearch"]["port"]))
        $this->config["risearch"]["port"] = $params["risearch"]["port"];
    else
        $this->config["risearch"]["port"] = $this->config["port"];

    if (isset($params["risearch"]["protocol"]))
        $this->config["risearch"]["protocol"] = $params["risearch"]["protocol"];
    else
        $this->config["risearch"]["protocol"] = $this->config["protocol"];
    }

    $this->location = $this->config["protocol"] . "://" . $this->config["server"]
      . ":" . $this->config["port"] . "/fedora";
    $this->upload_url = $this->location . "/management/upload";
  }


  /**
   * internal function for checking errors on SOAP response from Fedora
   * @param string $faultstring fault from soap exception
   * @param string $text description of action being taken
   * @throws FedoraNotAuthorized
   * @throws FedoraObjectNotFound
   * @throws FedoraAccessDenied
   * @throws FedoraObjectNotValid (ingest only)
   * @throws FedoraObjectExists   (ingest only)
   */
  private function checkErrors($faultstring, $text) {
    $message = preg_replace("/^[a-zA-Z\.]+: /", "", $faultstring);

    if (preg_match("/^([a-zA-Z\.]+): (.*)/", $faultstring, $matches)) {
      $exception = $matches[1];
      $message = $matches[2];
    } elseif (preg_match("/^Unauthorized( Request)?$/", $faultstring)) { // bad password
      throw new FedoraNotAuthorized($text); break;
      return;
    } else {
      trigger_error("Unrecognized Fedora exception: $faultstring", E_USER_NOTICE);
      return;
    }

    switch ($exception) {
      // match either 3.4 (org.fcrepo) or pre-3.4 fedora exception strings
    case "fedora.server.errors.ObjectNotInLowlevelStorageException":  // object doesn't exist
    case "org.fcrepo.server.errors.ObjectNotInLowlevelStorageException":
      throw new FedoraObjectNotFound($message); break;
    case "fedora.server.errors.authorization.AuthzDeniedException":   // don't have permission
    case "org.fcrepo.server.errors.authorization.AuthzDeniedException":
      throw new FedoraAccessDenied($text);  break;  // (no message from fault string for this one)
    case "fedora.server.errors.ObjectValidityException":  // invalid xml
    case "org.fcrepo.server.errors.ObjectValidityException":
      throw new FedoraObjectNotValid($text); break;
    case "fedora.server.errors.ObjectExistsException":    // object already exists (on ingest)
    case "org.fcrepo.server.errors.ObjectExistsException":
      throw new FedoraObjectExists($message); break;
    case "fedora.server.errors.DatastreamNotFoundException":  // datastream doesn't exist
    case "org.fcrepo.server.errors.DatastreamNotFoundException":
      throw new FedoraDatastreamNotFound($message); break;
    case "org.fcrepo.server.errors.ValidationException":
        // if validation exception is a checksum mismatch, raise specific error
        if (preg_match("/^Checksum Mismatch/i", $message)) {
            throw new FedoraChecksumMismatch($message . ': ' . $text); break;
        }
        // otherwise, fall through to default
    default:
      trigger_error("Unrecognized Fedora exception: $faultstring - ($exception); $text", E_USER_NOTICE);
    }
  }


  /* API-A functions */

  /**
   * generic api-a call - takes method name and options, and handles errors
   * @param string $method apia method to call
   * @param $options request object as appropriate to method
   * @param string $errortext description of action to display in case of error
   * @param return method response object on success, null on failure
   */
  private function apia($method, $options, $errortext) {
    try {
      $response = $this->apia->$method($options);
      return $response;
    } catch (SoapFault $e) {
      $this->checkErrors($e->faultstring, $errortext);
      return null;
    }
  }

  /**
   * retrieve top-level information about a Fedora object
   * @param string $pid fedora id
   * @param string $dateTime retrieve information as of a specified time (optional)
   * @return objectProfile
   */
  public function getObjectProfile($pid, $dateTime = null) {
    $options = new getObjectProfile();
    $options->pid = $pid;
    $options->asOfDateTime = $dateTime;
    $response = $this->apia("getObjectProfile", $options, "getObjectProfile for $pid");
    if ($response)
      return $response->objectProfile;
    else
      return null;
  }

  /**
   * search for objects in Fedora
   * @param array $resultFields array of fields to return
   * @param int $maxResults number of results to return
   * @param string|array of Condition $query string search term OR array of Condition for fielded search
   * @return FieldSearchResult
   */
  public function findObjects(array $resultFields, $maxResults, $query) {
    $options = new findObjects();
    $options->query = new FieldSearchQuery();

    // array of field names to be returned
    // NOTE: fedora complains if this list does not include pid; forcing it to always be included
    $options->resultFields = array_merge(array("pid"), $resultFields);
    $options->maxResults = $maxResults; // number of results to return

    // query is an array of conditions - search by field
    if (is_array($query)) {
      //      $options->query->terms = null;  // no search term
      $text_conditions = array();

      // FIXME: should types be checked here?
      foreach ($query as $c) {
  $condition = new Condition($c->property, $c->operator, $c->value);
  $condition->property = $c->property;
  $condition->operator = $c->operator;
  $condition->value = $c->value;
  $options->query->conditions[] = $condition;

  $text_conditions[] = $c->property . " " . $condition->operator . " " . $condition->value . ";";
      }

      // text summary of search conditions (for error message)
      $query_summary = "where " . implode(', ', $text_conditions);
    } else {
      // query is a generic/unfielded search
      $options->query = new FieldSearchQuery_TermOnly();
      /* NOTE: current version of php has a bug with xsd:choice in request object;
         Using custom FieldSearchQuery object that only has term and not conditions as workaround.
       */
      $options->query->terms = $query;
      $query_summary = "searching for '" . $query . "'";
    }

    $response = $this->apia("findObjects", $options, "findObjects $query_summary"); // search terms?
    if ($response) {
      /** NOTE: php has a "feature" that converts arrays into single
          elements when only one result is returned.  This behavior
          can be changed with a SoapClient configuration, but since
          that configuration was not used from the start, it is likely
          to affect many of the other api calls.  Adjusting result
          object here so that objectFields is always an array. */
      if (!is_array($response->result->resultList->objectFields) &&
    !is_null($response->result->resultList->objectFields)) {
  $response->result->resultList->objectFields = array($response->result->resultList->objectFields);
      }
      return $response->result;
    } else
      return null;
  }

  /**
   * retrieve object ownerId from fedora
   * @param string $pid
   * @return string|null
   */
  public function getOwner($pid) {
    // because there is currently no easy way to get oject owner from fedora,
    // do a find by pid and return only the owner field

    // ensure pid does not have leading info:fedora/, as searches will fail
    $pid = $this->risearch->risearchpid_to_pid($pid);

    $result = $this->findObjects(array("ownerId"), 2,
         array(new Condition("pid", ComparisonOperator::eq, $pid))
         );
    if (count($result->resultList->objectFields) != 1) {
      trigger_error("Could not find owner for " . $pid .
        "; saving object information will remove owner from record",
        E_USER_WARNING);
      return null;
    }
    return $result->resultList->objectFields[0]->ownerId;
  }

  // mock-up for possible findby* magic functions
  public function findByTitle($title, $max = 15) {
    return $this->findObjects(array("title"), $max, array(new Condition("title", ComparisonOperator::has, $title)));
  }
  public function findByPid($pid, $max = 15) {
    return $this->findObjects(array("title"), $max, array(new Condition("pid", ComparisonOperator::has, $pid)));
  }

  /** attempt at a magic findby* function....

  public function __call($method, $args) {
    $fields = array("title", "pid");
    $field_regexp = "(" . implode('|', $fields) . ")";
    $return_fields = array("title");
    $default_max = 15;

    if (preg_match("/findBy" . $field_regexp . "/i", $method, $matches)) {
      $field = strotolower($matches[1]);
      $value = $args[0];
      $max = isset($args[1]) ? $args[1] : $default_max;
      return $this->findObjects(array_merge($return_fields, array($field)), $max,
        array(new Condition($field, ComparisonOperator::has, $args[0])));

    } else {
      trigger_error("Method $method unknown", E_USER_WARNING);
    }
    }*/


  /**
   * REST version of findObjects call (deprecated)
   * @depricated use SOAP version instead
   * @param array $params
   */
  public function findObjectsREST($params) {
    //$url=http://localhost:8080/fedora/search?query=title~ocm00330651%20cModel=pagedBook&pid=true
    $this->server="localhost";
    $url = $this->protocol . "://" . $this->server . ":" . $this->port . "/fedora/search";
    if (count($params)) $url .= "?";
    $url_params = array();
    foreach ($params as $key => $value)  $url_params[] = $key . "=" . $value;
    $url .= implode("&", $url_params);

    return $this->getUrlContents($url);
  }

  /**
   * get the history of an object
   * @param string $pid
   * @return getObjectHistoryResponse
   */
  public function getObjectHistory($pid) {
    $options = new getObjectHistory();
    $options->pid = $pid;
    return $this->apia("getObjectHistory", $options, "getObjectHistory for $pid");
  }

  /**
   * retrieve a datastream (api-a version - getDatastreamDissemination)
   * @param string $pid
   * @param string $ds datastream ID
   * @return datastream content on success or null on failure
   */
  public function getDatastream($pid, $ds, $datetime = null) {
    $options = new getDatastreamDissemination();
    $options->pid = $pid;
    $options->dsID = $ds;
    $options->asOfDateTime = $datetime;
    $response = $this->apia("getDatastreamDissemination", $options, "getDatastream for $pid/$ds");
    if ($response)
      return $response->dissemination->stream;
    else
      return null;
  }

  /**
   * retrieve a datastream via REST interface
   * added force_ssl option to help with speed issues for downloading large binary datastreams
   * @param string $pid
   * @param string $ds datastream ID
   * @param boolean $force_ssl optional, defaults to false - retrieve over nonssl
   * @return datastream content
   */
  public function getDatastreamREST($pid, $ds, $force_nonssl = false) {
    return $this->getUrlContents($this->datastreamUrl($pid, $ds, $force_nonssl));
  }


  /**
   * retrieve a datastream and save to a file
   * intended for use with datastreams too large to be loaded into memory
   *
   * @param string $pid
   * @param string $ds datastream ID
   * @param string $filename full path filename where contents should be saved
   * @return int|boolean bytes written on success, false on failure
   */
  public function saveDatastream($pid, $ds, $filename) {
    if ($this->getUrlContents($this->datastreamUrl($pid, $ds),
            $filename)) {
      return filesize($filename);
    } else {
      return false;
    }
  }



  /**
   * get information about all datastreams
   * @param string $pid
   * @return DatastreamDef (single or array)
   */
  public function listDatastreams($pid) {
    $opt = new listDatastreams();
    $opt->pid = $pid;
    $result = $this->apia("listDatastreams", $opt, "listDatastreams for $pid");
    if ($result)
      return $result->datastreamDef;
    else
      return null;
  }

  /**
   * list methods available on an object
   * @param string $pid
   * @return objectMethod (single or array)
   */
  public function listMethods($pid) {
    $opt = new listMethods();
    $opt->pid = $pid;
    $result = $this->apia("listMethods", $opt, "listMethods for $pid");
    if ($result)
      return $result->objectMethod;
    else
      return null;

  }

  /**
   * get dissemination - currently using REST api-a lite, *without* authentication
   * @param string $pid fedora object pid
   * @param string $bdefpid pid of the bdef
   * @param string $method method to call
   * @param array $params array of parameters for the function, pass as name => value
   * @param string $date retrieve version as of a specified date/time (optional)
   * @return dissemination output
   */
  public function getDissemination($pid, $bdefpid, $method, $params = array(), $date = null) {

    // REST API datastream url format
    $url = $this->location . "/objects/" . $pid . "/methods/" . $bdefpid . "/" . $method;
    if (count($params)) $url .= "?";
    $url_params = array();
    foreach ($params as $key => $value)  $url_params[] = $key . "=" . $value;
    $url .= implode("&", $url_params);

    return $this->getUrlContents($url);

    // NOTE: would be better to call the soap getDissemination soap if
    // we can figure out how to pass parameters...
  }

  /**
   * get dissemination - soap version
   * NOTE: does not handle parameters properly
   * @param string $pid fedora object pid
   * @param string $bdefpid pid of the service definition object
   * @param string $method method to call
   * @param array $params array of parameters for the function, pass as name => value
   * @param string $date retrieve version as of a specified date/time (optional)
   * @return MIMETypedStream
   */
  public function getDisseminationSOAP($pid, $sdefpid, $method, $params = array(), $date = null) {

    $opt = new getDissemination();
    $opt->pid = $pid;
    $opt->serviceDefinitionPid = $sdefpid;
    $opt->methodName = $method;
    //    $opt->parameters = array();

    // convert parameters into an array of Property
    foreach ($params as $name => $value) {
      $prop = new Property();
      $prop->name = $name;
      $prop->value = $value;
      $opt->parameters[] = $prop;
    }
    $opt->asOfDateTime = $date;

    $result = $this->apia("getDissemination", $opt, "getDissemination $method ($sdefpid) on $pid");
    if ($result)
      return $result->dissemination;
    else
      return null;
  }


  /* api-m functions */
  /**
   * generic api-m call - takes method name and options, and handles errors
   * @param string $method apia method to call
   * @param $options request object as appropriate to method
   * @param string $errortext description of action to display in case of error
   * @param return method response object on success, null on failure
   */
  private function apim($method, $options, $errortext) {
    try {
      $response = $this->apim->$method($options);
      return $response;
    } catch (SoapFault $e) {
      $this->checkErrors($e->faultstring, $errortext);
      return null;
    }
  }

  /**
   * get the next pid for a new object
   * @param string $namespace namespace in which to generate the pid
   * @param int $number number of pids to return (optional, defaults to 1)
   * @return pid on success, null on failure
   */
  public function getNextPid($namespace, $number = 1) {
    $options = new getNextPid();
    $options->numPIDs = $number;
    $options->pidNamespace = $namespace;
    $result = $this->apim("getNextPid", $options, "getNextPid - $namespace");
    if ($result) {
      // NOTE: pid is an array or single value depending on what is requested
      return $result->pid;
    }   else
      return null;
  }

  /**
   * ingest a new object into fedora
   * @param string $xml full foxml content
   * @param string $message log message
   * @param string $format ingest format, defaults to foxml1.0
   * @return pid of new object on success, null on failure
   */
  public function ingest($xml, $message, $format = "info:fedora/fedora-system:FOXML-1.1") {
    $options = new ingest();
    $options->objectXML =$xml;  // base64binary
    $options->format = $format;
    $options->logMessage = $message;

    $result = $this->apim("ingest", $options, "ingest");
    if ($result)
      return $result->objectPID;
    else
      return null;
  }

  /**
   *  API-M version of getDatastream - returns information *about* the datastream
   *  returns controlGroup (X for xml), datastream ID, version id, alternate ids, label, versionable (1/0),
   *  mimetype, format uri, createDate, size, state (active/inactive), location, checksumtype & checksum
   * @param string $pid
   * @param string $ds datastream ID
   * @param string $datetime (optional) get info at a specified time (ignored in Fedora2 ?)
   * @return Datastream on success, null on failure
   */
  public function getDatastreamInfo($pid, $ds, $datetime = null) {
    $options = new getDatastream();
    $options->pid = $pid;
    $options->dsID = $ds;
    $options->asOfDateTime = $datetime;
    $response = $this->apim("getDatastream", $options, "getDatastream for $pid/$ds");
    if ($response)
      return $response->datastream;
    else
      return null;
  }

  /**
   * get the history of a datastream
   * @param string $pid
   * @param string $ds datastream ID
   * @return getDatastreamHistoryResponse on success
   */
  public function getDatastreamHistory($pid, $ds) {
    $options = new getDatastreamHistory();
    $options->pid = $pid;
    $options->dsID = $ds;
    $response = $this->apim("getDatastreamHistory", $options, "getDatastreamHistory for $pid/$ds");
    if ($response) return $response;
    else return null;
  }


  /**
   * modify top-level object information
   * @param string $pid
   * @param string $label new object label
   * @param string $message log message about the change
   * @param string $state state of the object (A/I/D, null = leave unchanged)
   * @param string $owner owner of the object (NOTE: if blank, removes current owner)
   * @returns date modified on success
   */
  public function modifyObject($pid, $label, $message, $state = null, $owner = null) {
    $options = new modifyObject();
    $options->pid = $pid;
    $options->state = $state; // if null, will be left unchanged
    $options->label = $label;   // if null, will be left unchanged
    $options->ownerId = $owner;  // FIXME: if null, will blank out current owner
    $options->logMessage = $message;
    $result = $this->apim("modifyObject", $options, "modifyObject for $pid");
    if ($result)
      return $result->modifiedDate; // if successful, returns date modified
    else
      return null;
  }

  /**
   * modify an xml datastream - wrapper with xml settings for modifyDatastreamByValue
   * @param string $pid
   * @param string $dsid datastream id
   * @param string $label datastream label
   * @param string $content new xml content
   * @param string $message log message about the change
   * @return date modified on success
   */
  public function modifyXMLDatastream($pid, $dsid, $label, $content, $message,
                                       $checksum_type='MD5') {
    $opt = new modifyDatastreamByValue();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    $opt->dsLabel = $label;
    $opt->MIMEType = "text/xml";
    $opt->dsContent = $content;

    // NOTE: Fedora does some custom cleaning of xml datastreams before
    // calculating the checksum, which makes it impossible to match the checksum.
    // Setting checksum value to null so Fedora will calculate the checksum.
    $opt->checksumType = $checksum_type;
    $opt->checksum =  "NULL";

    $opt->logMessage = $message;
    $opt->force = false;

    $result = $this->apim("modifyDatastreamByValue", $opt, "modify datastream - $pid/$dsid");
    if ($result)
      return $result->modifiedDate;
    else
      return null;
  }

  /**
   * compare checksums on a datastream
   * @param string $pid
   * @param string $dsid datastream id
   * @param string $date (optional) get checksum from a specified time
   * @return checksum on success
   */
  public function compareDatastreamChecksum($pid, $dsid, $date = null) {
    $opt = new compareDatastreamChecksum();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    $opt->date = $date;

    $result = $this->apim("compareDatastreamChecksum", $opt, "compare datastream checksum - $pid/$dsid");
    if ($result)
      return $result->checksum;
    else
      return null;
  }

  /**
   * Modify a binary datastream.  If a checksum value is not passed in, this
   * method will attempt to calculate a checksum when possible (e.g., when a filename
   * is specified or location is a something PHP can checksum), according to the
   * specified type.  Only MD5 and SHA-1 checksum types are supported for
   * checksum calculation by this method.
   *
   * @param string $pid
   * @param string $dsid datastream id
   * @param string $label datastream label
   * @param string $mimetype datastream mimetype
   * @param string $location location of the new content (e.g., url or upload id)
   * @param string $message log message
   * @param string $filename location of file for calculating checksum (optional)
   * @param string $checksum checksum for the new datastream contents (optional)
   * @param string $checksum_type checksum type for the new datastream contents (optional, defaults to MD5)
   * @return date modified on success
   */
  public function modifyBinaryDatastream($pid, $dsid, $label, $mimetype, $location, $message,
           $filename = null, $checksum = null, $checksum_type = 'MD5') {
    $opt = new modifyDatastreamByReference();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    $opt->dsLabel = $label;
    $opt->MIMEType = $mimetype;
    $opt->dsLocation = $location;
    // allow updating datastream properties without changing datastream content
    if ($opt->dsLocation === null) $opt->dsLocation = 'NULL';
    $opt->checksumType = $checksum_type;

    // if a checksum is specified, use it
    if (!is_null($checksum)) {
        $opt->checksum = $checksum;
    // otherwise, calculate a checksum if we can (from filename or location)
    } else {
        if ($filename != null) {
            $checksum_location =  $filename;
        } elseif (!preg_match("|^uploaded://[0-9]+$|", $location) &&
              is_file($location)) {
          // if location is a file, use that to calculate the checksum
          // NOTE: don't check fedora upload ids with is_file--
          // certain versions of php is_file will complain about not knowing the protocol
          $checksum_location = $location;
        } else {
          // checksum cannot be calculated locally; allow fedora to calculate requested type
          $opt->checksum = "NULL";
        }
        if (isset($checksum_location)) {
            switch ($checksum_type) {
                case 'MD5':
                    $opt->checksum = md5_file($checksum_location);
                    break;
                case 'SHA-1':
                    $opt->checksum = sha1_file($checksum_location);
                    break;
                default:
                    throw Exception('Unsupported checksum type ' . $checksum_type);
            }
        }
    }

    $opt->logMessage = $message;

    // note: if these are not explicitly set, soap call fails
    // FIXME/TODO: expose these in function call...
    $opt->altIDs = "";
    $opt->formatURI = "";
    $opt->force = false;
    // if($opt->dsID=='FILE'){
    //   $opt->checksum = '$checksum';
    // }

    $result = $this->apim("modifyDatastreamByReference", $opt, "modify binary datastream - $pid/$dsid");
    if ($result)
      return $result->modifiedDate;
    else
      return null;
  }

  /**
   * set the state of a datastream (active/inactive/deleted)
   * @param string $pid
   * @param string $dsid datastream id
   * @param string $state new state (A/I/D)
   * @param string $message log message
   * @return date modified on success
   */
  public function setDatastreamState($pid, $dsid, $state, $message) {
    $opt = new setDatastreamState();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    $opt->dsState = $state;
    $opt->logMessage = $message;

    $result = $this->apim("setDatastreamState", $opt, "set datastream state for $pid/$dsid to $state");
    if ($result)
      return $result->modifiedDate;
    else
      return null;
  }

  /**
   * enable or disable datastream versioning
   * @param string $pid
   * @param string $dsid datastream id
   * @param boolean $versionable
   * @param string $message log message
   * @return date modified on success
   */
  public function setDatastreamVersionable($pid, $dsid, $versionable, $message) {
    $opt = new setDatastreamVersionable();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    $opt->versionable = $versionable;
    $opt->logMessage = $message;

    $result = $this->apim("setDatastreamVersionable", $opt,
    "set datastream versionable to " . ($versionable ? "true" : "false") . " for $pid/$dsid");
    if ($result)
      return $result->modifiedDate;
    else
      return null;
  }

  /**
   * add a new datastream to an object
   * @param string $pid
   * @param string $dsid id for new datastream
   * @param string $label datastream label
   * @param boolean $versionable make datastream be versionable?
   * @param string $mimetype
   * @param string $format description of format
   * @param string $location content location (url/upload id)
   * @param string $controlgroup (optional, defaults to M [managed])
   * @param string $checksumtype (optional, defaults to MD5) [checksums broken in Fedora2...]
   * @param string $checksum value of the checksum
   * @param string $message log message
   * @return new datastream id on success
   */
  public function addDatastream($pid, $dsid, $label, $versionable, $mimetype, $format, $location,
        $controlgroup = FedoraConnection::MANAGED_DATASTREAM,
        $state = FedoraConnection::STATE_ACTIVE,
        $checksumtype = "MD5", $checksum = null, $message = null) {
    $opt = new addDatastream();
    $opt->pid = $pid;
    $opt->dsID = $dsid;
    //   $opt->altIDs;  // ?
    $opt->dsLabel = $label;
    $opt->versionable = $versionable;
    $opt->MIMEType = $mimetype;
    $opt->formatURI = $format;
    $opt->dsLocation = $location;
    $opt->controlGroup = $controlgroup;
    $opt->dsState = $state;
    $opt->checksumType = $checksumtype;
    $opt->checksum = $checksum;
    if ($opt->checksum == null) $opt->checksum = 'NULL';
    $opt->logMessage = $message;

    $result = $this->apim("addDatastream", $opt, "add datastream $label to $pid");
    if ($result)
      return $result->datastreamID;
    else
      return null;
  }

  /**
   * completely purge an object from Fedora
   * @param string $pid
   * @param string $message log message
   * @param boolean $force remove even if there are dependencies (optional, defaults to false)
   * @return date purged on success
   */
  public function purge($pid, $message, $force = false) {
    $opts = new purgeObject();
    $opts->pid = $pid;
    $opts->logMessage = $message;
    $opts->force = $force;
    $result = $this->apim("purgeObject", $opts, "purge $pid");
    if ($result)
      return $result->purgedDate;
    else
      return null;
  }

  public function purgeDatastream($pid, $dsid, $starttime, $endtime, $message, $force = false) {
    $opts = new purgeDatastream();
    $opts->pid = $pid;
    $opts->dsID = $dsid;
    $opts->startDT = $starttime;
    $opts->endDT = $endtime;
    $opts->logMessage = $message;
    $opts->force = $force;

    $result = $this->apim("purgeDatastream", $opts, "purge datastream $dsid on $pid from $starttime to $endtime");
    if ($result) return  $result->purgedVersionDate;
    else return null;
  }


  /**
   * get the full xml for a fedora object - calls getObjectXml
   * @param string fedora pid
   * @return string object xml
   */
  public function getXml($pid) {
    $opts = new getObjectXml();
    $opts->pid = $pid;
    $result = $this->apim("getObjectXml", $opts, "get object xml for $pid");
    if ($result) return $result->objectXML;
    else return null;
  }


  /** relation api calls - new in Fedora3 **/

  /**
   * get relationships for an object
   * @param string fedora pid
   * @param string relationship (optional; by default, returns all)
   * @return array of RelationshipTuple
   */
  public function getRelationships($pid, $relationship = null) {
    $opts = new getRelationships();
    $opts->pid = $pid;
    $opts->relationship =  $relationship;
    $result = $this->apim("getRelationships", $opts, "getRelationships for $pid");
    if ($result) {
      // force relationships to always be an array (see note in findObjects function)
      if (!is_array($result->relationships) && !is_null($result->relationships)) {
  $result->relationships = array($result->relationships);
      }
      return $result->relationships;
    }
    else return null;
  }

  /**
   * add a new relationship to an object's RELS-EXT
   * @param string fedora pid
   * @param string relationship to add
   * @param string object of the new relation
   * @param boolean is the object a literal (instead of another object); optional, defaults to false
   * @param string datatype (optional)
   * @return boolean relationship was added
   */
  public function addRelationship($pid, $relationship, $object,
          $isLiteral = false, $datatype = null) {
    $opts = new addRelationship();
    $opts->pid = $pid;
    $opts->relationship = $relationship;
    $opts->object = $object;
    $opts->isLiteral = $isLiteral;
    $opts->datatype = $datatype;
    $result = $this->apim("addRelationship", $opts, "addRelationship: $pid $relationship $object");
    if ($result) return $result->added;
    else return null;
  }

  /**
   * purge a relationship from an object
   * @param string fedora pid
   * @param string relationship to purge\
   * @param string object of the relation
   * @param boolean is the object a literal (instead of another object); optional, defaults to false
   * @param string datatype (optional)
   * @return boolean relationship was purged
   */
  public function purgeRelationship($pid, $relationship, $object, $isLiteral = false, $datatype = null) {
    $opts = new purgeRelationship();
    $opts->pid = $pid;
    $opts->relationship = $relationship;
    $opts->object = $object;
    $opts->isLiteral = $isLiteral;
    $opts->datatype = $datatype;
    $result = $this->apim("purgeRelationship", $opts, "purgeRelationship: $pid $relationship $object");
    if ($result) return $result->purged;
    else return null;
  }


  /**
   * Upload a file or other data to Fedora for use in ingesting or modifying a
   * datastream.
   *
   * @param string $payload full path to file OR upload content as string
   * @return string upload id
   */
  public function upload($payload) {
    /* Note: using curl because php options for multipart-post had very bad memory problems
     curl is much faster and more efficient for this task */
    $ch = curl_init($this->upload_url);

    // no data? don't even think about it
    if (empty($payload)) {
        throw new FedoraException('Attempting to upload empty payload to Fedora');
    }
    // if payload is data instead of a file, write to a temp file and let curl handle it
    // FIXME: this could have an unexpected result when a filename/path is incorrect...
    if (! is_file($payload)) {
        $tmpfname = tempnam("/tmp", 'fedora-upload');
        $handle = fopen($tmpfname, 'w');
        fwrite($handle, $payload);
        fclose($handle);
        $filepath = $tmpfname;
    } else {
        $filepath = $payload;
    }
    $data = '@' . $filepath;

    curl_setopt_array($ch, array(CURLOPT_POST => 1,
         CURLOPT_POSTFIELDS =>  array('file' => $data),
         CURLOPT_USERPWD => $this->config["username"] . ":"
                  . $this->config["password"],
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_SSLVERSION => 4,
         )
          );
    $result = curl_exec($ch);
    // if there was an error, return null rather than the text of the error page
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // if a temporary file was created, remove it
    if (isset($tmpfname)) unlink($tmpfname);

    switch ($code) {
        // accept any of 200/201/202 as success code (API docs say 202, fedora 3.4 returns 201)
        case 200:
        case 201:
        case 202:
            return trim($result); // id of newly uploaded file - remove newline or fedora will choke
        case 401:
            throw new FedoraNotAuthorized('Upload');
        case 403:
            throw new FedoraAccessDenied('Upload');
        default:
            throw new FedoraException("Error on upload; HTTP response $code: $result");
    }
  }


  /**
   * retrieve a url (e.g., rest api) using CURL and configured authentication
   * @param string $url url to be retrieved
   * @param string $filename (optional) save contents to specified file
   * @return boolean success or failure
   */
  private function getUrlContents($url, $filename = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
         CURLOPT_USERPWD => $this->config["username"]
           . ":" . $this->config["password"],
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_SSLVERSION => 4,
         )
          );

    if ($filename) {
      $file = fopen($filename, 'w');
      curl_setopt_array($ch, array(
           CURLOPT_BINARYTRANSFER => true,
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_FILE => $file,
           )
      );
      $result = curl_exec($ch);
      fclose($file);


      // if 404, we don't want 404 error page contents - truncate any data written
      if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == "404") {
  // NOTE: closing and re-opening the file, otherwise truncation has no effect
  $file = fopen($filename, 'w');
  ftruncate($file, 0);
  fclose($file);
      }
      // FIXME: probably should handle other error codes

      return $result;
    } else {
      return curl_exec($ch);
    }

  }

  // convenience wrapper around risearch query call
  public function risearch($query) {
    return $this->risearch->search($query);
  }



  /**
   * generate a public datastream url based on configured fedora location
   * @param string $pid object pid
   * @param string $dsid datastream id
   * @param boolean $force_ssl optional, defaults to false - retrieve over nonssl
   * @return string
   */
  public function datastreamUrl($pid, $dsid, $force_ssl = false) {
    if ($force_ssl && $this->config["protocol"] == "https" && isset($this->config["nonssl_port"])) {
      // if force_ssl is specifed, and configured protocol is https and nonssl_port is specified
      // use http and nonssl port for datastream location
      $protocol = "http";
      $port = $this->config["nonssl_port"];
    } else {
      // otherwise, use configured protocol and port (either http or https but nonssl port not specified)
      $protocol = $this->config["protocol"];
      $port = $this->config["port"];
    }

    //use proxy if available, no port
    if (isset($this->config["proxy_server"])) {
       $location = $protocol . "://" . $this->config["proxy_server"]  . "/fedora";
    }
    // construct base location based on protocol & port determined above
    else {
       $location = $protocol . "://" . $this->config["server"]  . ":" . $port . "/fedora";
    }
    // REST API url for getDatastreamDissemination
    return $location . "/objects/" . $pid . "/datastreams/" . $dsid . "/content";
  }



  /**
   * generate a public object url based on configured fedora location
   * @param string $pid object pid
   * @param boolean $force_ssl optional, defaults to false - retrieve over nonssl
   * @return string
   */
  public function objectUrl($pid, $force_ssl = false) {
    if ($force_ssl && $this->config["protocol"] == "https" && isset($this->config["nonssl_port"])) {
      // if force_ssl is specifed, and configured protocol is https and nonssl_port is specified
      // use http and nonssl port for object location
      $protocol = "http";
      $port = $this->config["nonssl_port"];
    } else {
      // otherwise, use configured protocol and port (either http or https but nonssl port not specified)
      $protocol = $this->config["protocol"];
      $port = $this->config["port"];
    }

    //use proxy if available, no port
    if (isset($this->config["proxy_server"])) {
       $location = $protocol . "://" . $this->config["proxy_server"]  . "/fedora";
    }
    // construct base location based on protocol & port determined above
    else {
        $location = $protocol . "://" . $this->config["server"]  . ":" . $port . "/fedora";
    }
    // REST API url for getObjectProfile
    return $location . "/objects/" . $pid;
  }



  /**
   * generate an internal/localhost datastream url (for moving content within Fedora)
   * @param string $pid object pid
   * @param string $dsid datastream id
   * @return string
   */
  public function localDatastreamUrl($pid, $dsid) {
    if ($this->config["protocol"] == "https" && isset($this->config["nonssl_port"])) {
      // if configured protocol is https and nonssl_port is specified, use http and nonssl port
      $protocol = "http";
      $port = $this->config["nonssl_port"];
    } else {
      // otherwise, use configured protocol and port (either http or https but nonssl port not specified)
      $protocol = $this->config["protocol"];
      $port = $this->config["port"];
    }

    return $protocol . "://127.0.0.1:" . $port . "/fedora/get/" . $pid . "/" . $dsid;
  }

}
