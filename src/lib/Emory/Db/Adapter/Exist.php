<?php

/**
  * @category EmoryZF
  * @package Emory_Db
  */

class ExistDbException extends Exception {};

 /**
  * XmlRpc client to eXist (native xml database)
  * - modeled loosely after Zend Db Adapters, as appropriate
  * @author Rebecca Sutton Koeser, June 2008
  * @package Emory_Db
  */
class Emory_Db_Adapter_Exist {

  /**
   * xmlrpc client object
   * @var Zend_XmlRpc_Client
   */
  private $xmlrpc;

  /**
   * name of the database to use in eXist
   * @var string
   */
  private $db;

  // FIXME: make more consistent with other DB constructors
  // FIXME2: required parameters ?
  /**
   * Constructor.
   * 
   * $config is an array of key/value pairs
   * containing configuration options.  These options are common to most adapters:
   *
   * host           => (string) What host to connect to, defaults to localhost
   * port           => (string) What port to connect to, defaults to 8080
   * webapp         => (string) Name of the webapp where exist is installed, defaults to exist
   * dbname         => (string) The name of the database to use
   * username       => (string) Connect to the database as this username.
   * password       => (string) Password associated with the username.
   *
   * @param  array $config An array with configuration data
   * @throws ExistDbException
   */
  public function __construct($config) {
    // default values if not set
    if (! isset($config["host"]))   $config["host"] = "localhost";
    if (! isset($config["port"]))   $config["port"] = "8080";
    if (! isset($config["webapp"])) $config["webapp"] = "exist";
    // required fields
    if (! isset($config["username"])) throw new ExistDbException("username must be specified");
    if (! isset($config["password"])) throw new ExistDbException("password must be specified");

    // NOTE: should dbname be *required*?  Shouldn't necessarily have to have this...
    // store database name if there is one
    if (isset($config["dbname"]))
      $this->db = $config["dbname"];	
    
    $xmlrpc_uri = "http://" . $config["host"] . ":" . $config["port"] . "/" . $config["webapp"] . "/xmlrpc";
    $this->xmlrpc = new Zend_XmlRpc_Client($xmlrpc_uri);
    $http = $this->xmlrpc->getHttpClient();
    $http->setAuth($config["username"], $config["password"]);

    // using xmlrpc server proxy to simplify xmlrpc calls
    $this->exist = $this->xmlrpc->getProxy();
  }

  /**
   * retrieve an entire document from exist
   * @param string $name document name relative to exist database
   * @param array $options
   * @throws ExistDbException
   */
  public function getDocument($name, $options = array()) {
    $params = new Zend_XmlRpc_Value_Struct($options);
    $docname = "/db/" . $this->db . "/" . $name;	// convert document name to full path
    try {
      return $this->exist->getDocument($docname, $params);
    } catch (Zend_XmlRPc_Client_FaultException $e) {
      $message = str_replace("org.exist.EXistException: ", "", $e->getMessage());
      throw new ExistDbException($message);
    }
  }

  /**
   * execute an xquery in exist
   * @param string $xquery query
   * @return int reference identifier
   */
  public function executeQuery($xquery) {	// encoding ?
    return $this->exist->executeQuery($xquery);	// FIXME: store most recent result id ?
  }

  /**
   * return specified number of results from a result set created with executeQuery
   * @param int $refid reference id from executeQuery
   * @return xml result
   */
  public function getHits(int $refid) {
    return $this->exist->getHits($refid);
  }

  /**
   * force a result set to be released
   * @param int $refid reference id from executeQuery
   */
  public function releaseQueryResult(int $refid) {
    return $this->exist->releaseQueryResult($refid);
  }

  /**
   * execute a query and return a specified set of results
   * @param string $xquery
   * @param int $howmany
   * @param int $start
   * @param array $options
   */
  public function query($xquery, $howmany = null, $start = null, array $options = array()) {
    $params = new Zend_XmlRpc_Value_Struct($options);
    $query = new Zend_XmlRpc_Value_base64($xquery);
    if (is_null($howmany))  $howmany = 10;
    if (is_null($start))    $start = 1;
    
    $result = $this->exist->query($query, (int)$howmany, (int)$start, $params);

    /* simulate the "nowrap" option available in other xquery modes:
       strip out the exist tags that wrap the output    */
    if (isset($options["nowrap"]) && $options["nowrap"]) {
      $result = $this->nowrap($result);
    }
    return $result;
  }


  /**
   * return the exist db path according to configured db name
   * (useful for building xqueries)
   * @return string 
   */
  public function getDbPath() {
    return "/db/" . $this->db;
  }

  public function nowrap($text) {
    $text = preg_replace("|^\s*<exist:result[^>]*>|", "", $text);
    $text = preg_replace("|</exist:result>$|", "", $text);
    return $text;
  }
}