<?php

require_once("models/foxml.php");
require_once("etd.php");
require_once("honors_etd.php");
require_once("grad_etd.php");

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
    switch ($factory->getEtdType($pid)) {
        case "honors":
            return new honors_etd($pid);
        case "gradschool":
            return new grad_etd($pid);
        default:
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
    // FIXME: what happens when user objects become associated with more than one etd ?!?
    switch ($factory->getEtdType($user->rels_ext->etd)) {
        case "honors":
            return new honors_user($pid);
        case "gradschool":        
        default:
            return $user;
    }
  }

  /**
   * determine type of ETD based on collection membership
   * @param string $pid
   * @return string
   */
  public function getEtdType($pid) {
    $config = Zend_Registry::get("config");
    $fedora = Zend_Registry::get("fedora");

    /** convert pid into format required by RIsearch  */
    $query_pid = $fedora->risearch->pid_to_risearchpid($pid);

    // find all collections this pid is a member of
    $query = "<" . $query_pid . "> <fedora-rels-ext:isMemberOfCollection> *";
    $rdf = $fedora->risearch->triples($query);
    if ($rdf != null) {
      $ns = $rdf->getNamespaces();  // get the namespaces directly from the simplexml object
      $descriptions = $rdf->children($ns['rdf']);

      // find collections and compare against known ETD subcollections until we find a match
      foreach ($descriptions as $d) {
          foreach ($d->children() as $rel) {
              if ($rel->getName() != "isMemberOfCollection") continue;
              $attr = $rel->attributes($ns['rdf']);
              $collection = $fedora->risearch->risearchpid_to_pid($attr[0]);          
              
              switch ($collection) {
                  case $config->collections->college_honors: return "honors";
                  case $config->collections->grad_school: return "gradschool";
              }
          }
      }
    }
    // no matches found - unknown/generic etd
    return;
  }
  
  
}
