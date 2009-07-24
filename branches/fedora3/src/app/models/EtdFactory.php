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
   * generic init - use for etd or user
   *
   * @param string $pid fedora id
   * @param string $class base classname (e.g., etd, user, etc.)
   */
  public static function init($pid, $class) {
    switch($class) {
    case "etd":  return EtdFactory::etdByPid($pid);
    case "user": return EtdFactory::userByPid($pid);
    default:
      // attempt to initialize return whatever class was specified
      return new $class($pid);
    }
  }
   

  /**
   * construct and return etd or honors etd as appropriate
   * @param string $pid fedora id
   * @return etd or honors_etd
   */
  public static function etdByPid($pid) {
    $config = Zend_Registry::get("config");
    $fedora = Zend_Registry::get("fedora");

    $factory = new EtdFactory();
    if ($factory->isHonorsEtd($pid)) {
      return new honors_etd($pid);
    } else {
      return new etd($pid);
    }
    
  }

   /**
   * construct and return user or honors user as appropriate,
   * based on etd that the user object is associated with
   *
   * @param string $pid fedora id
   * @return user or honors_user
   */
  public static function userByPid($pid) {
    $user = new user($pid);

    $factory =  new EtdFactory();
    // if related etd is honors, re-initialize user object as honors user
    // NOTE: using relation in rels-ext rather than going to the risearch
    if ($factory->isHonorsEtd($user->rels_ext->etd)) {
      $user = new honors_user($pid);
    }

    return $user;

  }

  /**
   * check if an ETD is a member of the honors collection
   * @param string $pid
   * @return boolean
   */
  public function isHonorsEtd($pid) {
    $config = Zend_Registry::get("config");
    $fedora = Zend_Registry::get("fedora");

    /** convert pids into format required by RIsearch
        (if already in that format, should still work)  */
    $query_pid = $this->pid_to_fedorapid($pid);
    $collection_pid = $this->pid_to_fedorapid($config->collections->college_honors);

    // is the current pid is a member of the honors collection object?
    $query = "<" . $query_pid . "> <fedora-rels-ext:isMemberOf> <" . $collection_pid . ">";
    $rdf = $fedora->risearch->triples($query);
    if ($rdf != null) {
      $ns = $rdf->getNamespaces();  // get the namespaces directly from the simplexml object
      $descriptions = $rdf->children($ns['rdf']);
      
      if (count($descriptions) == 0) {
	// no matches found - NOT a member of honors collection
	return false;
      } else {
	// pid IS a member of honors collection
	return true;
      }
    } else {
      return false;
      // FIXME: should probably generate a notice or warning or something...
    }
    
  }

  /**
   * convert pid to format needed for risearch, but only if not
   * already in that format
   *
   * @param string $pid pid to convert
   * @return string
   */
  private function pid_to_fedorapid($pid) {
    if (!preg_match("|^info:fedora/|", $pid)) 
      return "info:fedora/" . $pid;
    else return $pid;
  }

  
  
}
