#!/usr/bin/php -q
<?php
/**
 * Loades initial data to etd_admins table.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("../..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname Loades initial data to etd_admins table.
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


$db = Zend_Registry::get("etd-db"); //database connection
$table = "etd_admins"; //table name
$fields = array('netid', 'schoolid', 'programid'); //field names

//data
$data = array(

    array(strtoupper('mkrance'), 'PUBH', 'RSPH-APEPI'),
    array(strtoupper('mkrance'), 'PUBH', 'RSPH-HCOM'),
    array(strtoupper('mkrance'), 'PUBH', 'RSPH-MCHEPI'),
    array(strtoupper('mkrance'), 'PUBH', 'RSPH-MS'),
    array(strtoupper('mkrance'), 'PUBH', 'RSPH-PS'),
    array(strtoupper('ddunba2'), 'PUBH', 'RSPH-BSHE'),
    array(strtoupper('cdettme'), 'PUBH', 'RSPH-BSHE'),
    array(strtoupper('twachho'), 'PUBH', 'RSPH-BIOS'),
    array(strtoupper('twachho'), 'PUBH', 'RSPH-INFO'),
    array(strtoupper('ascarl'), 'PUBH', 'RSPH-EOH'),
    array(strtoupper('ascarl'), 'PUBH', 'RSPH-EOHEPI'),
    array(strtoupper('ascarl'), 'PUBH', 'RSPH-EOHIH'),
    array(strtoupper('jtenley'), 'PUBH', 'RSPH-EPI'),
    array(strtoupper('jtenley'), 'PUBH', 'RSPH-GLEPI'),
    array(strtoupper('kwollen'), 'PUBH', 'RSPH-HPM'),
    array(strtoupper('arozo'), 'PUBH', 'RSPH-IH'),
    array(strtoupper('tnash'), 'PUBH', 'RSPH-IH')
);


foreach ($data as $row){
    $values = array_combine($fields, $row);

    //see if record already exists
    $result = $db->select()
    ->from($table)
    ->where('netid = ? AND schoolid = ? AND programid = ?')
    ->bind(array(':netid' => $values['netid'], ':schoolid' => $values['schoolid'], ':programid' => $values['programid']));
    $result = $db->fetchall($result);
    
    if($opts->noact){
        if(!$result)
        $logger->info("Would have inserted: {$values['netid']} {$values['schoolid']} {$values['programid']}");
        else
        $logger->info("Would not have inserted alreay exists: {$values['netid']} {$values['schoolid']} {$values['programid']}");
    }
    else{
        try{
            if(!$result){
                $db->insert($table, $values);
                $logger->info("Inserted: {$values['netid']} {$values['schoolid']} {$values['programid']}");
            }
            else{
                $logger->info("Not Inserting alreay exists: {$values['netid']} {$values['schoolid']} {$values['programid']}");
            }

        }catch(Exception $e){
            $logger->err($e->getMessage());
        }

    }
}

?>
