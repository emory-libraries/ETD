#!/usr/bin/php -q
<?php

set_include_path('.:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:../app:../app/models/:' . get_include_path());
//set_include_path('.:../lib:../app/models/:' . get_include_path());

include 'Zend/Loader.php';
Zend_Loader::registerAutoload();

require_once("api/FedoraConnection.php");
require_once("controllers/helpers/PdfPageTotal.php");

$opts = new Zend_Console_Getopt(
  array(
	'noact|n'	=> "no action: don't save to fedora",
	'pid|p=s'	=> "fedora pid of the fez etd to convert",
	'urls|u=s'	=> "filename where old and new urls should go",
  )
);

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $opts->getUsageMessage();
  exit;
}
$pid = $opts->pid;
if (!$pid) {
  print "Error: please supply pid of record to convert\n";
  exit;
}
$rewrite_file = $opts->urls;
$rewrite = "";


$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);

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

$fezetd = new FezEtd($pid);


// map vcard to new user object

// create new user object
$user = new user();

// map vcard to mads
$user->mads->name->first = $fezetd->mods->author->first;	// first & middle - split these out?
$user->mads->name->last = $fezetd->mods->author->last;
$user->mads->permanent->email = $fezetd->vcard->permanent_email;
$user->mads->permanent->address->street[0] = $fezetd->vcard->street;
$user->mads->permanent->address->city = $fezetd->vcard->city;
$user->mads->permanent->address->state = $fezetd->vcard->state;
$user->mads->permanent->address->postcode = $fezetd->vcard->zip;
$user->mads->permanent->address->country = $fezetd->vcard->country;
if (isset($fezetd->vcard->telephone))
    $user->mads->permanent->phone = $fezetd->vcard->telephone;

// pull some information from Fez DB (things not stored in vcard)
$fezUserObject = new FezUserObject();
$fezuser = $fezUserObject->findByUsrId($fezetd->vcard->uid);
if ($fezuser) {  // in case user is not in the Fez DB
  $ownerid = $fezuser->username;
  $user->mads->netid = $ownerid;
  $user->owner = $ownerid;

  // emory email
  $user->mads->current->email = $fezuser->email;
} else {
 print "Warning: Cannot find user information in Fez Db for author (fez id=" . $fezetd->vcard->uid . ")\n";
}
// set dc:title, and foxml label 
$user->name = $fezetd->mods->author->full;

// finished setting values for user object - except for relation to etd


$newetd = new etd();
if (isset($ownerid)) {
  $newetd->owner = $ownerid;
  // also use netid for mods name id
  $newetd->mods->author->id = $ownerid;
 }
  
// set formatted fields in html & clean versions in mods
$newetd->title = $fezetd->mods->title;
$newetd->abstract = $fezetd->mods->abstract;
$newetd->contents = $fezetd->mods->tableOfContents;

// set other dublin core fields
$newetd->dc->creator = $fezetd->mods->author->full;
$newetd->dc->contributor = $fezetd->mods->advisor->full;
$newetd->dc->language = $fezetd->mods->language->text;
$newetd->dc->date = $fezetd->mods->date;	// should pick up key date

// set author information

$name_fields = array("full", "first", "last", "affiliation");
foreach ($name_fields as $field) {
  $newetd->mods->author->$field = $fezetd->mods->author->$field;
}

// set author's department in mods & in view policy
$newetd->department = $fezetd->mods->department;

// retrieve netid and use to set values in new etd record
$fezAdvisor = $fezUserObject->findByUsrId($fezetd->mods->advisor->id);
if ($fezAdvisor) {
  $newetd->setAdvisor($fezAdvisor->username);
  // FIXME: possibly need error handling if user is not found in ESD? (unlikely...)
} else {
  print "Warning: Cannot find user information in Fez Db for advisor (fez id=" .
    $fezetd->mods->advisor->id . ")";
}

// set committee
$committee_ids = array();
foreach ($fezetd->mods->committee as $cm) {
  if (!isset($cm->full) || ($cm->full == "")) continue;	// skip blank entries
  $fezCommittee = $fezUserObject->findByUsrId($cm->id);
  if ($fezCommittee) 
    $committee_ids[] = $fezCommittee->username;
  else
    print "Warning: Cannot find user information in Fez Db for committee (fez id=" .
      $cm->id . ")\n";
}
if (count($committee_ids)) {	// don't set if none were found
  // set committee in mods & policy with values from ESD
  $newetd->setCommittee($committee_ids);
 }

// copy non-Emory committee from old etd
// 	clear out non-emory so if there are none we won't get blank xml for that
$newetd->mods->clearNonEmoryCommittee();
foreach ($fezetd->mods->nonemory_committee as $cm) {
  // skip blank entries (added by Fez)
  if (!isset($cm->full) || ($cm->full == "")) continue;
  $newetd->mods->addCommitteeMember($cm->last,
				    $cm->first,
				     false,	//  false = non-emory
				    $cm->affiliation);
}


