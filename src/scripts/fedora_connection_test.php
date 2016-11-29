<?php
require_once("bootstrap.php");
require_once("api/risearch.php");
require_once("models/etd.php");
require_once("models/programs.php");
require_once("skosCollection.php");

$persis = new Etd_Service_Persis(Zend_Registry::get('persis-config'));
$ri = $fedora->risearch;
$pids = $ri->findByCModel("emory-control:ETD-1.0");
print count("\n" . $pids . "\n");
$pg = new foxmlPrograms();
$config = Zend_Registry::get("config");
$pid = $config->programs_collection->pid;
$ds = 'skos';
$info = $fedora->getDatastreamInfo($pid, $pg->xmlconfig[$ds]['dsID']);

$pg->xmlconfig["skos"]["class_name"] = "programs";
print_r($pg->xmlconfig["skos"]["class_name"]);
print("\n");
print("program pid " . $config->programs_collection->pid . "\n");
print("\n");
print_r($pg->map[$ds]->setDatastreamInfo($info, $pg));
print("\n Done");
