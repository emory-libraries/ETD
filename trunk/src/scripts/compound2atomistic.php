#!/usr/bin/php -q
<?php

set_include_path('.:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:../app:../app/models/:' . get_include_path());
//set_include_path('.:../lib:../app/models/:' . get_include_path());

include 'Zend/Loader.php';
Zend_Loader::registerAutoload();

require_once("api/FedoraConnection.php");

$opts = new Zend_Console_Getopt(
  array(
	'noact|n'	=> "no action: don't save to fedora",
	'pid|p=s'		=> "fedora pid of the fez etd to convert",
  )
);

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $opts->getUsageMessage();
  exit;
}
$pid = $opts->pid;


$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");

$config = new Zend_Config_Xml('../config/fedora.xml', $env_config->mode);
Zend_Registry::set('fedora-config', $config);


$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);
$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
				 $fedora_cfg->server, $fedora_cfg->port);
Zend_Registry::set('fedora', $fedora);


//persistent id service -- needs to be configured for generating new fedora pids
require_once("persis.php");

$persis_config = new Zend_Config_Xml("../config/persis.xml", $env_config->mode);
$persis = new persis($persis_config->url, $persis_config->username,
		     $persis_config->password, $persis_config->domain);
Zend_Registry::set('persis', $persis);


//Create DB connection for Fez db
$fezconfig = new Zend_Config_Xml('../config/fez.xml', $env_config->mode);
$db = Zend_Db::factory($fezconfig->database->adapter, $fezconfig->database->params->toArray());
// NOT setting as default - fezuser model will grab from the registry
Zend_Registry::set('fez-db', $db);

// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml('../config/esd.xml', $env_config->mode);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);


require_once("fezetd.php");
require_once("fezuser.php");
require_once("etd.php");
require_once("etdfile.php");


/** convert a compound (fez) emory etd object into new atomist model  */

$etd = new FezEtd($pid);

// map vcard to new user object

// create new user object
$user = new user();

// map vcard to mads
$user->mads->name->first = $etd->mods->author->first;	// first & middle - split these out?
$user->mads->name->last = $etd->mods->author->last;
$user->mads->permanent->email = $etd->vcard->permanent_email;
$user->mads->permanent->address->street = $etd->vcard->street;
$user->mads->permanent->address->city = $etd->vcard->city;
$user->mads->permanent->address->state = $etd->vcard->state;
$user->mads->permanent->address->postcode = $etd->vcard->zip;
$user->mads->permanent->address->country = $etd->vcard->country;
if (isset($etd->vcard->telephone))
    $user->mads->permanent->phone = $etd->vcard->telephone;

// pull some information from Fez DB (things not stored in vcard)
$fezUserObject = new FezUserObject();
$fezuser = $fezUserObject->findByUsrId($etd->vcard->uid);
if ($fezuser) {  // in case user is not in the Fez DB
  $ownerid = $fezuser->username;
  print "found fezuser... username is " . $ownerid . "\n";
  
  $user->mads->netid = $ownerid;
  $user->owner = $ownerid;

  // emory email
  $user->mads->current->email = $fezuser->email;
} else {
  trigger_error("Cannot find user information in Fez Db for author (fez id=" . $etd->vcard->uid . ")", E_USER_WARNING);
}
// set dc:title, and foxml label 
$user->name = $etd->mods->author->full;


// finished setting values for user object - save
if ($opts->noact) {
  print "saving user xml to tmp file: tmp/user.xml\n";
  $user->saveXMLtoFile("tmp/user.xml");
  $user->pid = "test:user1";	// set test pid in order to simulate setting rels
} else {
  $user_pid = $user->save("migrating to atomistic content model from $pid");
  print "user object saved as $user_pid\n";
}


$newetd = new etd();
if (isset($ownerid)) {
  $newetd->owner = $ownerid;
  // also use netid for mods name id
  $newetd->mods->author->id = $ownerid;
 }
  
// set formatted fields in html & clean versions in mods
$newetd->title = $etd->mods->title;
$newetd->abstract = $etd->mods->abstract;
$newetd->contents = $etd->mods->tableOfContents;

// set other dublin core fields
$newetd->dc->creator = $etd->mods->author->full;
$newetd->dc->contributor = $etd->mods->advisor->full;
$newetd->dc->language = $etd->mods->language->text;
$newetd->dc->date = $etd->mods->date;	// should pick up key date

// set author information

$name_fields = array("full", "first", "last", "affiliation");
foreach ($name_fields as $field) {
  $newetd->mods->author->$field = $etd->mods->author->$field;
}

// set author's department in mods & in view policy
$newetd->department = $etd->mods->department;

// retrieve netid and use to set values in new etd record
$fezAdvisor = $fezUserObject->findByUsrId($etd->mods->advisor->id);
if ($fezAdvisor) {
  $newetd->setAdvisor($fezAdvisor->username);
  // FIXME: possibly need error handling if user is not found in ESD? (unlikely...)
} else {
  trigger_error("Cannot find user information in Fez Db for advisor (fez id=" .
		$etd->mods->advisor->id . ")", E_USER_WARNING);
}