$newetd->mods->genre = $fezetd->mods->genre;
$newetd->mods->language->text = $fezetd->mods->language->text;
// set code for english (?)

// dates 	FIXME: adjust date format? 
$newetd->mods->originInfo->issued = $fezetd->mods->originInfo->issued;

// in fez etds, yes/no on copyright request was temporarily stored in copyright date
// 	FIXME: make sure this works correctly for published records 
if (is_numeric($fezetd->mods->originInfo->copyright) ||
    $fezetd->mods->originInfo->copyright == "Yes")
  $copyright = "yes";
elseif ($fezetd->mods->originInfo->copyright == "" ||
	$fezetd->mods->originInfo->copyright == "No")
  $copyright = "no";
if ($copyright)
  $newetd->mods->copyright = $copyright;
else
  print "Warning: Cannot determine if copyright was requested";

// in fez etds, yes/no on embargo request was temporarily stored in dateOther

$embargo_duration = str_replace("Embargoed for ", "", $fezetd->mods->embargo_note);

if ($fezetd->mods->embargo_end == "No" || $embargo_duration == "0 days")
  $embargo_request = "no";
elseif ($fezetd->mods->embargo_end == "Yes" || $embargo_duration != "")
  $embargo_request = "yes";
if ($embargo_request)
  $newetd->mods->embargo_request = $embargo_request;
else
  print "Warning: Cannot determine if embargo was requested\n";

// if embargo has already been approved & duration set, store it
if ($embargo_duration)
  $newetd->mods->embargo = $embargo_duration;

// if embargo end date has already been calculated, store it
if ($embargo_request == "yes" && $fezetd->mods->embargo_end != "Yes") {
  $newetd->mods->embargo_end = $fezetd->mods->embargo_end;
  $embargo_end = $fezetd->mods->embargo_end;
}
// store embargo information - may be needed for etdfile xacml policy rules
if ($embargo_request == "yes") $embargo_requested = true;
 else $embargo_requested = false;




// fezetd record created/updated mapped to a new (proper) place in mods
$newetd->mods->recordInfo->created = $fezetd->mods->originInfo->created;
$newetd->mods->recordInfo->modified = $fezetd->mods->originInfo->modified;



//  researchfields
$fields = array();	// construct an array of id => text name
for ($i= 0; $i < count($fezetd->mods->researchfields); $i++) {
  // ProQuest ids lost their leading zeroes in Fez - restore them here; should be 4 digits
  $id = sprintf("%04u", $fezetd->mods->researchfields[$i]->id);
  $fields[$id] = $fezetd->mods->researchfields[$i]->topic;
 }
// set all the fields at once
$newetd->mods->setResearchFields($fields);

// no series in dublin core as yet - use first research field as subject for now
// FIXME: after dc object can handle series, store all research fields & keywords
$newetd->dc->subject = $newetd->mods->researchfields[0]->topic;


// copy keywords (no id attributes)
for ($i= 0; $i < count($fezetd->mods->keywords); $i++) {
  if (array_key_exists($i, $newetd->mods->keywords)) 
    $newetd->mods->keywords[$i]->topic = $fezetd->mods->keywords[$i]->topic;
  else 
    $newetd->mods->addKeyword($fezetd->mods->keywords[$i]->topic);
}

// copy number of pages
if (isset($fezetd->mods->pages))
  $newetd->mods->pages = $fezetd->mods->pages;
else 
  print "Warning: Record does not seem to have page count\n";

if ($fezetd->mods->genre == "Dissertation") {
  $newetd->mods->degree->level = "Doctoral";
  $newetd->mods->degree->name = "Ph.D.";	// safe to assume for limited pilot participants?
} else { // check genre again? should be one or the other
  $newetd->mods->degree->level = "Masters";
  // pull degree name from vcard (value from alumni feed stored on publication)
  if (isset($fezetd->vcard->degree))
    $newetd->mods->degree->name = $fezetd->vcard->degree;
  else
    print "Warning: Record does not have degree name (MA/MS?); please set manually\n";
    
}
// note: discipline is being used for program subfield
//   - no subfields in pilot departments, so ignoring



// set status & load appropriate policies
$newetd->setStatus($fezetd->status);
if ($newetd->status() == "published" &&
    $embargo_requested && $embargo_end) { 
  // set embargo end date as condition in published policy rule
  $newetd->policy->published->condition->embargo_end = $embargo_end;
}

if ($opts->noact) {
  print "saving etd foxml to tmp file: tmp/etd.xml\n";
  $newetd->pid = "test:etd1";	// set test pid in order to simulate setting rels, premis, etc
  $newetd->saveXMLtoFile("tmp/etd.xml");
} else {
  $newid = $newetd->save("migrating to atomistic content model from $pid");
  print "etd object saved as $newid\n";
  
  // need to reinitialize from fedora so datastreams can be saved separately
  $newetd = new etd($newid);
}

