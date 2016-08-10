<?php 

  // send xquery requests to eXist database;
  // convert results into a DOM document, process eXist cursor information; detect basic errors
/**
 * Send xquery requests to an eXist db; process cursor information & detect basic errors
 * 
 * This class handles eXist database related functions, including running
 * xqueries, interpreting cursors and detecing basic errors in the returned result.
 * Intended to be used via xmlDbConnection.class.php.
 * 
 * @author Rebecca Sutton Koeser rebecca.s.koeser@emory.edu
*/

class existConnection {

  // connection parameters
  var $host;
  var $port;
  var $db;
  var $coll;
  // whether or not to display debugging information
  var $debug;
  
  // these variables used internally
  var $base_url;
  var $xmlContent;
  var $xml;
  var $xpath;
  var $xsl_result;
  var $xq_rval;
  var $xq_code;
  var $xq_msg;

  // cursor variables
  var $cursor;
  var $count;
  var $position;
  var $quantity;

  // wrap output in exist:result set?
  var $wrap;

  function existConnection($argArray) {
    $this->host = $argArray['host'];
    $this->port = $argArray['port'];
    $this->db = $argArray['db'];
    if (isset($argArray['coll'])) {
      $this->coll = $argArray['coll'];
    }
    $this->debug = $argArray['debug'];

    $myport = '';
    $mycoll = '';
    if ($this->port != '') { $myport = ":$this->port"; }
    if ($this->coll != '') { $mycoll = "/$this->coll"; }	// subsection of a db
    
    if(array_key_exists('exist-wrap', $argArray)) {
      $this->wrap = $argArray['exist-wrap'];
    }
    else {
      $this->wrap = 'yes';
    }

    $this->base_url = "http://$this->host$myport/exist/servlet/db/$this->db$mycoll?_wrap=$this->wrap&";
    
  }

  // send an xquery to eXist & get xml result
  // FIXME: eXist correlative?...
  // taminoConnection returns  tamino error code (0 for success, non-zero for failure)
  function xquery ($query, $position = NULL, $maxdisplay = NULL) {
    $myurl = $this->base_url . "_query=$query&_encoding=UTF-8";
    if (isset($position) && isset($maxdisplay)) {
      $myurl .= "&_start=$position&_howmany=$maxdisplay";
    }
    $myurl .= "&_wrap=no";

    if ($this->debug) {
      print "DEBUG: In function existConnection::xquery, url is " . htmlentities($myurl) . ".<p>";
      print "<a href=\"".htmlentities($myurl)."\">run xquery</a><br>";
    }

    $this->xmlContent = file_get_contents($myurl);
      // FIXME: how to handle errors?      
     // need to check the HTTP return val from file_get_contents; errors give a 400

    if ($this->xmlContent) {
      $this->initializeXML();
    } else {
      print "<p><b>Error:</b> unable to access database.</p>";
      $this->xq_rval = -1;
    }
   return $this->xq_rval;
  }


   // retrieve the cursor & get the total count
   function getCursor () {
     if ($this->xml) {
       $n = $this->xpath->query("/exist:result/@exist:hits");	// total matches
       if ($n) { $this->count = $n->item(0)->textContent; }
       $n = $this->xpath->query("/exist:result/@exist:start");	// start of current set
       if ($n) { $this->position = $n->item(0)->textContent; }
       $n = $this->xpath->query("/exist:result/@exist:count");	// total in current set
       if ($n) { $this->quantity = $n->item(0)->textContent; }
     } else {
       print "Error! existConnection xml variable uninitialized.<br>";
     }
   }


   // create a new domDocument with the raw xmlContent, retrieve tamino messages
   function initializeXML () {
    $this->xml = new domDocument();
    $this->xml->loadXML($this->xmlContent);
    if (!$this->xml) {
      print "existConnection::initializeXML error: unable to parse xml content.<br>";
      $this->xq_rval = 0;	// not a tamino error but a dom error
    } else {
     $this->xpath = new domxpath($this->xml);
     // exist does not return messages and error codes like tamino does
     // initialize cursor (is automatically set)
     if ($this->wrap != "no")	// if exist:result is not included, there is no cursor information
       $this->getCursor();
    }
   }

}
