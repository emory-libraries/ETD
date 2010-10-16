#!/usr/bin/php -q
<?php
/**
 * Adds the owner etdadmin to all the school collection objects and the program hierarchy object
 * THIS MUST BE RUN AS fedoraAdmin due xacml policy rule.  The rule does not allow any other user to update unless the owner is etdadmin.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/foxmlCollection.php");

$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname Adds the owner etdadmin to all the school collection objects and the program hierarchy object
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
//use maintenance_account to connect
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


$owner = $cfg->etdOwner; //ownerid
print "OWNER: $owner";
$programs = new foxmlCollection(); //programs object

//collection objects
$collections = array(
"emory-control:ETD-collection",
"emory-control:ETD-GradSchool-collection",
"emory-control:ETD-College-collection",
"emory-control:ETD-Candler-collection"
);


//Update ownerid
$programs->owner = $owner;

if(!$opts->noact){
   $success = $programs->save("Updated ownerid to $owner");
   if($success) $logger->info("Updated program hierarchy ownerid to $owner");
   else $logger->err("Failed to update program hierarchy ownerid to $owner");
}
else{
    $logger->info("Updated program hierarchy ownerid to $owner (simulated)");
}

foreach($collections as $col){
    $collection = new FedoraCollection($col);
    $collection->owner = $owner;

    if(!$opts->noact){
       $success = $collection->save("Updated ownerid to $owner");
       if($success) $logger->info("Updated collection $col ownerid to $owner");
       else $logger->err("Failed to update collection $col ownerid to $owner");
    }
    else{
        $logger->info("Updated collection $col ownerid to $owner (simulated)");
    }
}


?>
