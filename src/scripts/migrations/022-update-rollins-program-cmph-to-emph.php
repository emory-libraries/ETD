#!/usr/bin/php -q
<?php
/**
 * Update rsph-cmph to rsph-emph.
 * NOTE: THIS DOES NOT WORK. Please see changelog for ....
 * 
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

exit('This migration DOES NOT WORK. Please see line 57 and changelog for 1.8 :('. PHP_EOL);
// set working directory to the main scripts directory so all the paths work the same
chdir("..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/programs.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname updates program list to add Rollins School of Public Health with its subdivisions
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

$cfg = Zend_Registry::get('config');

//use dev credentials to connect
try {
  $fedora_opts = $fedora_cfg->toArray();
  // use default fedora config opts but with maintenance account credentials
  $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
  $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
  $maintenance_fedora = new FedoraConnection($fedora_opts);
      } catch (FedoraNotAvailable $e) {
  $this->logger->err("Error connecting to Fedora with maintenance account - " . $e->getMessage());
  return;
}
Zend_Registry::set("fedora", $maintenance_fedora);

$programs = new foxmlPrograms();

$skos = $programs->skos;

if ($skos->findLabelbyId('#rsph-cmph') != null) {
    $skos->rollins->collection->members_by_id['rsph-cmph']->label = 'Executive Masters of Public Health';
    $skos->rollins->collection->members_by_id['rsph-cmph']->id = 'rsph-emph';
    //TODO This is what doesn't work. The `id` for the collection does not update.
    $skos->{"rsph-cmph"}->collection->id = 'rsph-emph';

}
else {
echo 'nothing';
}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to to reflect name change for Executive Masters of Public Health");
    if ($result) {
      $logger->info("Successfully updated progams (" . $programs->pid .
        ") at $result");
    } else {
      $logger->err("Error updating programs (" . $programs->pid . ")");
    }
  } else {  // no-act mode, simulate saving
    $logger->info("Updating programs " .  $programs->pid . " (simulated)");
  }

} else {
  $logger->info("Program listing is unchanged, not saving.");
}