if (isset($newetd->mods->ark) && $newetd->mods->ark != "")
  $newurl = $newetd->mods->ark;		// should be set if not in noact mode
else
  $newurl = "view/record/pid/" . $newetd->pid;

$rewrite .= "# etd record - $ownerid
view.php?pid={$fezetd->pid}	$newurl
";


// NOTE: in noact mode, temporary pids are set in order to test setting rels & premis 


// add etd relation to user object
$user->rels_ext->addRelationToResource("rel:authorInfoFor", $newetd->pid);

if ($opts->noact) {
  print "saving user xml to tmp file: tmp/user.xml\n";
  $user->pid = "test:user1";	// set test pid in order to simulate setting rels
  $user->saveXMLtoFile("tmp/user.xml");
} else {
  $user_pid = $user->save("migrating to atomistic content model from $pid");
  print "user object saved as $user_pid\n";
}


// add author info relation to etd object
$newetd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $user->pid);



$filecount = 0;
// generate etdFile objects based on these
foreach ($fezetd->files as $file) {
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

  $etdfile->file->label = $file->label;
  $etdfile->file->mimetype = $etdfile->dc->type = $file->MIMEType;
  $etdfile->dc->description = $file->ID;

  // for pdf/original - set author, basic description (need same in controllers)

  // fixme: any other settings we can do?

  // add relations between objects
  $etdfile->rels_ext->addRelationToResource("rel:is" . $reltype . "Of", $newetd->pid);
  
  
  // ingest into fedora
  if ($opts->noact) {
    print "saving etdfile " . $file->label . " to tmp file: tmp/" . $file->ID . ".xml\n";
    $etdfile->pid = "test:etdfile" . ++$filecount;   
    $etdfile->saveXMLtoFile("tmp/" . $file->ID . ".xml");
  } else {
    //if we just refer to the files at the public url, they may be inaccessible/embargoed

    // should access as fedoraAdmin so the file can be retrieved, then re-upload
    $filename = "/tmp/" . $file->ID;
    file_put_contents($filename, $fedora->getDatastream($fezetd->pid, $file->ID));
    if (filesize($filename) === 0) {
      print "Error: file $filename downloaed from fedora is zero size\n";
      // exit? skip this file?
    }
    
    // while the file is locally accessible, get some information about it
    $etdfile->setFileInfo($filename);	// set mimetype, filesize, and pages if appropriate
    
    $etdfile->file->url = $fedora->upload($filename);
    if ($reltype == "PDF") {
      $pagecalc = new Etd_Controller_Action_Helper_PdfPageTotal();
      // get page total
      $etdfile->dc->setPages($pagecalc->pagetotal($filename));
    }
    
    $fileid = $etdfile->save("migrating to atomistic content model from $pid");
    print "etd file object saved as $fileid\n";
  }

  if ($reltype != "Original") {		// no redirects for archive copy
    // print out old and new urls to be used in creating rewrite rules
    
    if (isset($etdfile->dc->ark) && $etdfile->dc->ark != "")
      $newurl = $etdfile->dc->ark;		// should be set if not in noact mode
    else
      $newurl = "file/view/pid/" . $etdfile->pid;
    // FIXME: is this a good file url? will this change?
    
    $rewrite .= "# etd file ($reltype) 
eserv.php?pid={$fezetd->pid}&dsID={$file->ID} 	$newurl
";

  }

  // add relation to new etd
  if ($opts->noact) {
    $newetd->rels_ext->addRelationToResource("rel:has" . $reltype, $etdfile->pid);
  } else {
    // adds appropriate relation and also sets etdfile sequence numbers 
    switch($reltype) {
    case "PDF":        $result = $newetd->addPdf($etdfile); break;
    case "Original":   $result = $newetd->addOriginal($etdfile); break;
    case "Supplement": $result = $newetd->addSupplement($etdfile); break;
    }
  }


}


// copy premis events, updating to new format

// note: premis should only be updated after object has a pid
$newetd->premis->object->identifier->value = $newetd->pid;

// fez lists most recent events first; we want the history in chronological order
foreach (array_reverse($fezetd->premis->event) as $event) {
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

if ($opts->noact) {
  print "updating etd foxml in tmp file with rels & premis: tmp/etd.xml\n";
  $newetd->saveXMLtoFile("tmp/etd.xml");
} else {
  $result = $newetd->save("migrated record history");
  print "updated etd with rels & premis actions; saved at $result\n";
}


// save old and new urls based on old & new pids for all objects/files
// (basis for rewrite rules)
if ($rewrite_file) {
  print "saving old and new urls for rewrite rules in $rewrite_file\n";
  file_put_contents($rewrite_file, $rewrite, FILE_APPEND);
}



?>
