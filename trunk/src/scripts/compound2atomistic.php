#!/usr/bin/php -q
<?php

set_include_path('.:../lib:../lib/fedora:../lib/xml-utilities:../app/models/:' . get_include_path());
//set_include_path('.:../lib:../app/models/:' . get_include_path());

include 'Zend/Loader.php';

function __autoload($class) {
    Zend_Loader::loadClass($class);
}
$config = new Zend_Config_Xml('../config/fedora.xml', 'development');
Zend_Registry::set('fedora-config', $config);

//persistent id service
require_once("persis.php");

$persis_config = new Zend_Config_Xml("../config/persis.xml", 'development');
$persis = new persis($persis_config->url, $persis_config->username,
		     $persis_config->password, $persis_config->domain);
Zend_Registry::set('persis', $persis);



require_once("fezetd.php");
require_once("etd.php");
require_once("etdfile.php");

/** convert a compound (fez) emory etd object into new atomist model  */


if (! isset($argv[1])) {
  print "Error: please supply pid of record to convert\n";
  exit;
}
$pid = $argv[1];


$etd = new FezEtd($pid);

// map vcard to new user object
//print_r($etd->vcard);

// create new user object
$user = new user();
//$user->pid = fedora::getNextPid("emoryetd");

// map vcard to mads
$user->mads->name->first = $etd->mods->author->first;	// first & middle - split these out?
$user->mads->name->last = $etd->mods->author->last;
$user->mads->permanent->email = $etd->vcard->permanent_email;
$user->mads->permanent->address->street = $etd->vcard->street;
$user->mads->permanent->address->city = $etd->vcard->city;
$user->mads->permanent->address->state = $etd->vcard->state;
$user->mads->permanent->address->postcode = $etd->vcard->zip;
$user->mads->permanent->address->country = $etd->vcard->country;
$user->mads->permanent->phone = $etd->vcard->telephone;
//$user->mads->permanent->netid  == ?	// pull from fez db?

// set dc:title, and foxml label 
$user->name = $etd->mods->author->full;

// FIXME: get info from fez db: netid, emory email
// (OR, maybe easier? - get from ldap or emory shared data?)
//print $user->saveXML() . "\n";


// FIXME: do dublin core mapping from mods

$newetd = new etd();
//$newetd->pid = fedora::getNextPid("emoryetd");

// set formatted fields in html & clean versions in mods
$newetd->title = $etd->mods->title;
$newetd->abstract = $etd->mods->abstract;
$newetd->contents = $etd->mods->tableOfContents;

// set other dublin core fields
$newetd->dc->creator = $etd->mods->author->full;
$newetd->dc->contributor = $etd->mods->advisor->full;
$newetd->dc->language = $etd->mods->language->text;
$newetd->dc->date = $etd->mods->date;	// should pick up key date


$name_fields = array("full", "first", "last", "affiliation");
foreach ($name_fields as $field) {
  $newetd->mods->author->$field = $etd->mods->author->$field;
}

foreach ($name_fields as $field) {
  if ($field == "affiliation") continue;	// no affiliation for advisor
  $newetd->mods->advisor->$field = $etd->mods->advisor->$field;
}


// copy committee members
foreach (array("committee", "nonemory_committee") as $cm) {
  for ($i = 0, $j = 0; $i < count($etd->mods->$cm); $i++) {
    // skip blank entries (added by Fez)
    if (!isset($etd->mods->{$cm}[$i]->full) || ($etd->mods->{$cm}[$i]->full == ""))
	continue;
    
    if (array_key_exists($i, $newetd->mods->$cm)) {
      foreach ($name_fields as $field) {
	// no affiliation for emory committee
	if ($cm == "committee" && $field == "affiliation") continue;	
	$newetd->mods->{$cm}[$j]->$field = $etd->mods->{$cm}[$i]->$field;
      }
    } else {
      print "Warning: cannot add committee member " . $etd->mods->{$cm}[$i]->full . "\n";
      // FIXME: need a way to add new mod:name nodes
      // create new node and then set values...
    }
    $j++;
  }
}