// set committee
$committee_ids = array();
foreach ($etd->mods->committee as $cm) {
  if (!isset($cm->full) || ($cm->full == "")) continue;	// skip blank entries
  $fezCommittee = $fezUserObject->findByUsrId($cm->id);
  if ($fezCommittee) 
    $committee_ids[] = $fezCommittee->username;
  else
    trigger_error("Cannot find user information in Fez Db for committee (fez id=" .
		  $cm->id . ")", E_USER_WARNING);
}
if (count($committee_ids)) {	// don't set if none were found
  // set committee in mods & policy with values from ESD
  $newetd->setCommittee($committee_ids);
 }

// copy non-Emory committee from old etd
// 	clear out non-emory so if there are none we won't get blank xml for that
$newetd->mods->clearNonEmoryCommittee();
foreach ($etd->mods->nonemory_committee as $cm) {
  // skip blank entries (added by Fez)
  if (!isset($cm->full) || ($cm->full == "")) continue;
  $newetd->mods->addCommitteeMember($cm->last,
				    $cm->first,
				     false,	//  false = non-emory
				    $cm->affiliation);
}

// FIXME: need to retrieve committee member & advisor netids and add them as relations


$newetd->mods->genre = $etd->mods->genre;
$newetd->mods->language->text = $etd->mods->language->text;
// set code for english (?)

// dates 	FIXME: adjust date format? 
$newetd->mods->originInfo->issued = $etd->mods->originInfo->issued;

// in fez etds, yes/no on copyright request was temporarily stored in copyright date
// 	FIXME: make sure this works correctly for published records 
if (is_numeric($etd->mods->originInfo->copyright) ||
    $etd->mods->originInfo->copyright == "Yes")
  $copyright = "yes";
elseif ($etd->mods->originInfo->copyright == "" ||
	$etd->mods->originInfo->copyright == "No")
  $copyright = "no";
if ($copyright)
  $newetd->mods->copyright = $copyright;
else
  trigger_error("Cannot determine if copyright was requested", E_USER_WARNING);

// in fez etds, yes/no on embargo request was temporarily stored in dateOther

$embargo_duration = str_replace("Embargoed for ", "", $etd->mods->embargo_note);

if ($etd->mods->embargo_end == "No" || $embargo_duration == "0 days")
  $embargo_request = "no";
elseif ($etd->mods->embargo_end == "Yes" || $embargo_duration != "")
  $embargo_request = "yes";
if ($embargo_request)
  $newetd->mods->embargo_request = $embargo_request;
else
  trigger_error("Cannot determine if embargo was requested", E_USER_WARNING);

// if embargo has already been approved & duration set, store it
if ($embargo_duration)
  $newetd->mods->embargo = $embargo_duration;

// if embargo end date has already been calculated, store it
if ($embargo_request == "yes" && $etd->mods->embargo_end != "Yes") {
  $newetd->mods->embargo_end = $etd->mods->embargo_end;
  $embargo_end = $etd->mods->embargo_end;
}
// store embargo information - may be needed for etdfile xacml policy rules
if ($embargo_request == "yes") $embargo_requested = true;
 else $embargo_requested = false;




// fezetd record created/updated mapped to a new (proper) place in mods
$newetd->mods->recordInfo->created = $etd->mods->originInfo->created;
$newetd->mods->recordInfo->modified = $etd->mods->originInfo->modified;



//  researchfields
$fields = array();	// construct an array of id => text name
for ($i= 0; $i < count($etd->mods->researchfields); $i++) {
  $fields["id" . $etd->mods->researchfields[$i]->id] = $etd->mods->researchfields[$i]->topic;
 }
// set all the fields at once
$newetd->mods->setResearchFields($fields);

// no series in dublin core as yet - use first research field as subject for now
// FIXME: after dc object can handle series, store all research fields & keywords
$newetd->dc->subject = $newetd->mods->researchfields[0]->topic;


// copy keywords (no id attributes)
for ($i= 0; $i < count($etd->mods->keywords); $i++) {
  if (array_key_exists($i, $newetd->mods->keywords)) 
    $newetd->mods->keywords[$i]->topic = $etd->mods->keywords[$i]->topic;
  else 
    $newetd->mods->addKeyword($etd->mods->keywords[$i]->topic);
}

// copy number of pages
if (isset($etd->mods->pages))
  $newetd->mods->pages = $etd->mods->pages;
else 
  trigger_error("Record does not seem to have page count", E_USER_WARNING);

if ($etd->mods->genre == "Dissertation") {
  $newetd->mods->degree->level = "Doctoral";
  $newetd->mods->degree->name = "Ph.D.";	// safe to assume for limited pilot participants?
} else { // check genre again? should be one or the other
  $newetd->mods->degree->level = "Masters";
  // pull degree name from vcard (value from alumni feed stored on publication)
  $newetd->mods->degree->name = $etd->vcard->degree;
}
// note: discipline is being used for program subfield
//   - no subfields in pilot departments, so ignoring

