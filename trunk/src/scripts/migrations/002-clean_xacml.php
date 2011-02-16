#!/usr/bin/php -q
<?php
/**
 * This script cleans known xacml problems on existing records.
 *  - remove draft rule if status is not draft
 *  - remove view rule and add new one from current template, to
 *    update graduate coordinator portion of the rule (August 2008)
 *  - update draft and published rules to latest version of
 *    xacml policy rules, as appropriate for etd status  (October 2008)
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files;
// set up connection objects for fedora, solr, ESD, and stats db
require_once("bootstrap.php");

// The selection option allows a selection of pids to be processed.
// Otherwise, the php out of memory error occurs when processing entire amount.
// Setting selection to 1, will process all "emory:1*" pids.
// Syntax of a typical call would be ./002-clean_xacml.php -s 1 -v debug
$getopts = array_merge(
           array('pid|p=s'      => 'Clean xacml on one etd record only'),
           array('selection|s=i'  => 'Selection of pids to process (0-9)'),           
           $common_getopts  // use default verbose and noact opts from bootstrap
           );

$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname goes through all etd records and cleans object xacml policy
 
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);
$count = 0;
$updated_count = 0;
$skipped_count = 0;
$failed_count = 0;

// if pid is specified, run single record mode
// ./002-clean_xacml.php -p 'emory:8kjtd' -v debug -n 
if ($opts->pid) {
  print "PID = [" . $opts->pid . "]\n";
  $etd = new etd($opts->pid);
  try {
    clean_xacml($etd);
  } catch (Exception $err) {
    $logger->err("002-clean_xacml failed to update pid [" . $opts->pid . "] POLICY datastream.");   
    $failed_count++;
    print "\n\n";    
  }
  return;
}

// find *all* records, no matter their status, etc.
$options = array("start" => 0, "max" => 6500);
$etdSet = new EtdSet($options, null, 'find');
$logger->info("EtdSet found = [" . $etdSet->count() . "] etd records.");


// Wrap the result set around the paginator to get (page) sets of data to process.
$paginator = new Zend_Paginator($etdSet);
$paginator->setItemCountPerPage(6500);

for ($page = 0; $page<sizeof($paginator); $page++) {
  
  $plural = ($etdSet->numFound == "1") ? "" : "s";  
  foreach ($paginator as $etd) { 
    $count++;
    $logger->info("Begin Processing [" . ($count-1) ."] ETD pid = [" . $etd->pid . "]"); 
   
    if (!$opts->selection ||
        $opts->selection && intval($etd->pid[6]) == $opts->selection) {
      try {  
        if (clean_xacml($etd)) {
          $updated_count++;
          $logger->info("Done Cleaned [" . ($count-1) ."] ETD pid = [" . $etd->pid . "]"); 
        }
        else {
          $skipped_count++;
          $logger->info("Done Skipped [" . ($count-1) ."] ETD pid = [" . $etd->pid . "]");    
        }
      } catch (Exception $err) {
        $logger->err("Done Failed [" . ($count-1) ."] pid [" . $etd->pid . "] POLICY datastream with [" . $err->getMessage() . "]");
        $failed_count++;      
      } 
      print "\n\n";
    }
  } 
}

$logger->info($count . " records changed");
print "\n-----------------\nResults:\n";
print "Updated count: $updated_count\n";
print "Skipped count: $skipped_count\n";
print "Failed count: $failed_count\n";
print "Total count: $count\n";
print "\n-----------------\n";


/**
 * do the actual work of cleaning the xacml
 * @param etd $etd to be cleaned
 * @return boolean whether or not record was changed
 */
function clean_xacml(etd $etd) {
  global $logger, $opts;

  $logger->debug("clean_xacml PID=[" . $etd->pid . "]");

  // update view rules
  //  - advisor & committee members should be listed on view rules for etd & all etdfiles but original
  //  - departmental staff (filtered by department name) is allowed to view
  
  // remove old view role and add new one to get new grad coordinator xacml
  $etd->removePolicyRule("view");
  $etd->addPolicyRule("view");    // should not get added to original files
  // add committee chairs & members to policies
  foreach (array_merge($etd->mods->chair, $etd->mods->committee) as $committee) {
    if ($committee->id != "") {
      $logger->debug("Adding committee id " . $committee->id . " to policy view rule");
      foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {      
        $obj->policy->view->condition->addUser($committee->id);   
      }
    }
  }
 
  // set department for departmental staff view
  if ($etd->mods->department != "") {  
    $logger->debug("Setting department to " . $etd->mods->department . " on policy view rule");
    foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {      
      $obj->policy->view->condition->department = $etd->mods->department;
    }
  }
 
  if ($etd->status() == "draft") {    
    // remove the old draft policy and add the latest version (in case it has changed)
    $etd->removePolicyRule("draft");    
    $etd->addPolicyRule("draft");;     
  }  else {     
    // if etd is no longer in draft status, make sure draft rule is removed from etd files
    // draft rule could be present in one of the etdfiles (hard to detect); just force removal
    $logger->debug("Record is not in draft status; removing any draft policy rules.");    
    $etd->removePolicyRule("draft");    // remove draft policy on etd & all files     
  }

  // if pubished, remove the old published policy and add the latest version
  if ($etd->status() == "published") {    
    $etd->removePolicyRule("published");    
    $etd->addPolicyRule("published");    
  }
    
  // check if anything has been changed (for reporting purposes)
  $hasChanged = false;
  foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
    if ($obj->policy->hasChanged()) {
      $hasChanged = true;
      print "[" . $obj->pid . "] has changed.\n";
    }
  }

  if (!$hasChanged) {    
    $logger->info("No changes to policies on " . $etd->pid . " or associated files");
  } elseif (!$opts->noact) {   
      $result = $etd->save("cleaned xacml policy - advisor, committee, department on view rule; draft rule");
    if ($result)
      $logger->info("Saved changes to " . $etd->pid . " and associated files");
    else  // save failed
      $logger->err("Error saving changes to " . $etd->pid . " and associated files");
  }

  // NOTE: if etdfiles changed but etd did not, might look like an error here when it is not
  
  // return whether or not the record was changed
  return $hasChanged;
}
