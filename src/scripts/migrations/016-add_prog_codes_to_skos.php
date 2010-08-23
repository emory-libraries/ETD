#!/usr/bin/php -q
<?php
/**
 * Update SKOs programs with new element dc:identifier 
 * that contains the program-department code.
 * There could be more that one identifier for each collection.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/programs.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname updates program list to add program-degree codes in a dc:identifier element
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// Schools
// Top level for Rollins school
$schools = array("grad", "undergrad", "candler", "rollins");
     
     
/**
* @todo remove dept and subdept from the dataset, and calculate the collection path
* by writing a function in skosCollection to retrieve the collection path of the id.
* 
* @todo allow the identifiers elements to be cleaned up by deleting any element not 
* found in the identifiers array list.
*/ 
   
        
// Program Codes for all schools
$dataset = array(

// GSAS - Graduates
array("school"=>'grad', "dept"=>'humanities', "id"=>'arthistory', "identifiers"=>array('ARTHISTMA')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'complit', "identifiers"=>array('COMPLITMA','COMPLITPHD')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'english', "identifiers"=>array('ENGLISHMA','ENGLISHPHD')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'filmstudies', "identifiers"=>array('FILMSTMA')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'french', "identifiers"=>array('FRENCHPHD')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'ILA', "identifiers"=>array('ILAMA','ILAPHD')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'history', "identifiers"=>array('HISTORYMA','HISTORYMA')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'jewishstudies', "identifiers"=>array('JEWISHMA')),
//array("school"=>'grad', "dept"=>'', "id"=>'music', "identifiers"=>array('')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'philosophy', "identifiers"=>array('PHILMA','PHILPHD')),
array("school"=>'grad', "dept"=>'humanities', "id"=>'spanish', "identifiers"=>array('SPANISHMA','SPANISHPHD')),
//array("school"=>'grad', "dept"=>'humanities', "id"=>'american', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'comparative', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'humanities', "id"=>'ethics', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'historical', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'newtestament', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'religiouspractices', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'theological', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'', "id"=>'asian', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'biochem', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'genetics', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'immunology', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'microbiology', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'pharmacology', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'neuroscience', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'nutrition', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'population', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'biomedeng', "identifiers"=>array('')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'biostat', "identifiers"=>array('BIOSTATMS','BIOSTATPHD')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'chemistry', "identifiers"=>array('CHEMMS','CHEMPHD')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'clinicalresearch', "identifiers"=>array('CLNRSRCHMS')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'epi', "identifiers"=>array('EPIDMS','EPIDPHD')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'healthpolicy', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'mathcompsci', "identifiers"=>array('')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'nursing', "identifiers"=>array('NURSINGPHD')),
array("school"=>'grad', "dept"=>'natlhealthsci', "id"=>'physics', "identifiers"=>array('PHYSICSPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'anthro', "identifiers"=>array('ANTHMA','ANTHPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'business', "identifiers"=>array('BUSPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'econ', "identifiers"=>array('ECONMA','ECONPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'education', "identifiers"=>array('EDSMA','EDSPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'polisci', "identifiers"=>array('POLISCIMA','POLISCIPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'psychology', "identifiers"=>array('PSYCMA','PSYCPHD')),
//array("school"=>'grad', "dept"=>'socsci', "id"=>'clinicalpsych', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'socsci', "id"=>'cognitivepsych', "identifiers"=>array('')),
//array("school"=>'grad', "dept"=>'socsci', "id"=>'animalbehavior', "identifiers"=>array('')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'sociology', "identifiers"=>array('SOCMA','SOCPHD')),
array("school"=>'grad', "dept"=>'socsci', "id"=>'womensstudies', "identifiers"=>array('WMNSTMA','WMNSTPHD')),
  
// UCOL - Undergraduates
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uafr', "identifiers"=>array('AFSBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uamerican', "identifiers"=>array('AMERSTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uanc', "identifiers"=>array('ANCMEDBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uanthro', "identifiers"=>array('ANTHBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uarthist', "identifiers"=>array('ARTHISTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uarthistvis', "identifiers"=>array('ARTHVARTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uasian', "identifiers"=>array('ASIANAMBA')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'ubio', "identifiers"=>array('BIOLOGYBA','BIOLOGYBS','BIOLGYBSMS')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'uchem', "identifiers"=>array('CHEMBA','CHEMBS','CHEMBSMS')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uchinese', "identifiers"=>array('CHINLLBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uclassics', "identifiers"=>array('CLASSICSBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uclassichist', "identifiers"=>array('CLASHISTBA')),
//array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uclassicphil', "identifiers"=>array('')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ucomplit', "identifiers"=>array('LITBA')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'ucompsci', "identifiers"=>array('COMPSCIBA','COMPSCIBS')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'udance', "identifiers"=>array('DNCMVSTBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uecon', "identifiers"=>array('ECONBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'ueconhist', "identifiers"=>array('ECONHISTBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'ueconmath', "identifiers"=>array('ECONMATHBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'ued', "identifiers"=>array('EDSBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ueng', "identifiers"=>array('ENGLISHBA','ENGLSHBAMA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uengwri', "identifiers"=>array('ENGCWBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uenghist', "identifiers"=>array('ENVSBS','ENVSBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uenv', "identifiers"=>array('ENVSBA','ENVSBS')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ufilm', "identifiers"=>array('FILMSTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ufrench', "identifiers"=>array('FRENSTUDBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ugerman', "identifiers"=>array('GERMANSTBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uhist', "identifiers"=>array('HISTORYBA','HISTRYBAMA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uhistart', "identifiers"=>array('HSTARHSTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uids', "identifiers"=>array('IDSSCBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uintl', "identifiers"=>array('INTLSTUBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uitalian', "identifiers"=>array('ITALSTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ujapan', "identifiers"=>array('JAPANBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'ujewish', "identifiers"=>array('JEWISHBA')),
//array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'ujournalism', "identifiers"=>array('')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'ulatinam', "identifiers"=>array('LACSBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uling', "identifiers"=>array('LINGBA')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'umath', "identifiers"=>array('MATHBA','MATHBS')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'umathcs', "identifiers"=>array('MATHCSBS','MATHCSBSMS')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'umathpolisci', "identifiers"=>array('MATHPOLSBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'umideast', "identifiers"=>array('MESASBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'umusic', "identifiers"=>array('MUSICBA')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'uneuro', "identifiers"=>array('NEUROBBBS')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uphil', "identifiers"=>array('PHILBA','PHILBAMA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uphilrel', "identifiers"=>array('PHILRELBA')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'uphysics', "identifiers"=>array('PHYSICSBA','PHYSICSBS')),
array("school"=>'undergrad', "dept"=>'unatlhealthsci', "id"=>'uphysastronomy', "identifiers"=>array('PHYSASTBA','PHYSASTBS')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'upolisci', "identifiers"=>array('POLISCIBA','POLISCBAMA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'upsych', "identifiers"=>array('PSYCHBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'upsychling', "identifiers"=>array('PSYCLINGBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urel', "identifiers"=>array('RELBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urelanthro', "identifiers"=>array('RELANTHBA')),
//array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urelciv', "identifiers"=>array('')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urelhist', "identifiers"=>array('RELHISTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urelsoc', "identifiers"=>array('RELSOCBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'urussian', "identifiers"=>array('RUSSLCBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'usociology', "identifiers"=>array('SOCBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uspanish', "identifiers"=>array('SPANISHBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'utheater', "identifiers"=>array('THEASTBA')),
array("school"=>'undergrad', "dept"=>'usocsci', "id"=>'uwomens', "identifiers"=>array('WOMENSTBA')),
array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'uclassicciv', "identifiers"=>array('CLCIVBA')),

// THEO - Candler School of Theology
array("school"=>'candler', "id"=>'cstdivinity', "identifiers"=>array('MDVDIVIN')),
array("school"=>'candler', "id"=>'csttheo', "identifiers"=>array('THEOLSTMTS')),
array("school"=>'candler', "id"=>'cstpc', "identifiers"=>array('THDCOUNSEL')),

// PUBH - Rollins School of Public Health
array("school"=>'rollins', "dept"=>'cmph', "id"=>'apepi', "identifiers"=>array('APEPIMPH')),
array("school"=>'rollins', "dept"=>'cmph', "id"=>'hcom', "identifiers"=>array('HCOMMPH')),
array("school"=>'rollins', "dept"=>'cmph', "id"=>'mchepi', "identifiers"=>array('MCHEPIMPH')),
array("school"=>'rollins', "dept"=>'cmph', "id"=>'ms', "identifiers"=>array('MSMPH')),
array("school"=>'rollins', "dept"=>'cmph', "id"=>'ps', "identifiers"=>array('PSMPH')),
array("school"=>'rollins', "dept"=>'bb', "id"=>'bios',  "identifiers"=>array('BIOSMPH','BIOSMSPH')),
array("school"=>'rollins', "dept"=>'bb', "id"=>'info',  "identifiers"=>array('INFOMSPH')),
array("school"=>'rollins', "dept" =>'eh', "id"=>'eoh', "identifiers"=>array('EOHMPH')),
array("school"=>'rollins', "dept" =>'eh', "id"=>'eohepi', "identifiers"=>array('EOHEPIMPH')),
array("school"=>'rollins', "dept" =>'eh', "id"=>'eohih', "identifiers"=>array('EOHIHMPH')),
array("school"=>'rollins', "dept" =>'ep', "id"=>'epi', "identifiers"=>array('EPIMPH', 'EPIMPSH')),
array("school"=>'rollins', "dept" =>'ep', "id"=>'glepi', "identifiers"=>array('GLEPIMPH','GLEPIMSPH')),
);


$programs = new foxmlPrograms();
$skos = $programs->skos;
        
//add school identifiers to programs
foreach ($dataset as $data) {
    
  foreach ($data['identifiers'] as $identifier) {  // allow for more than one dc_id
    //print "\nProcess {$data['school']} dept={$data['dept']} prog={$data['id']} {$identifier}";    
    if ($skos->findIdentifier($identifier) == null) {      
      $logger->info("{$data['school']} dept={$data['dept']} prog={$data['id']} does not yet include dc:identifier {$identifier}, adding.\n");
      if (isset($data['dept'])) {
        $skos->{$data['school']}->{$data['dept']}->{$data['id']}->collection->addIdentifier($identifier);
      }
      else {
        $skos->{$data['school']}->{$data['id']}->collection->addIdentifier($identifier);
      }     
    } else {
      if (isset($data['dept'])) {
        $logger->info("{$data['school']} dept={$data['dept']} prog={$data['id']} dc:identifier {$identifier} is already present");
      }
      else {
        $logger->info("{$data['school']} prog={$data['id']} dc:identifier {$identifier} is already present");
      }      
    }
  }
}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to include identifiers");
    if ($result) {
      $logger->info("Successfully updated identifiers (" . $programs->pid .
        ") at $result");
    } else {
      $logger->err("Error updating identifiers (" . $programs->pid . ")");
    }
  } else {  // no-act mode, simulate saving
    $logger->info("Updating identifiers " .  $programs->pid . " (simulated)");
  }

} else {
  $logger->info("Program listing is unchanged, not saving.");
}

?>
