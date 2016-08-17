#!/usr/bin/php -q
<?php

require_once("bootstrap.php");

require_once("api/risearch.php");
//require_once("Service/Persis.php");
require_once("models/etd.php");
//$proquest = new Zend_Config_Xml($config_dir . "proquest.xml", $env_config->mode);
$persis = new Etd_Service_Persis(Zend_Registry::get('persis-config'));
$ri = $fedora->risearch;
$pids = $ri->findByCModel("emory-control:ETD-1.0");

//foreach ($pids as $pid) {
  $etd = new etd($pids[5]);

  $response = $persis->generateDoi($etd);
  if (preg_match('/^doi.{16}$/', $response)){
    $etd->save();
  }
  print $response;


  //$count++;
  //if ($count > 1) {
  //    break;
  //}
//}
