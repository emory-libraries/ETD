#!/usr/bin/php -q
<?php

  /**
	This script cleans known xacml problems on existing records.
	- remove draft rule if status is not draft
	- remove view rule and add new one from current template, to
  	  update graduate coordinator portion of the rule (August 2008)
        - update draft and published rules to latest version of
	  xacml policy rules, as appropriate for etd status  (October 2008)
   */

// set paths, load config files;
// set up connection objects for fedora, solr, ESD, and stats db
require_once("bootstrap.php");

$getopts = array_merge(
		       array('pid|p=s'	    => 'Clean xacml on one etd record only'),
		       $common_getopts	// use default verbose and noact opts from bootstrap
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

// if pid is specified, run single record mode
if ($opts->pid) {
  $etds = EtdFactory::etdByPid($opts->pid);
  clean_xacml($etd);
  return;
}


// find *all* records, no matter their status, etc.
$options = array("start" => 0, "max" => 100);
$etdSet = new EtdSet();
$etdSet->find($options);

for ($count = 0; $etdSet->hasResults(); $etdSet->next()) {
  $logger->info("Processing records " . $etdSet->currentRange() . " of " . $etdSet->numFound);
  
  foreach ($etdSet->etds as $etd) {
    if (clean_xacml($etd)) $count++;
  }
}

$logger->info($count . " records changed");




/**
 * do the actual work of cleaning the xacml
 * @param etd $etd to be cleaned
 * @return boolean whether or not record was changed
 */
function clean_xacml(etd $etd) {
  global $logger, $opts;

  $logger->debug("Processing " . $etd->pid);
  // update view rules
  //  - advisor & committee members should be listed on view rules for etd & all etdfiles but original
  //  - departmental staff (filtered by department name) is allowed to view
  
  // remove old view role and add new one to get new grad coordinator xacml
  $etd->removePolicyRule("view");
  $etd->addPolicyRule("view");		// should not get added to original files
  
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
    $etd->addPolicyRule("draft");
  }  else {
    // if etd is no longer in draft status, make sure draft rule is removed from etd files
    // draft rule could be present in one of the etdfiles (hard to detect); just force removal
    $logger->debug("Record is not in draft status; removing any draft policy rules.");
    $etd->removePolicyRule("draft");		// remove draft policy on etd & all files
  }


  // if pubished, remove the old published policy and add the latest version
  if ($etd->status() == "published") {
    $etd->removePolicyRule("published");
    $etd->addPolicyRule("published");
  }
  
  // check if anything has been changed (for reporting purposes)
  $hasChanged = false;
  foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
    if ($obj->policy->hasChanged()) $hasChanged = true;
  }
  
  if (!$hasChanged) {
    $logger->info("No changes to policies on " . $etd->pid . " or associated files");
  } elseif (!$opts->noact) {
    $result = $etd->save("cleaned xacml policy - advisor, committee, department on view rule; draft rule");
    if ($result)
      $logger->info("Saved changes to " . $etd->pid . " and associated files");
    else	// save failed
      $logger->err("Error saving changes to " . $etd->pid . " and associated files");
  }
  // NOTE: if etdfiles changed but etd did not, might look like an error here when it is not


  
  // return whether or not the record was changed
  return $hasChanged;
}