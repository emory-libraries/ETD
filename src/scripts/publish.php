#!/usr/bin/php -q
<?php


// ZendFramework, etc.
ini_set("include_path", "../app/::../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

require("Zend/Loader.php");
Zend_Loader::registerAutoload();

require_once("Zend/Console/Getopt.php");

require_once("models/etd.php");
require_once("models/ProQuestSubmission.php");
require_once("api/FedoraConnection.php");

$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);
$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port,
			       $fedora_cfg->protocol, $fedora_cfg->resourceindex);
Zend_Registry::set('fedora', $fedora);


$opts = new Zend_Console_Getopt(
  array(
    'all|a'    	       => 'Run all steps in the proper order (default action)',
    'confirmgrad|c'    => 'Confirm Graduation [pids]',
    'proquest|q'       => 'Submit to Proquest [pids]',
    'publish|p'	       => 'Publish [pids]',
    'orphan|o'	       => 'Check for "orphaned" ETDs (graduate not in feed)',
    'file|f=s'	       => 'Registrar feed /path/to/file',
    'date|d=s'	       => 'Date for calculating recent grads (defaults to current date)',
    'verbose|v'	       => 'Verbose output',
    'noact|n'	       => "Test/simulate - don't actually do anything (no actions)",
    'tmpdir|t=s'       => "Temp directory for proquest files (default: /tmp/pqsubmission-YYYYMMDD)",
  )
);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
  In the default mode, $scriptname will find recent graduates from the
  specified registrar feed, find their corresponding ETD records, and
  run those records through all the steps in the publication process.
    example usage:      $scriptname -f /path/to/feed.csv

  The individual actions (or any combination of them) may be run on
  records specified by pid.
    example usage:      $scriptname -q pid1 pid2 pid3

  In test mode, no records will be changed, no files will be ftped,
  and no emails will be sent.  (This mode is mainly useful to check
  for recent graduates in the feed and their corresponding ETD
  records and for testing ProQuest submission output.)

";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

$do_all = false;
// if no mode is specified, run all steps
// set this in the useropts array for simplicity of processing below
if (!$opts->confirmgrad && !$opts->proquest && !$opts->publish && !$opts->orphan) {
  $do_all = true;
}

if ($opts->tmpdir) {
  $tmpdir = $opts->tmpdir;
} else {
  // default location for tmpdir
  $tmpdir = "/tmp/pqsubmission-" . date("Ymd", time()) . "/";
}


// if not using file, pids should be specified
$pids = $opts->getRemainingArgs();
$filename = $opts->file;

if ($do_all) {
  if ($filename == "") {
    print "Error! filename must be specified when running all steps\n";
    echo $usage;
    exit;
  } 
  if (count($pids) != 0) {
    print "Warning: specified pids  will be ignored - will find records in feed\n";
  }
} elseif (count($pids) == 0) {
  print "Error! pids must be specified for this mode\n";
  echo $usage;
  exit;
}


// find graduates and get etd records from fedora
if ($do_all) {	
  if ($opts->verbose) print "Finding pids from Registrar feed\n";
  $etds = get_graduate_etds($filename, $opts->date);
  if (count($etds) == 0) {
    print "\nNo records to be processed\n";
  } elseif ($opts->verbose) {
    print "  Found " . count($etds) . " records\n";
  }
  
}


// if there are any records to be processed
if (count($etds)) {

  if ($do_all || $opts->confirmgrad) 	confirm_graduation($etds);

  if ($do_all || $opts->proquest)	submit_to_proquest($etds);

  if ($do_all || $opts->publish) 	publish($etds);

  // save etd records with whatever changes were made
  foreach ($etds as $etd) {
    $actions = array();
    // construct a save message based on what was actually done
    if ($opts->confirmgrad)  $actions[] = "confirmed graduation";
    if ($opts->proquest)     $actions[] = "submitted to ProQuest";
    if ($opts->publish)      $actions[] = "published";
    $etd->save(implode('; ', $actions));
  }
}

// look for any approved records still in the system ('orphaned' theses)
if ($do_all || $opts->orphan) 		find_orphans();  






/**
 * find etd pids for recent graduates in the registrar feed
 *
 * @param $filename registrar feed
 * @param $refdate reference date for finding recent graduates (optional)
 * @return array of fedora pids corresponding to graduates found
 */
