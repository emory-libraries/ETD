<?php
error_reporting(E_ERROR);

require_once("bootstrap.php");

require_once("api/risearch.php");
//require_once("Service/Persis.php");
require_once("models/etd.php");

$getopts = array_merge(
array(
    'pid|p=s'             => 'Specfic PID',
    'file|f=s'           => 'File with a list of PIDS, one per line.',
    ),  $common_getopts    // use default verbose and noact opts from bootstrap
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

$logger = setup_logging($opts->verbose);

$persis = new Etd_Service_Persis(Zend_Registry::get('persis-config'));
$ri = $fedora->risearch;

$pids = array();
if ($opts->pid) {
  array_push($pids, $opts->pid);
} elseif ($opts->file) {
  $pids = file($opts->file, FILE_IGNORE_NEW_LINES);
} else {
  $pids = $ri->findByCModel("emory-control:ETD-1.0");
}

$count = 0;
print count($pids);
foreach ($pids as $pid) {
  $etd = new etd($pid);
    if ($etd->rels_ext->status == 'published') {
      print $etd->pid . "\n";
      if (!$opts->noact) {
        print "shit";
      //   $response = $persis->generateDoi($etd);
      //   if (preg_match('/^doi.{17}$/', $response)){
      //       $etd->premis->addEvent("administrative",
      //               "DOI generated " . $etd->mods->doi,
      //               "success", array("software", "etd system"));
      //       $etd->save('DOI Added.');
      //   } else {
      //       print_r($response);
      //       print $response;
      //   }
      }
      if ($count++ > 36){
          break;
      }
    }
  }
//
//
//   #!/usr/bin/php -q
//   <?php
//   error_reporting(E_ERROR);
//
//   require_once("bootstrap.php");
//
//   require_once("api/risearch.php");
//   //require_once("Service/Persis.php");
//   require_once("models/etd.php");
//
//   $getopts = array_merge(
//   array(
//       'pid|p=s'             => 'Specfic PID',
//       'file|f=s'           => 'File with a list of PIDS, one per line.',
//       ),  $common_getopts    // use default verbose and noact opts from bootstrap
//   );
//
//   $opts = new Zend_Console_Getopt($getopts);
//
//
//   // extended usage information - based on option list above, but with explanation/examples
// $scriptname = basename($_SERVER{"SCRIPT_NAME"});
// $usage = $opts->getUsageMessage() . "
//   In the default mode, $scriptname will find recent graduates from the
//   specified registrar feed, find their corresponding ETD records, and
//   run those records through all the steps in the publication process.
//     example usage:      $scriptname -f /path/to/feed.csv
//
//   The individual actions (or any combination of them) may be run on
//   records specified by pid.
//     example usage:      $scriptname -q pid1 pid2 pid3
//
//   In test mode, no records will be changed, no files will be ftped,
//   and no emails will be sent.  (This mode is mainly useful to check
//   for recent graduates in the feed and their corresponding ETD
//   records and for testing ProQuest submission output.)
//
// ";
//
// try {
//   $opts->parse();
// } catch (Zend_Console_Getopt_Exception $e) {
//   echo $usage;
//   exit;
// }
//
// $logger = setup_logging($opts->verbose);
//
// $persis = new Etd_Service_Persis(Zend_Registry::get('persis-config'));
// $ri = $fedora->risearch;
//
// $pids = array();
// if ($opts->pid) {
//   array_push($pids, $opts->pid);
// } elseif ($opts->file) {
//   $pids = file($opts->file, FILE_IGNORE_NEW_LINES);
// } else {
//   $pids = $ri->findByCModel("emory-control:ETD-1.0");
// }
//
// $count = 0;
// print count($pids);
// foreach ($pids as $pid) {
//   $etd = new etd($pid);
//     if ($etd->rels_ext->status == 'published') {
//       print $etd->pid . "\n";
//       if (!$opts->noact) {
//         print "shit";
//       //   $response = $persis->generateDoi($etd);
//       //   if (preg_match('/^doi.{17}$/', $response)){
//       //       $etd->premis->addEvent("administrative",
//       //               "DOI generated " . $etd->mods->doi,
//       //               "success", array("software", "etd system"));
//       //       $etd->save('DOI Added.');
//       //   } else {
//       //       print_r($response);
//       //       print $response;
//       //   }
//       }
//       if ($count++ > 36){
//           break;
//       }
//     }
//   }
