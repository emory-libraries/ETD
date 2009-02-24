<?php

require_once("models/foxml.php");
require_once("etd.php");
require_once("honors_etd.php");

/**
 * Factory class to initialize etd or honors etd by pid.
 * Checks if pid is a member of the honors etd collection
 * and then initializes accordingly. 
 */
class EtdFactory {

  /**
   * construct and return etd or honors etd as appropriate
   * @param string $pid fedora id
   * @return etd or honors etd
   */
  public static function etdByPid($pid) {
    $config = Zend_Registry::get("config");
    $fedora = Zend_Registry::get("fedora");

    /** convert pids into format required by RIsearch
        (if already in that format, should still work)  */
    $query_pid = $pid;
    if (!preg_match("|^info:fedora/|", $query_pid)) 
      $query_pid = "info:fedora/$pid";
    $collection_pid = $config->honors_collection;
    if (!preg_match("|^info:fedora/|", $collection_pid)) 
      $collection_pid = "info:fedora/" . $collection_pid;

    // is the current pid is a member of the honors collection object?
    $query = "<" . $query_pid . "> <fedora-rels-ext:isMemberOf> <" . $collection_pid . ">";
    
    $rdf = $fedora->risearch->triples($query);

    if ($rdf == null) {
        // no response from risearch - fall back to generic etd
        trigger_error("No response returned from risearch; cannot determine if $pid is an honors etd", E_USER_WARNING);
        return new etd($pid);
    }

    $ns = $rdf->getNamespaces();  // get the namespaces directly from the simplexml object
    $descriptions = $rdf->children($ns['rdf']);
      
    if (count($descriptions) == 0) {
        // no matches found - NOT a member of honors collection, therefore regular etd
        return new etd($pid);
    } else {
        // pid is a member if honors collection - return an honors etd
        return new honors_etd($pid);
    }

  }
  
}