function get_graduate_etds($filename, $refdate = null) {
  global $opts;


  // degrees for which we should expect an ETD
  $etd_degrees = array("PHD", "MA", "MS");

  // field order in registrar feed
  $netid 		=  0;
  $lastname 		=  1;
  $firstname		=  2;
  $email		=  3;
  $emplid		=  4;
  $major		=  5;
  $major2		=  6;
  $comajor		=  7;
  $minor		=  8;
  $term			=  9;		// 4-digit code for year/semester
  $degree_status	= 10;		// awarded or revoked
  $degree		= 11;
  $honors		= 12;
  

  if ($refdate) { 	  // calculate year-month relative to date specified
    $date = date("Y-m", strtotime($refdate, 0));
  } else { 	  // if no date is specified, use today's date
    $date = date("Y-m");
  }

  // calculate the term most recently *completed* (or about to end) relative to the specified date

  list($year, $month) = split('-', $date);
  $last_term =  last_semester($year, $month);

  if ($opts->verbose) {
    print "   Finding graduates for most recently completed term relative to $date (semester code $last_term)\n\n";
  }

  //open alumni feed
  $fp = fopen($filename, 'r');
  if ($fp == false)
    die("Could not open file for reading: $filename\n");
  
  $etds = array();
  
  while (($data = fgetcsv($fp, 1500, ",")) !== FALSE) {
    if (count($data) < 2) continue;	// skip blank lines
    if ($data[$degree_status] == "AW"  // degree status = awarded
	&&  $data[$term] == $last_term  // graduate of most recently ended semester
	&&  in_array($data[$degree], $etd_degrees)	// one of the degrees for which we expect ETDs
	) {		      // found a relevant graduate 

      $name_degree = $data[$lastname] . ", " . $data[$firstname] . " (" . $data[$major] . ")";
	  
      if ($opts->verbose) print "   Found graduate " . $data[$netid] . " $name_degree\n";

      // find fedora record id, add to $pids
      $etd = etd::findUnpublishedByAuthor($data[$netid]);
      // only allowing one unpublished record per student at a time, so this should be safe
      $count = count($etd);
      if ($count == 0) {
	/* NOTE: no longer filtering by pilot departments; we will probably get lots of warnings
	 	 from now until electronic submission becomes mandatory 
	 */
	if ($opts->verbose)  print "\tWarning: no ETD found for $name_degree\n";
      } elseif ($count == 1) {			// what we expect 
	if ($opts->verbose)  print "\tFound etd record $etdpid for $name\n";
	$etds[] = $etd;
      } else {		      // should never happen (except maybe in development)
	if ($opts->verbose)  print "\tWarning: found more than one record for $name_degree (shouldn't happen)\n";
      }
    } elseif ($data[$degree_status] == "RE") {      // degree status revoked
      // note: no handling for this yet -- what should be done?
      print "   Error: found a revoked degree for " . $data[$lastname] . ", " . $data[$firstname] . " (" . 
	$data[$degree] . ", " . $data[$major] . ")
	There is not yet any code to handle this.\n";
    }
  }	// finished processing feed

  return $etds;
}


/**
 * determine the most-recently completed semester relative to specified month & year
 *
 * @param $year 4-digit year
 * @param $month numerical month
 * @return string 4-digit registrar semester code
 */
function last_semester($year, $month) {
  global $opts;

  if ($month < 5) { // before May
    $semester = "FALL";
    $year--;
    $grad_month = "12";
  } elseif ($month >= 5 && $month < 8) { // after/during May but before August
    $semester = "SPRING";
    $grad_month = "05";
  } elseif ($month >= 8 && $month < 12) {	// after/during August but before December
    $semester = "SUMMER";
    $grad_month = "08";
  } elseif ($month == 12) {	// December
    $semester = "FALL";
    $grad_month = "12";
  }

  if ($opts->verbose)  print "   Most recently completed term is $semester $year\n";
  $publish_date = "$year-{$grad_month}-31";
  return semester_code($year, $semester);
}

// semester logic based on decode_semester from reserves scripts