// add relations to user
//$newetd->rels_ext->addRelationToResource("rel:owner", $user->pid);


// set status & load appropriate policies
$newetd->setStatus($etd->status);
if ($embargo_requested && $embargo_end) {
  // fixme : set embargo end date in published policy
 }

if ($opts->noact) {
  print "saving etd foxml to tmp file: tmp/etd.xml\n";
  $newetd->saveXMLtoFile("tmp/etd.xml");
  $newetd->pid = "test:etd1";	// set test pid in order to simulate setting rels, premis, etc
} else {
  $newid = $newetd->save("migrating to atomistic content model from $pid");
  print "etd object saved as $newid\n";
}



// FIXME: in noact mode set temporary pids to test setting rels & premis stuff...


// generate etdFile objects based on these
foreach ($etd->files as $file) {
  switch($file->label) {
  case "Dissertation":
    //    print "Dissertation: ID=" . $file->ID . ", mimetype=" . $file->MIMEType . "\n";
    $reltype = "PDF";
    break;
  case "Original":
    //    print "Original: ID=" . $file->ID . ", mimetype=" . $file->MIMEType . "\n";
    $reltype = "Original";
    break;
  default:	// supplemental files
    //    print "Supplement: ID=" . $file->ID . ", label=" . $file->label . ", mimetype=" . $file->MIMEType . "\n";
    $reltype = "Supplement";
  }

  $etdfile = new etd_file();
  // let etdfile handle getting new pids (using arks)
  $etdfile->label = $file->label;
  if (isset($ownerid)) $etdfile->owner = $ownerid;
  $etdfile->file->url = "http://" . $config->server . ":" . $config->port
    . "/fedora/get/" . $pid . "/" . $file->ID;
  $etdfile->file->label = $file->label;
  $etdfile->file->mimetype = $etdfile->dc->type = $file->MIMEType;
  $etdfile->dc->description = $file->ID;
  // fixme: any other settings we can do?

  // NOTE: can't add relations until after objects are ingested and they have pids
  // add relations between objects
  //  $etdfile->rels_ext->addRelationToResource("rel:owner", $user->pid);
  $etdfile->rels_ext->addRelationToResource("rel:is" . $reltype . "Of", $newetd->pid);
  $newetd->rels_ext->addRelationToResource("rel:has" . $reltype, $etdfile->pid);
  
  
  // ingest into fedora
  if ($opts->noact) {
    print "saving etdfile " . $file->label . " to tmp file: tmp/" . $file->ID . ".xml\n";
    $etdfile->saveXMLtoFile("tmp/" . $file->ID . ".xml");
  } else {
    $fileid = $etdfile->save("migrating to atomistic content model from $pid");
    print "etd file object saved as $fileid\n";
  }
}


/* FIXME: still needs to be included in the migration:
    - set permissions for embargo, policy status, etc.
*/


// remaining actions depend on etd object having been created in fedora
if ($opts->noact) exit;
 

// copy premis events, updating to new format

// need to reinitialize from fedora so datastreams can be saved separately
$newetd = new etd($newid);

// note: premis must be done after object has a pid
$newetd->premis->object->identifier->value = $newid;

//FIXME: need to set object id in premis before adding events (event ids based on object id)
// fez lists most recent events first; we want the history in chronological order
foreach (array_reverse($etd->premis->event) as $event) {
  // note: agent value is returning fez userid (numeric database id)

  preg_match("/(^.*) by (.*)$/", $event->detail, $matches);
  $action = $matches[1];
  $agent = $matches[2];

  // determine event type according to action taken
  switch ($action) {
  case "Record created": $event_type = "ingest"; break;
  case "Record modified": $event_type = "change"; break;
  case "Submitted for Approval":
  case "Published":
  case "Document approved":
    $event_type = "status change";
    break;
  case "Record Reviewed":
    $event_type = "review";
    break;
  case "Access Restrictions requested":
  case "Access restriction of 0 days approved":
  case "Access restriction of 6 months approved":
  case "Access restriction of 1 year approved":
  case "Access restriction of 2 years approved":
  case "Access restriction of 6 years approved":
  case "Graduation Confirmed":
    $event_type = "administrative";
    break;
  case "Publication Notification":
  case "Submission Notification":
  case "Publication Notification":	// are these correct? any others?
    $event_type = "notice";
    break;
  default:
    $event_type = "unknown";
  }

  // agent - handle non-user actions
  switch($agent) {
  case "ETD System":
    $agent = array("name", "ETD software"); break;
  case "Graduate School":
    $agent = array("name", "Graduate School"); break;
  default:
    // FIXME: look up netid by fezid in fez db 
    $agent = array("fezid", $event->agent->value);
  }

  $newetd->premis->addEvent($event_type, $event->detail, "success",
			    $agent, $event->date);
}

$result = $newetd->save("migrated record history");
print "added premis actions and saved at $result\n";




?>
