<?php

  /**
   * Catch common errors that can happen when ingesting a new object
   * into Fedora.
   */

class Etd_Controller_Action_Helper_IngestOrError
	extends Zend_Controller_Action_Helper_Abstract {
  private $logger;

  public function __construct(){
    $this->logger = Zend_Registry::get('logger');
  }
  
  public function direct($object, $message, $objtype = "record", &$errtype = null) {
    return $this->ingest_or_error($object, $message, $objtype, $errtype);
  }


  /**
   * attempt to ingest the fedora object; handle known errors if they occur
   *
   * @param $object foxml object to be ingested
   * @param string $message save message
   * @param string $objtype label for object to be used in error messages/logs
   * @param string $errtype passed by reference, returned with exception class if any
   * @return string fedora pid on success, false on failure
   */
  public function ingest_or_error($object, $message, $objtype, &$errtype) {
    try {
      $pid = $object->save($message);	// save and get pid
      $this->logger->info("Created new $objtype $pid");
      return $pid;
    } catch (Exception $e) {
      $errtype = get_class($e);
      $debug_msg = $e->getMessage();

      switch ($errtype) {
      case "PersisServiceUnavailable":
	$err_message = "Could not create $objtype because Persistent Identifier Service is not available";
	break;
      case "PersisServiceUnauthorized":
	// not authorized - most likely misconfigured, bad password
	$err_message = "Could not create $objtype because of an authorization error with Persistent Identifier Service";
	break;
      case "PersisServiceException":
	// generic persis error - most likely misconfigured, bad password
	$err_message = "Could not create $objtype because of an error accessing Persistent Identifier Service";
	break;
      case "FedoraObjectNotValid":
	$err_message = "Could not create $objtype (FedoraObjectNotValid)";
	// ??$this->logger->err("Could not ingest $objtype : FedoraObjectNotValid");
	break;
      case "FedoraObjectNotFound":
	$err_message = "Could not create $objtype (FedoraObjectNotFound)";
	/** FIXME: how to handle?
	 $this->view->errors[] = "Could not create record.";
	 $this->view->xml = $etd->saveXML(); */
	// ??	$this->logger->err("Could not create etd record : FedoraObjectNotFound");
	break;

      default:
	$err_message = "Unknown error";
      }
    }

      
    $flashMessenger = $this->_actionController->getHelper("flashMessenger");
    $flashMessenger->addMessage("Error: $err_message");
    $this->logger->err($err_message);
    $this->logger->debug($debug_msg);

    // for persis errors, redirect to appropriate error page
    if (preg_match("/^Persis/", $errtype)) {
      // redirect to an error page
      $redirector = $this->_actionController->getHelper("redirector");
      $redirector->gotoRouteAndExit(array("controller" => "error",
					  "action" => "unavailable"));
    }
  
    return false;
  }
}
