#!/usr/bin/php -q
<?php

/**

  This script is intended to handle the auto-publication of ETDs based
  on a feed from the Registrar that confirms students have graduated.

  By default, it should be run as a cron job in the default mode where
  it will parse the feed, find graduates and their ETD records, and
  then run those ETD records through the appropriate steps in the
  following order:
  
    - confirm graduation (simply add an event to the record history
    that system has confirmed graduation)

    - publish the record (this includes setting publication date,
    calculating embargo end dates, setting status, and adding xacml
    rules for publication)

    - submit to ProQuest (for each record, creates a zipped submission
    packet made up of xml metadata in PQ's custom format, PDF of the
    dissertation, and optionally supplemental files; zip files are
    ftped to a specified PQ server, and an email "packing list" of the
    files is sent both to PQ and to the ETD admin listserv)

  This script can also be used for manual publication or re-submitting
  to ProQuest; any or all of these steps can be done to records
  specified by pid.

  This script should be access Fedora via a privileged maintenance
  account (with etdadmin-level permissions), because it needs to
  access unpublished records, modify the history, and modify the xacml. 

  
  This script should not be used to publish alumni submissions.
 
 */

require_once("bootstrap.php");

require_once("models/ProQuestSubmission.php");
$proquest = new Zend_Config_Xml("../config/proquest.xml", $env_config->mode);

$getopts = array_merge(
array(
    'all|a'    	       => 'Run all steps in the proper order (default action)',
    'confirmgrad|c'    => 'Confirm Graduation [pids]',
    'proquest|q'       => 'Submit to Proquest [pids]',
    'publish|p'	       => 'Publish [pids]',
    'orphan|o'	       => 'Check for "orphaned" ETDs (graduate not in feed)',
    'file|f=s'	       => 'Registrar feed /path/to/file',
    'date|d=s'	       => 'Date for calculating recent grads (defaults to current date)',
    'tmpdir|t=s'       => "Temp directory for proquest files (default: /tmp/pqsubmission-YYYYMMDD)",
    ),  $common_getopts	// use default verbose and noact opts from bootstrap
);
		       

