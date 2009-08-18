#!/usr/bin/php -q
<?php
/**
 *  Calculate & add missing datastream checksums for managed datastreams
 *  - actually accesses the foxml data files directly and finds the paths
 *    to datastream files in the fedora database
 *
 *  (yes, this is a hack - meant as a one-time clean up script to
 *  compensate for a bug in Fedora 2.2 and an unknown at the time
 *  issue in PHP SOAP's problem with sending nil properly)
 */

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
$getopts = array_merge(array('dbname|d=s'      => 'Fedora mysql database name (required)',
			     'username|u=s' => 'username for Fedora mysql database (required)',
			     'password|p=s' => 'password for Fedora mysql database (required)',
			     'fedorahome|f=s' => 'path to FEDORA_HOME (required)',
			     'tmpdir|t=s' => "tmpdir to inspect output (required for noact mode only)",
			     ),
		       // use default verbose and noact opts from bootstrap
		       $common_getopts
                       );
$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
Scan Fedora foxml files, find managed datastream versions that are missing them,
calculate MD5 checksum, and save the updated foxml back to the filesystem. 
";
try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// double-check for required fields
if ((!$opts->dbname || !$opts->username || !$opts->password || !$opts->fedorahome)  
    || ($opts->noact && !$opts->tmpdir)){
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// interested in foxml files only 
$path = rtrim($opts->fedorahome, '/') . "/data/objects";
$dom = new DOMDocument();
$foxmlns = "info:fedora/fedora-system:def/foxml#";
$tmpdir = $opts->tmpdir;

// iterate over all actual files (ignore directories)
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
$db = new Zend_Db_Adapter_Pdo_Mysql(array(
    'host'     => '127.0.0.1',
    'username' => $opts->username,
    'password' => $opts->password,
    'dbname'   => $opts->dbname,
));
// sql to find a datastream path by token id
$token_sql = "SELECT path FROM datastreamPaths WHERE token = ?";

$obj_count = $ds_count = 0;

foreach ($files as $filename => $object) {
  // load foxml from file & init xpath object for querying
  $dom->load($filename);
  $xpath = new DOMXPath($dom);
  $xpath->registerNamespace("foxml", $foxmlns);
  
  // search for datastreamVersions of managed datastreams that are missing checksums
  $nodelist = $xpath->query('//foxml:datastream[@CONTROL_GROUP="M"]/foxml:datastreamVersion[not(foxml:contentDigest) or foxml:contentDigest/@TYPE="DISABLED"]');
  if ($nodelist->length) {
    $logger->info($filename . " has " . $nodelist->length . " managed datastreamVersion(s) missing checksums");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $dsVersion = $nodelist->item($i);
      // get datastream location token for this datastreamVersion
      $loc_nodes = $xpath->query("foxml:contentLocation", $dsVersion);
      $location = $loc_nodes->item(0);
      if ($location->getAttribute("TYPE") != "INTERNAL_ID") {
	$logger->err("Location type is " . $location->getAttribute("TYPE") .
		      "; can only handle INTERNAL_ID");
	continue;  // skip to next datastream version
      }
      $logger->debug("location token is " . $location->getAttribute("REF"));
      // query fedora database to get datastream path for this datastream id token
      $result = $db->fetchCol($token_sql, $location->getAttribute("REF"));
      if ($result) {
	$dspath = $result[0];
	$logger->debug("path to datastream binary is " . $dspath);
	$md5 =  md5_file($dspath);
	$logger->debug("md5 is $md5");
	
	// get contentDigest, if any, for this datastreamVersion
	$digest_nodes = $xpath->query("foxml:contentDigest", $dsVersion);
	// if digest exists, update it
	if ($digest_nodes->length) {
	  $digest =  $digest_nodes->item(0);
	} else { // otherwise, add new contentDigest node
	  $digest = $dom->createElementNS($foxmlns, "foxml:contentDigest");
	  // insert under datastream version, before contentLocation
	  $digest = $dsVersion->insertBefore($digest, $location);
	}
	$digest->setAttribute("TYPE", "MD5");
	$digest->setAttribute("DIGEST", $md5);

	$ds_count++;
      } else {
	$logger->err("No path found for datastream token " .
		     $location->getAttribute("REF"));
	continue;
      }
      
    } // end looping over datastreamVersions with missing checksums

    if ($opts->noact) {
      // save foxml to tmp dir for perusal ?
      $tmpfile = $tmpdir . basename($filename); 
      $dom->save($tmpfile);
      $logger->info("noact mode, saving new saving foxml to $tmpfile");
      $obj_count++;
    } else {
      // actually save the updated foxml back to original file
      $written = $dom->save($filename);
      if ($written) {
	$logger->info("Successfully updated $filename");
	$obj_count++;
      } else {
	$logger->err("Problem updating $filename");
      }
      
    }
  }
}

$logger->notice("Updated $obj_count objects and $ds_count datastream versions");

?>