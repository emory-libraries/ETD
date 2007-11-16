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

require_once("emoryetd.php");
require_once("etd.php");
require_once("etdfile.php");

/** convert a compound (fez) emory etd object into new atomist model  */


if (! isset($argv[1])) {
  print "Error: please supply pid of record to convert\n";
  exit;
}
$pid = $argv[1];


$etd = new EmoryEtd($pid);

// map vcard to new user object
//print_r($etd->vcard);

// create new user object
$user = new person();
$user->pid = fedora::getNextPid("emoryetd");


//transfer common fields from vcard to vcard
$common_fields = array("permanent_email", "street", "city", "state", "zip", "country", "telephone");
foreach ($common_fields as $field) {
  $user->vcard->$field = $etd->vcard->$field;
  // FIXME: losing address type (currently permanent for all)
}

// pick up fields fez didn't properly set in the vcard
//$user->vcard->fullname = $etd->mods->author->full;

// magically set vcard name, dc:title, and foxml label 
$user->name = $etd->mods->author->full;


$user->vcard->name->last = $etd->mods->author->last;
// split out given name into first & middle names


if (strpos($etd->mods->author->first, ' ')) {
  list($firstname, $middlename) = split(' ', $etd->mods->author->first, 2);
  $user->vcard->name->first = $firstname;
  $user->vcard->name->middle = $middlename;
} else {
  $user->vcard->name->first = $etd->mods->author->first;
}

// FIXME: get info from fez db: netid, current email
// (OR, maybe easier? - get from ldap or emory shared data?)
//print $user->vcard->saveXML() . "\n";



$newetd = new etd();
$newetd->pid = fedora::getNextPid("emoryetd");

// set formatted fields in html & clean versions in mods
$newetd->title = $etd->mods->title;
$newetd->abstract = $etd->mods->abstract;
$newetd->contents = $etd->mods->toc;


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
    if ($etd->mods->{$cm}[$i]->full == "")  continue;
    
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


$newetd->mods->genre = $etd->mods->genre;

// copy researchfields
// FIXME: also need to copy researchfield IDs (probably need to make a mods_subject class)
for ($i= 0; $i < count($etd->mods->researchfields); $i++) {
  if (array_key_exists($i, $newetd->mods->researchfields))
    $newetd->mods->researchfields[$i] = $etd->mods->researchfields[$i];
  else
    $newetd->mods->addResearchField($etd->mods->researchfields[$i]);
}

// copy keywords
for ($i= 0; $i < count($etd->mods->keywords); $i++) {
  if (array_key_exists($i, $newetd->mods->keywords))
    $newetd->mods->keywords[$i] = $etd->mods->keywords[$i];
  else
    $newetd->mods->addKeyword($etd->mods->keywords[$i]);
}

// copy number of pages
$newetd->mods->pages = $etd->mods->pages;


// add relations to user
$newetd->rels_ext->addRelationToResource("rel:owner", $user->pid);

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
  $etdfile->pid = fedora::getNextPid("emoryetd");
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
  $etdfile->save("migrating to atomistic content model from $pid");
}


print "saving user xml to tmp file: tmp/user.xml\n";
$user->saveXMLtoFile("tmp/user.xml");
$user->save("migrating to atomistic content model from $pid");

print "saving etd foxml to tmp file: tmp/etd.xml\n";
$newetd->saveXMLtoFile("tmp/etd.xml");
$newetd->save("migrating to atomistic content model from $pid");


/* FIXME: still needs to be included in the migration:
    - migrate record history
    - set permissions for embargo, policy status, etc.
    - fez status

*/
 


?>
