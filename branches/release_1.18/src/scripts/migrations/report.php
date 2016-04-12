#!/usr/bin/php -q
<?php
/**
 * This script cleans known xacml problems on existing records.
 *  - remove draft rule if status is not draft
 *  - remove view rule and add new one from current template, to
 *    update graduate coordinator portion of the rule (August 2008)
 *  - update draft and published rules to latest version of
 *    xacml policy rules, as appropriate for etd status  (October 2008)
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

function readableSize($size) {
    $bytes = array('B','KB','MB','GB','TB');
    foreach($bytes as $val) {
      if($size > 1024){
	$size = $size / 1024;
      }else{
	break;
      }
    }
    return round($size, 1)." ".$val;
}





// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files;
// set up connection objects for fedora, solr, ESD, and stats db
require_once("bootstrap.php");

// The selection option allows a selection of pids to be processed.
// Otherwise, the php out of memory error occurs when processing entire amount.
// Setting selection to 1, will process all "emory:1*" pids.
// Syntax of a typical call would be ./002-clean_xacml.php -s 1 -v debug
$getopts = array_merge(
           array('pid|p=s'      => 'Clean xacml on one etd record only'),
           array('selection|s=i'  => 'Selection of pids to process (0-9)'),           
           $common_getopts  // use default verbose and noact opts from bootstrap
           );

$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname goes through all etd records and cleans object xacml policy
 
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

$counts = array();
$total =0;


// find *all* records, no matter their status, etc.
$etdSet = new EtdSet(array(), null, 'find');
$logger->info("EtdSet found = [" . $etdSet->count() . "] etd records.");

$paginator = new Zend_Paginator($etdSet);

for ($page = 0; $page<sizeof($paginator); $page++) {
  $current_page = $page+1;
  $paginator->setCurrentPageNumber($current_page);
  print $current_page . " of " .  sizeof($paginator) . "\n";

  foreach ($paginator as $etd) {
    $total++;
    $logger->info( $etd->pid);
    
    foreach($etd->pdfs as $file){
        try{
            $logger->info( $file->file->mimetype . " " . $file->dc->filesize);
            $counts[$file->file->mimetype][0]+=1;
            $counts[$file->file->mimetype][1]+=$file->dc->filesize;
       } catch(Exception $e){}
    }

    foreach($etd->originals as $file){
        try{
            $logger->info( $file->file->mimetype . " " . $file->dc->filesize);
            $counts[$file->file->mimetype][0]+=1;
            $counts[$file->file->mimetype][1]+=$file->dc->filesize;
        } catch (Exception $e){}
       }
    
    foreach($etd->supplements as $file){
        try{
            $logger->info( $file->file->mimetype . " " . $file->dc->filesize);
            $counts[$file->file->mimetype][0]+=1;
            $counts[$file->file->mimetype][1]+=$file->dc->filesize;
        } catch(Exception $e){}
    }

  }
}

print "\n\nTotal ETDs: " . $total . "\n";
#print_r($counts); 

foreach($counts as $type => $info){
     print $type . " " . $info[0] . " " . readableSize($info[1])  . "\n"; 
} 

