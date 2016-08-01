<?php

  /**
   * simple interface for the Fedora Resource Index search
   *
   *
   * NOTE: static functions rely on configuration stored in Zend_Registry
   * - static functions are deprecated
   */

class risearch {

  private $url;
  private $format;

  private $username;
  private $password;

  private $fedora_model_ns = 'info:fedora/fedora-system:def/model#';

  /**
   * constructor
   * @param string $fedora_server hostname
   * @param string $fedora_port port fedora is running on
   * @param string $resourceindex_baseurl webapp path for resource index, usually risearch
   */
  public function __construct($fedora_server, $fedora_port, $resourceindex_baseurl,
    $protocol = "http") {
    $this->url = $protocol . "://" . $fedora_server . ":" . $fedora_port . "/fedora/" . $resourceindex_baseurl;

    // returning as simplexml, so format must be Sparql
    $this->format = "sparql";
  }

  /**
   * enable http auth for accessing risearch
   * @param string $username
   * @param string $password
   */
  public function setAuthCredentials($username, $password) {
    $this->username = $username;
    $this->password = $password;
  }


  /**
   * run a triples query   	(NOTE: MTPstore *only* supports triples/SPO)
   *
   * @param string $query SPO format query
   * @return SimpleXMLElement
   */
  public function triples($query) {
    $search_url = $this->url . "?type=triples&lang=spo&format=RDF/XML"
      . "&query=" . urlencode($query);

    try {
        $result = $this->getData($search_url);
        if ($result) {
              return  simplexml_load_string($result);
        } else {
          return null;
        }
    } catch (Exception $e) {
      print "Error: " . $e->message . "\n";
    }
  }

  public function search($query, $type = "tuples", $lang = "iTQL") {
    $search_url = $this->url . "?type=$type&lang=$lang&format=" . $this->format
      . "&query=" . urlencode($query);

    // fixme: what if response is not valid xml? add try/catch ?
    try {
      return  simplexml_load_string($this->getData($search_url));
    } catch (Exception $e) {
      print "Error: " . $e->message . "\n";
    }
  }


  /**
   * find pids for objects by content model
   * @param string pid for content model object
   * @return array
   */
  public function findByCModel($cmodel) {
      if (strpos($cmodel, "info:fedora/") === false)
          $cmodel = "info:fedora/$cmodel";

      $result = array();

      // tuple query for objects by model relation
      $namespace = "fedora-model";	// risearch alias for fedora model namespace
      $predicate = $this->fedora_model_ns . 'hasModel';
      $query = '* <' . $predicate . '> <' . $cmodel . '>';
      $rdf = $this->triples($query);
      if (is_null($rdf))  {
        trigger_error("No data returned from resource index", E_USER_NOTICE);
        return $result;
      }

      $ns = $rdf->getNamespaces();	// get the namespaces directly from the simplexml object
      $descriptions = $rdf->children($ns['rdf']);

      if (count($descriptions)) {		// matches found
          foreach ($descriptions as $desc) {
              // only attribute is rdf:about pid; add to array of result pids
              $att = $desc->attributes($ns['rdf']);
              $result[] = $this->risearchpid_to_pid((string) $desc->attributes($ns['rdf']));
          }
      }
      return $result;
  }

   /**
   * find a list of content models for a specified object
   * @param string $pid object pid
   * @return array
   */
  public function get_cmodels($pid) {
      $result = array();

      // tuple query for objects by model relation
      //$namespace = "fedora-model";	// risearch alias for fedora model namespace
       $predicate = $this->fedora_model_ns . 'hasModel';
      $query = '<' . $this->pid_to_risearchpid($pid) . '> <' . $predicate . '> *';
      $rdf = $this->triples($query);
      if (is_null($rdf))  {
        trigger_error("No data returned from resource index", E_USER_NOTICE);
        return $result;
      }

      $ns = $rdf->getNamespaces();	// get the namespaces directly from the simplexml object
      $rdf->registerXPathNamespace('model', $this->fedora_model_ns);
      $models = $rdf->xpath('//model:hasModel');
      foreach ($models as $m) {
        $result[] = $this->risearchpid_to_pid((string)$m->attributes($ns['rdf']));
      }
      return $result;
  }


  /**
   * find pids for objects by owner
   * @param string pid for content model object
   * @return array
   */
  public function findByOwner($owner) {
      $result = array();

      // tuple query for objects by owner relation
      $predicate = '<info:fedora/fedora-system:def/model#ownerId>';
      $query = "*  $predicate '$owner'";
      $rdf = $this->triples($query);
      if (is_null($rdf))  {
        trigger_error("No data returned from resource index", E_USER_NOTICE);
        return $result;
      }

      $ns = $rdf->getNamespaces();	// get the namespaces directly from the simplexml object
      $descriptions = $rdf->children($ns['rdf']);

      if (count($descriptions)) {		// matches found
          foreach ($descriptions as $desc) {
              // only attribute is rdf:about pid; add to array of result pids
              $att = $desc->attributes($ns['rdf']);
              $result[] = $this->risearchpid_to_pid((string) $desc->attributes($ns['rdf']));
          }
      }
      return $result;
  }



  /**
   * safely convert normal (short-format) pid to risearch required format
   * @param string $pid
   * @return string
   */
  public function pid_to_risearchpid($pid) {
     if (strpos($pid, "info:fedora/") === false)
          $pid = "info:fedora/$pid";
     return $pid;
  }

  /**
   * safely convert risearch format pid to normal short-format pid
   * @param string $pid
   * @return string
   */
  public function risearchpid_to_pid($pid) {
      return str_replace("info:fedora/", "", $pid);
  }

  /** static functions - deprecated **/

  public static function query($queryString, $type = "tuples", $lang = "sparql") {

    $format = "sparql";

    $config = Zend_Registry::get('fedora-config');
    $search_url = "http://" . $config->server . ":8080" .
      "/fedora/risearch" . $config->resourceindex .
      "?type=$type&lang=$lang&format=$format&query=" . urlencode($queryString);

    try {
      //	return  simplexml_load_file($search_url);
      return  new SimpleXMLElement(file_get_contents($search_url));
    } catch (Exception $e) {
      print "Error: " . $e->message . "\n";
    }

  }


  // run a simple risearch with flush=true to flush the index
  public static function flush($pid) {

    $format = "sparql";
    $query = 'select $obj from <#ri>
  where <info:fedora/' . $pid . '> <rdf:type> $obj';

    $config = Zend_Registry::get('fedora-config');
    $search_url = "http://" . $config->server . ":" . $config->port .
      "/fedora/" . $config->resourceindex .
      "?flush=true&type=$type&lang=$lang&format=$format&query=" . urlencode($queryString);

    // fixme: what if response is not valid xml? add try/catch ?
    try {
      //	return  simplexml_load_file($search_url);
      return  simplexml_load_string(file_get_contents($search_url));
    } catch (Exception $e) {
      print "Error: " . $e->message . "\n";
    }
  }


  /**
   * function to retrieve data via url with optional authentication
   */
  private function getData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,  true);

    if ($this->username && $this->password) {
      curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    }


    $result = curl_exec($ch);
    return $result;
  }

}