// FIXME: need to retrieve committee member & advisor netids and add them as relations


$newetd->mods->genre = $etd->mods->genre;
$newetd->mods->language->text = $etd->mods->language->text;
// set code for english (?)

// copy dates
// 	FIXME: adjust date format? 
$newetd->mods->originInfo->issued = $etd->mods->originInfo->issued;
$newetd->mods->originInfo->copyright = $etd->mods->originInfo->copyright;
$newetd->mods->originInfo->dateOther = $etd->mods->originInfo->dateOther;
// mapped to a new (proper) place in mods
$newetd->mods->recordInfo->created = $etd->mods->originInfo->created;
$newetd->mods->recordInfo->modified = $etd->mods->originInfo->modified;

// copy researchfields

// construct an array of id => text name
$fields = array();
for ($i= 0; $i < count($etd->mods->researchfields); $i++) {
  $fields[$etd->mods->researchfields[$i]->id] = $etd->mods->researchfields[$i]->topic;
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
  print "Warning: record does not seem to have page count\n";

if ($etd->mods->genre == "Dissertation") {
  $newetd->mods->degree->level = "Doctoral";
  $newetd->mods->degree->name = "Ph.D.";
} else { // check genre again? should be one or the other
  $newetd->mods->degree->level = "Masters";
  // FIXME: pull degree name from alumni feed
}

// how to set discipline ? is this a controlled vocabulary?
$newetd->mods->degree->discipline = $etd->mods->department;


// add relations to user
//$newetd->rels_ext->addRelationToResource("rel:owner", $user->pid);
// FIXME: need user's netid to set object owner and author relation
//$newetd->rels_ext->addRelation("rel:author", $user->pid);
$newetd->rels_ext->addRelation("rel:etdStatus", $etd->status);





//print $newetd->html->saveXML() . "\n";
//print $newetd->mods->saveXML() . "\n";
//$newetd->saveXMLtoFile("tmp-etd-foxml.xml");

//print $newetd->saveXML();

/* // actually ingest into fedora
 $newpid = $newetd->save();
 print "saved in fedora - id is $newpid\n";*/


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
  //  $etdfile->pid = fedora::getNextPid("emoryetd");	// let etdfile handle, use arks
  $etdfile->label = $file->label;
  $etdfile->file->url = "http://" . $config->server . ":" . $config->port
    . "/fedora/get/" . $pid . "/" . $file->ID;
  $etdfile->file->label = $file->label;
  $etdfile->file->mimetype = $etdfile->dc->type = $file->MIMEType;
  $etdfile->dc->description = $file->ID;
  // fixme: any other settings we can do?

  // add relations between objects
  $etdfile->rels_ext->addRelationToResource("rel:owner", $user->pid);
  $etdfile->rels_ext->addRelationToResource("rel:is" . $reltype . "Of", $newetd->pid);
  $newetd->rels_ext->addRelationToResource("rel:has" . $reltype, $etdfile->pid);
  
  print "saving etdfile " . $file->label . " to tmp file: tmp/" . $file->ID . ".xml\n";
  $etdfile->saveXMLtoFile("tmp/" . $file->ID . ".xml");
  
  // ingest into fedora
  
  $fileid = $etdfile->save("migrating to atomistic content model from $pid");
  print "etd file object saved as $fileid\n";
}


print "saving user xml to tmp file: tmp/user.xml\n";
$user->saveXMLtoFile("tmp/user.xml");
//$uid = $user->save("migrating to atomistic content model from $pid");
//print "user object saved as $uid\n";

print "saving etd foxml to tmp file: tmp/etd.xml\n";
$newetd->saveXMLtoFile("tmp/etd.xml");
$newid = $newetd->save("migrating to atomistic content model from $pid");
print "etd object saved as $newid\n";

/* FIXME: still needs to be included in the migration:
    - set permissions for embargo, policy status, etc.
*/
 

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