$opts = new Zend_Console_Getopt($getopts);

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
    trigger_error("Specified pids will be ignored - will find records in feed", E_USER_WARNING);
  }
} elseif (count($pids) == 0) {
  print "Error! pids must be specified for this mode\n";
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// global publish date as it should be set on record (relative to current semester)
$publish_date;
// if not doing all steps *but* publishing, need to calculate publish date
if (!$do_all && $opts->publish) {
  last_term($opts->date); 
}


// find graduates and get etd records from fedora
if ($do_all) {
  $logger->info("Finding pids from Registrar feed");
  $etds = get_graduate_etds($filename, $opts->date);
  if (count($etds) == 0) $logger->info("No records to be processed");
  else 			 $logger->info("Found " . count($etds) . " records");
 } else {	// not in do-all mode; initialize etd objects based on pids specified on command line
  $etds = array();
  foreach ($pids as $pid) {
    try {
      $etds[] = new etd($pid);
    } catch (FedoraObjectNotFound $e) {
      $logger->warn("Record not found: $pid");
      //      trigger_error("Record not found: $pid", E_USER_WARNING);
    } catch (FoxmlBadContentModel $e) {
      $logger->warn("Record is not an etd, ignoring: $pid");	
      //      trigger_error("Record is not an etd, ignoring: $pid", E_USER_WARNING);
    }
  }
}

// if there are any records to be processed
if (count($etds)) {

  if ($do_all || $opts->confirmgrad)
    confirm_graduation($etds);
  if ($do_all || $opts->publish) 
    $etds = publish($etds);	// if publication fails, record will not continue through the rest of the process
  if ($do_all || $opts->proquest)
    submit_to_proquest($etds);

  // save etd records with whatever changes were made
  foreach ($etds as $etd) {
    $actions = array();
    // construct a save message based on what was actually done
    if ($opts->confirmgrad)  $actions[] = "confirmed graduation";
    if ($opts->proquest && $etd->mods->submitToProquest())
      // not all records will be submitted to ProQuest (optional for Masters)
      $actions[] = "submitted to ProQuest";
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
 * @return array of approved etd objects belonging to graduates found in the feed
 */
function get_graduate_etds($filename, $refdate = null) {
  global $opts, $logger;

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
  
  // determine the term most recently *completed* (or about to end) relative to the specified date
  $last_term =  last_term($refdate);

  $logger->debug("Finding graduates for most recently completed term " . 
		($refdate ? "relative to $refdate " : "" ) .  "(semester code $last_term)");

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
	  
      $logger->debug("Found graduate " . $data[$netid] . " $name_degree");
      
      // find fedora record id, add to $pids
      $etdSet = new EtdSet();
      $etdSet->findUnpublishedByOwner($data[$netid]);
      // only allowing one unpublished record per student at a time, so this should be safe
      $count = count($etdSet->etds);
      if ($count == 0) {
         /* NOTE: no longer filtering by pilot departments; we will probably get lots of warnings
          from now until electronic submission becomes mandatory 
         */
	$logger->warn("Warning: no ETD found for $name_degree");
      } elseif ($count == 1) {			// what we expect 
        $logger->info("Found etd record " . $etdSet->etds[0]->pid . " for $name_degree");
        if ($etdSet->etds[0]->status() != "approved") {
	  $logger->info("Record is not yet approved, skipping");
	  continue;
        }
        $etds[] = $etdSet->etds[0];
      } else {		      // should never happen (except maybe in development)
        $logger->warn("Found more than one record for $name_degree (this shouldn't happen)");
      }
    } elseif ($data[$degree_status] == "RE") {      // degree status revoked
      // note: no handling for this yet -- what should be done?
      // fixme: is it appropriate to make this an alert instead of a warning?
      $logger->alert("Found a revoked degree for " . $data[$lastname] . ", " . $data[$firstname] . " (" . 
        $data[$degree] . ", " . $data[$major] . ")
        There is not yet any code to handle this.");
    }
  }	// finished processing feed (end while loop)
  
  return $etds;
}

/**
 * calculate last semester code based on current or specified date
 * wrapper function for last_semester with date handling
 *
 * @param $refdate date (in any format strtotime can handle)
 * @return string 4-digit registrar semester code calculated by last semester
 */
function last_term($refdate = null) {
  if ($refdate) { 	  // calculate year-month relative to date specified
    $date = date("Y-m", strtotime($refdate, 0));
  } else { 	  // if no date is specified, use today's date
    $date = date("Y-m");
  }

  // calculate the term most recently *completed* (or about to end) relative to the specified date

  list($year, $month) = split('-', $date);
  return last_semester($year, $month);
}

/**
 * determine the most-recently completed semester relative to specified month & year
 *
 * @param $year 4-digit year
 * @param $month numerical month
 * @return string 4-digit registrar semester code
 */
function last_semester($year, $month) {
  global $opts, $publish_date, $logger;

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

  $logger->info("Most recently completed term is $semester $year");
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





/**
 * mark graduation as confirmed (just adds an event in the history)
 * @param array $etds etd objects
 */
function confirm_graduation(array $etds) {
  global $opts, $logger;
  $logger->info("Confirming graduation");
  foreach ($etds as $etd) {
    // only records that are currently approved should have graduation confirmed
    if ($etd->status() != "approved") {
      $logger->warn("Cannot confirm graduation on record " . $etd->pid . ": status is " .
		    $etd->status() . " instead of approved");
      continue;	// skip to next etd
    }
    if (!$opts->noact) {
      $etd->confirm_graduation();	// note: no status returned (not much to go wrong here)
    }
  }
}

/**
 * do everything needed to publish etds (most of the logic is handled in etd model class)
 * 
 * @param array $etds etd objects
 * @return array of etds that were successfully published
 */
function publish(array $etds) {
  global $opts, $publish_date, $logger;
  $logger->info("Publishing");

  // store all etds that are successfully published 
  $published = array();
  
  foreach ($etds as $etd) {
    // only records that are currently approved should be published
    if ($etd->status() != "approved") {
      $logger->warn("Cannot publish record " . $etd->pid . ": status is " .
		    $etd->status() . " instead of approved");
      continue;	// skip to next etd
    }

    if ($opts->noact) {
      // in noact mode, simulate successful publication of all records but skip all actual processing
      $published[] = $etd;
      continue;
    }
    
    try {
      // official publication date (end of semester), current date
      $etd->publish($publish_date, $opts->date);     // currently no return value / status -- needed?
    } catch (XmlObjectException $ex) {
      $logger->err("Problem publishing record " . $etd->pid . ": " . $ex->getMessage());
      // skip to the next record
      continue;
    }
    
    // if publish succeeded, add to array 
    $published[] = $etd;
    
    // send an email to notify author, and record in history
    $notify = new etd_notifier($etd);
    
    try {
      $notify->publication();
    } catch (Zend_Db_Adapter_Exception $e) {
      // if ESD is not accessible, cannot look up faculty email addresses - notification will fail
      $logger->crit("Error accessing ESD (needed for faculty email addresses); cannot proceed");
      // if ESD is down, this will most likely fail for *ALL* records being processed
      // skip to the next record
      continue;
    } catch (Zend_Exception $ex) {
      $logger->err("There was a problem sending the publication notification email for " . $etd->pid . ": "
		   .  $ex->getMessage());
      // don't add notification event to history, but continue processing other records
      continue;
    }
    
    $etd->premis->addEvent("notice",
			   "Publication Notification sent by ETD system",
			   "success",  array("software", "etd system"));
  } // end looping through etds
  return $published;
}

/**
 * Create a proquest submission package (zip of PQ submission xml + files),
 * ftp to PQ, and send email.
 * Because submission is optional for non-PhDs, if record is not set
 * to be submitted to ProQuest, it will be skipped.
 *
 * @param array $etds etd objects
 */
function submit_to_proquest(array $etds) {
  global $opts, $tmpdir, $logger;

  // delete temporary directory to ensure its contents are only from the last run of this script
  if (is_dir($tmpdir)) {
    rdelete($tmpdir);	// recursively delete directory & all contents
  }
  mkdir($tmpdir);
  
  $logger->info("Submitting to ProQuest");
  $logger->info("Temporary files will be created in $tmpdir");
    
  // in test mode: create the metadata & zip files, but don't change the records, ftp, or send email
  $zipfiles = array();

  $submissions = array();
  
  foreach ($etds as $etd) {
    // only records that are either approved or published should be sent to ProQuest
    if ($etd->status() != "approved" && $etd->status() != "published") {
      $logger->warn("Cannot submit record " . $etd->pid . " to ProQuest: status is " .
		    $etd->status() . " instead of approved or published");
      continue;	// skip to next etd
    }

    // submission optional for non-PhDs - do not submit if not required/requested
    if (!$etd->mods->submitToProquest()) {
      $logger->info("Record " . $etd->pid . " will not be sent to ProQuest (not required and not requested by student)");
      continue;
    }
    

    $logger->info("Preparing ProQuest submission file for " . $etd->pid);	// debug instead of info?
    $submission = new ProQuestSubmission();
    $submission->initializeFromEtd($etd);
    // still to do: save xml, get binary files from fedora, create zip file
    if ($submission->isValid()) {
      // create zipfile for submission package
      $submission->create_zip($tmpdir);
      $submissions[] = $submission;
    } else {
      $logger->err("ProQuest submission xml for " . $etd->pid . " is not valid");



      $submission->create_zip($tmpdir);

      // output more detailed validation information as info 
      $errors['ProQuest DTD'] = $submission->dtdValidationErrors();
      $errors['Schema'] = $submission->schemaValidationErrors();
      foreach ($errors as $name => $xml_errors) {
	if (count($xml_errors)) {
	  $logger->info("Invalid according to $name");
	  foreach ($xml_errors as $err) {
	    switch ($err->level) {
	    case LIBXML_ERR_WARNING:
	      $logger->info("Validation Warning, line " . $err->line . ": " . $err->message); break;
	    case LIBXML_ERR_ERROR:
	      $logger->info("Validation Error, line " . $err->line . ": " . $err->message); break;
	    case LIBXML_ERR_FATAL:
	      $logger->info("Fatal Validation Error, line " . $err->line . ": " . $err->message); break;
	    }
	  }
	}
      }
    }
  }
    
  // batch post-processing for proquest submissions 
  if ($opts->noact) {
    $logger->info("Test mode, so not ftping submission zip files to ProQuest");
    foreach ($submissions as $sub) $sub->ftped = true;		// pretend ftp worked
  } else {
    //  - ftp all zip files to PQ	(with error checking)
    $logger->info("Ftping all submission zip files to ProQuest");
    // ftp 
    proquest_ftp($submissions);
  }
    
  if ($opts->noact) {
    $logger->info("Test mode, so not emailing submission list to ProQuest");
    // generate & display email 
    $logger->debug("Packing list email:\n" . proquest_email($submissions));
  } else {
    //  - send email with packing list
    $logger->info("Sending submission list email to ProQuest");
    proquest_email($submissions);

    // add proquest submission to history for each etd record
    if (!$opts->noact) {
      foreach ($submissions as $submission) {
	// if the submission was successfully created and sent to ProQuest
	if ($submission->ftped) $submission->etd->sent_to_proquest();
	// FIXME: more error checking here?
      }
    }
  }
}


/**
 * ftp all created zip files to proquest
 * @param array $submissions ProQuestSubmission objects (initialized, with zipfiles)
 * @return boolean success
 */
function proquest_ftp(array $submissions) {
  global $opts, $proquest, $logger;

  $success = array();
  
  // set up basic connection
  $ftpsrv = ftp_connect($proquest->ftp->server);
  if (!$ftpsrv) {
    $logger->err("Failed to connect to ftp server " . $proquest->ftp->server);
    return false;
  } else {
    $logger->info("Successfully connected to ftp server " . $proquest->ftp->server);
  }
  // login with username and password
  $ftplogin = ftp_login($ftpsrv, $proquest->ftp->user, $proquest->ftp->password);

  if (!$ftplogin) {
    $logger->err("Failed to log in to ftp server as " . $proquest->ftp->user);
    return false;
  } else {
    $logger->debug("Logged in to ftp server as " . $proquest->ftp->user);
  }

  // set mode to passive
  ftp_pasv($ftpsrv, true);
  // get a list of files currently on the server
  $serverfiles = ftp_nlist($ftpsrv, ".");

  // loop through the submissions and transfer the zipfiles to the ftp server
  foreach ($submissions as $submission) {
    $remotefile = basename($submission->zipfile);
    
    // check if the file is already on the server
    if (is_array($serverfiles) && in_array($remotefile, $serverfiles)) {
      $logger->debug("$remotefile is already on the ftp server; deleting");
      if (!ftp_delete($ftpsrv, $remotefile)) {
	$logger->warn("Unable to delete $remotefile already on the ftp server");
      }
    }

    // upload the file
    $upload = ftp_put($ftpsrv, $remotefile, $submission->zipfile, FTP_BINARY);
    // check upload status
    if (!$upload) {
      $logger->err("FTP upload failed for $remotefile");
      $submission->ftped = false;
    } else {
      array_push($success, $remotefile);	
      $logger->debug("Successfully uploaded $remotefile");
      $submission->ftped = true;
    }

    // sanity check - compare file size
    $rsize = ftp_size($ftpsrv, $remotefile);
    if ($rsize != -1) {		// -1 = couldn't get remote filesize - not supported by ftp server
      if ($rsize != filesize($submission->zipfile)) {
	$logger->err("Remote file is not the same size as local file " . $submission->zipfile);
	// exit code?
	$submission->ftped = false;
      }
    }
  }
  
  // close the FTP connection
  ftp_close($ftpsrv);

  return true;
}




/**
 * send an email to proquest with a packing list of submissions
 * @param array $packlist array of ProQuestSubmission objects
 * @return boolean|string true on success, packing list content in when testing
 */
function proquest_email(array $submissions) {
  global $opts, $proquest, $config, $env_config, $logger;

  // if there are no records, don't send an email
  if (!count($submissions)) return;	
  
  $lines = array();
  foreach ($submissions as $submission) {
    if ($submission->ftped) 	// only include if ftp was successfully
      $lines[] = sprintf("%-30s %s",
			 $submission->author_info->name->last . ", " . $submission->author_info->name->first,
			 basename($submission->zipfile));
  }
  sort($lines);		// sort alphabetically by author
  $text = implode("\n", $lines);

  if ($opts->noact) return $text;
  
  $mail = new Zend_Mail();
  $mail->setSubject("Emory Submissions")
       ->setFrom($config->email->etd->address, $config->email->etd->name)
       ->setBodyText($text);

  // send email to configured proquest address
  $mail->addTo($proquest->email);
  // if in production, blind-copy the etd-admin listserv
  if ($env_config->mode == "production") {
    $mail->addBcc($config->email->etd->address);
  }
  
  try {
    $mail->send();
  } catch (Zend_Exception $ex) {
    $logger->err("There was a problem sending the Submission 'packing list' email to ProQuest: " .  $ex->getMessage());
    return false;
  }
  
  return true;
}


/**
 * convenience function to delete a directory and all its contents (used by proquest submission process)
 * @param string $file filename or directory to be deleted
 */
function rdelete($file) {
  if (is_dir($file)) {
    foreach (scandir($file) as $f) {
      if ($f != '.' && $f != '..') {	// ignore current/parent dirs
	// if the current file is a directory, recurse and delete all its files
	if (is_dir("$file/$f")) {
	  rdelete("$file/$f");	// needs full path
	}
	else unlink("$file/$f");
      }
    }
    // all files have been deleted, now remove the directory
    rmdir("$file");
  } else if (is_file($file))  { //regular file
    unlink($file);
  } // any other conditions?
}



/**
 * find records that have been approved that weren't published (e.g., author is not yet in registrar feed)
 */
function find_orphans() {
  global $opts, $logger;
  $logger->info("Checking for 'orphaned' ETD records");

  $etdSet = new EtdSet();
  $etdSet->findApproved();
  $count = $etdSet->numFound;
  
  if ($count) {
    $logger->notice("Found " . $count . " approved record" . ($count != 1 ? "s" : ""));
    foreach ($etdSet->etds as $etd) {
      $logger->info("    " . $etd->author() . " " . $etd->pid);
    }
  } else {
    $logger->notice("No approved unprocessed records found");
  }
}