/*
OPUS uses a strange format to encode semester and year information.  This function will translate the 
encode value to something useful 

We can translate Term to verbiage such as "Fall 2005" if you want.  The
Term code itself is easy to interpret.  Term consists of CYYM where
C is century (first two digits of year) where C value of '5' is
century of '20'
    YY is last two digits of year
    M value of '1' is Spring term
    M value of '6' is Summer term
    M value of '9' is Fall term
    M value of '0' is Interim 
    
    Thus,
    5051 is Spring 2005
    5056 is Summer 2005
    5059 is Fall 2005
    5061 is Spring 2006
    5066 is Summer 2006 
*/

/**
 * convert year & term into registrar code
 * @param string $year 4-digit year
 * @param string $term SPRING, SUMMER, FALL, or INTERIM
 * @return string 4-digit register term code
 */
function semester_code ($year, $term) {
  $century = substr($year, 0, 2);
  $year = substr($year, 2, 2);

  $C = $century - 15;	// century code 5 = 20
  $M = array('INTERIM' => 0, 'SPRING' => 1, 'SUMMER' => 6, 'FALL' => 9);

  $code = $C . $year . $M{"$term"};
  return $code;
} 





/** mark graduation as confirmed (just adds an event in the history)
 */
function confirm_graduation(array $etds) {
  global $opts;
  if ($opts->verbose) print "\nConfirming graduation\n";
  if (!$opts->noact) {
    foreach ($etds as $etd) {
      $etd->confirm_graduation();	// no status returned currently (necessary?)
    }
  }
  
}

/** do everything needed to publish an etd (all the logic handled in etd model class)
 */
function publish(array $etds) {
  global $opts;
  if ($opts->verbose) print "\nPublishing\n";
    
  if (!$opts->noact) {
    foreach ($etds as $etd) {
      $etd->publish();	// currently no return value / status -- needed?
    }
  }
}

/** create a proquest submission package (zip of PQ submission xml + files), ftp to PQ, and send email
 */
function submit_to_proquest(array $etds) {
  global $opts;
  if ($opts->verbose) print "\nSubmitting to ProQuest\n";
  if ($opts->verbose) print "   Temporary files will be created in $tmpdir\n";
  rdelete($tmpdir);
  mkdir($tmpdir);
    
  $pq_submissions = array();		// store info for packing list to send in PQ email
  $pq_filetopid = array();		// store filename -> pid relation

  // in test mode: create the metadata & zip files, but don't change the records, ftp, or send email
    
  foreach ($etds as $etd) {
    if ($verbose) print "   Preparing ProQuest submission file for " . $etd->pid . "\n";
    $submission = new ProQuestSubmission();
    $submission->initializeFromEtd($etd);
    // still to do: save xml, get binary files from fedora, create zip file
  }
    
  // batch post-processing for proquest submissions 
    
  if ($opts->noact && $opts->verbose) {
    print "   Test mode, so not ftping submission zip files to ProQuest\n";
  } else {
    //  - ftp all zip files to PQ	(with error checking)
    if ($opts->verbose) print "   Ftping all submission zip files to ProQuest\n";
    // ftp 
    //      $success = proquest_ftp($tmpdir);
      

  }
    
  if ($opts->noact && $opts->verbose) {
    print "   Test mode, so not emailing submission list to ProQuest\n";

    // todo: generate email 
    //      print "\n     Packing list email:\n" . proquest_email($pq_submission, $noact) . "\n";
  } else {
    //  - send email with packing list
    if ($verbose) print "   Sending submission list email to ProQuest\n";
    //      proquest_email($pq_submission);

    if (!$opts->noact) {
      // add proquest submission to history for each record
      //proquest_log($pids);
    }
      
  }
}


/**
 * find records that have been approved that weren't published (e.g., author is not yet in registrar feed)
 */
function find_orphans() {
  global $opts;
  if ($opts->verbose) print "\nChecking for 'orphaned' ETD records\n";

  $orphans = etd::findByStatus("approved");
  $count = count($orphans);
  
  if ($count) {
    print "  Found " . $count . " approved record" . ($count != 1 ? "s" : "") . "\n";
    foreach ($orphans as $etd) {
      print "    " . $etd->author() . " " . $etd->pid . "\n";
    }
  } elseif ($opts->verbose) {
    print "  No approved records found\n";
  }
}
