#!/usr/bin/php -q
<?php
/**
 * Update SKOs program codes for all schools.
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
 $scriptname updates program list to add program codes to all schools
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


// Program Codes for all schools
$degcodes = array(

// GSAS - Graduates
array('school' => 'grad', 'id' => '#arthistory', 'dc_id' => array('ARTHISTMA')),
array('school' => 'grad', 'id' => '#complit', 'dc_id' => array('COMPLITMA','COMPLITPHD')),
array('school' => 'grad', 'id' => '#english', 'dc_id' => array('ENGLISHMA','ENGLISHPHD')),
array('school' => 'grad', 'id' => '#filmstudies', 'dc_id' => array('FILMSTMA')),
array('school' => 'grad', 'id' => '#french', 'dc_id' => array('FRENCHPHD')),
array('school' => 'grad', 'id' => '#ILA', 'dc_id' => array('ILAMA','ILAPHD')),
array('school' => 'grad', 'id' => '#history', 'dc_id' => array('HISTORYMA','HISTORYMA')),
array('school' => 'grad', 'id' => '#jewishstudies', 'dc_id' => array('JEWISHMA')),
//array('school' => 'grad', 'id' => '#music', 'dc_id' => array('')),
array('school' => 'grad', 'id' => '#philosophy', 'dc_id' => array('PHILMA','PHILPHD')),
array('school' => 'grad', 'id' => '#spanish', 'dc_id' => array('SPANISHMA','SPANISHPHD')),
array('school' => 'grad', 'id' => '#religion', 'dc_id' => array('RELPHD')),
//array('school' => 'grad', 'id' => '#american', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#comparative', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#ethics', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#hebrew', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#historical', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#newtestament', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#religiouspractices', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#theological', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#asian', 'dc_id' => array('')),
array('school' => 'grad', 'id' => '#behavsci', 'dc_id' => array('SPHBSHEPHD')),
array('school' => 'grad', 'id' => '#biosci', 'dc_id' => array('BBSMS','BBSPHD')),
//array('school' => 'grad', 'id' => '#biochem', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#genetics', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#immunology', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#microbiology', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#pharmacology', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#neuroscience', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#nutrition', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#population', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#biomedeng', 'dc_id' => array('')),
array('school' => 'grad', 'id' => '#biostat', 'dc_id' => array('BIOSTATMS','BIOSTATPHD')),
array('school' => 'grad', 'id' => '#chemistry', 'dc_id' => array('CHEMMS','CHEMPHD')),
array('school' => 'grad', 'id' => '#clinicalresearch', 'dc_id' => array('CLNRSRCHMS')),
array('school' => 'grad', 'id' => '#epi', 'dc_id' => array('EPIDMS','EPIDPHD')),
//array('school' => 'grad', 'id' => '#healthpolicy', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#mathcompsci', 'dc_id' => array('')),
array('school' => 'grad', 'id' => '#nursing', 'dc_id' => array('NURSINGPHD')),
array('school' => 'grad', 'id' => '#physics', 'dc_id' => array('PHYSICSPHD')),
array('school' => 'grad', 'id' => '#anthro', 'dc_id' => array('ANTHMA','ANTHPHD')),
array('school' => 'grad', 'id' => '#business', 'dc_id' => array('BUSPHD')),
array('school' => 'grad', 'id' => '#econ', 'dc_id' => array('ECONMA','ECONPHD')),
array('school' => 'grad', 'id' => '#education', 'dc_id' => array('EDSMA','EDSPHD')),
array('school' => 'grad', 'id' => '#polisci', 'dc_id' => array('POLISCIMA''POLISCIPHD')),
array('school' => 'grad', 'id' => '#psychology', 'dc_id' => array('PSYCMA','PSYCPHD')),
//array('school' => 'grad', 'id' => '#clinicalpsych', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#cognitivepsych', 'dc_id' => array('')),
//array('school' => 'grad', 'id' => '#animalbehavior', 'dc_id' => array('')),
array('school' => 'grad', 'id' => '#sociology', 'dc_id' => array('SOCMA','SOCPHD')),
array('school' => 'grad', 'id' => '#womensstudies', 'dc_id' => array('WMNSTMA','WMNSTPHD')),
  
// UCOL - Undergraduates
array('school' => 'undergrad', 'id' => '#uafr', 'dc_id' => array('AFSBA')),
array('school' => 'undergrad', 'id' => '#uamerican', 'dc_id' => array('AMERSTBA')),
array('school' => 'undergrad', 'id' => '#uanc', 'dc_id' => array('ANCMEDBA')),
array('school' => 'undergrad', 'id' => '#uanthro', 'dc_id' => array('ANTHBA')),
array('school' => 'undergrad', 'id' => '#uarthist', 'dc_id' => array('ARTHISTBA')),
array('school' => 'undergrad', 'id' => '#uarthistvis', 'dc_id' => array('ARTHVARTBA')),
array('school' => 'undergrad', 'id' => '#uasian', 'dc_id' => array('ASIANAMBA')),
array('school' => 'undergrad', 'id' => '#ubio', 'dc_id' => array('BIOLOGYBA','BIOLOGYBS','BIOLGYBSMS')),
array('school' => 'undergrad', 'id' => '#uchem', 'dc_id' => array('CHEMBA','CHEMBS','CHEMBSMS')),
array('school' => 'undergrad', 'id' => '#uchinese', 'dc_id' => array('CHINLLBA')),
array('school' => 'undergrad', 'id' => '#uclassics', 'dc_id' => array('CLASSICSBA')),
array('school' => 'undergrad', 'id' => '#uclassichist', 'dc_id' => array('CLASHISTBA')),
//array('school' => 'undergrad', 'id' => '#uclassicphil', 'dc_id' => array('')),
array('school' => 'undergrad', 'id' => '#ucomplit', 'dc_id' => array('LITBA')),
array('school' => 'undergrad', 'id' => '#ucompsci', 'dc_id' => array('COMPSCIBA','COMPSCIBS')),
array('school' => 'undergrad', 'id' => '#udance', 'dc_id' => array('DNCMVSTBA')),
array('school' => 'undergrad', 'id' => '#uecon', 'dc_id' => array('ECONBA')),
array('school' => 'undergrad', 'id' => '#ueconhist', 'dc_id' => array('ECONHISTBA')),
array('school' => 'undergrad', 'id' => '#ueconmath', 'dc_id' => array('ECONMATHBA')),
array('school' => 'undergrad', 'id' => '#ued', 'dc_id' => array('EDSBA')),
array('school' => 'undergrad', 'id' => '#ueng', 'dc_id' => array('ENGLISHBA','ENGLSHBAMA')),
array('school' => 'undergrad', 'id' => '#uengwri', 'dc_id' => array('ENGCWBA')),
array('school' => 'undergrad', 'id' => '#uenghist', 'dc_id' => array('ENVSBS','ENVSBA')),
array('school' => 'undergrad', 'id' => '#uenv', 'dc_id' => array('ENVSBA','ENVSBS')),
array('school' => 'undergrad', 'id' => '#ufilm', 'dc_id' => array('FILMSTBA')),
array('school' => 'undergrad', 'id' => '#ufrench', 'dc_id' => array('FRENSTUDBA')),
array('school' => 'undergrad', 'id' => '#ugerman', 'dc_id' => array('GERMANSTBA')),
array('school' => 'undergrad', 'id' => '#uhist', 'dc_id' => array('HISTORYBA','HISTRYBAMA')),
array('school' => 'undergrad', 'id' => '#uhistart', 'dc_id' => array('HSTARHSTBA')),
array('school' => 'undergrad', 'id' => '#uids', 'dc_id' => array('IDSSCBA')),
array('school' => 'undergrad', 'id' => '#uintl', 'dc_id' => array('INTLSTUBA')),
array('school' => 'undergrad', 'id' => '#uitalian', 'dc_id' => array('ITALSTBA')),
array('school' => 'undergrad', 'id' => '#ujapan', 'dc_id' => array('JAPANBA')),
array('school' => 'undergrad', 'id' => '#ujewish', 'dc_id' => array('JEWISHBA')),
//array('school' => 'undergrad', 'id' => '#ujournalism', 'dc_id' => array('')),
array('school' => 'undergrad', 'id' => '#ulatinam', 'dc_id' => array('LACSBA')),
array('school' => 'undergrad', 'id' => '#uling', 'dc_id' => array('LINGBA')),
array('school' => 'undergrad', 'id' => '#umath', 'dc_id' => array('MATHBA','MATHBS')),
array('school' => 'undergrad', 'id' => '#umathcs', 'dc_id' => array('MATHCSBS','MATHCSBSMS')),
array('school' => 'undergrad', 'id' => '#umathpolisci', 'dc_id' => array('MATHPOLSBA')),
array('school' => 'undergrad', 'id' => '#umideast', 'dc_id' => array('MESASBA')),
array('school' => 'undergrad', 'id' => '#umusic', 'dc_id' => array('MUSICBA')),
array('school' => 'undergrad', 'id' => '#uneuro', 'dc_id' => array('NEUROBBBS')),
array('school' => 'undergrad', 'id' => '#uphil', 'dc_id' => array('PHILBA','PHILBAMA')),
array('school' => 'undergrad', 'id' => '#uphilrel', 'dc_id' => array('PHILRELBA')),
array('school' => 'undergrad', 'id' => '#uphysics', 'dc_id' => array('PHYSICSBA','PHYSICSBS')),
array('school' => 'undergrad', 'id' => '#uphysastronomy', 'dc_id' => array('PHYSASTBA','PHYSASTBS')),
array('school' => 'undergrad', 'id' => '#upolisci', 'dc_id' => array('POLISCIBA','POLISCBAMA')),
array('school' => 'undergrad', 'id' => '#upsych', 'dc_id' => array('PSYCHBA')),
array('school' => 'undergrad', 'id' => '#upsychling', 'dc_id' => array('PSYCLINGBA')),
array('school' => 'undergrad', 'id' => '#urel', 'dc_id' => array('RELBA')),
array('school' => 'undergrad', 'id' => '#urelanthro', 'dc_id' => array('RELANTHBA')),
//array('school' => 'undergrad', 'id' => '#urelciv', 'dc_id' => array('')),
array('school' => 'undergrad', 'id' => '#urelhist', 'dc_id' => array('RELHISTBA')),
array('school' => 'undergrad', 'id' => '#urelsoc', 'dc_id' => array('RELSOCBA')),
array('school' => 'undergrad', 'id' => '#urussian', 'dc_id' => array('RUSSLCBA')),
array('school' => 'undergrad', 'id' => '#usociology', 'dc_id' => array('SOCBA')),
array('school' => 'undergrad', 'id' => '#uspanish', 'dc_id' => array('SPANISHBA')),
array('school' => 'undergrad', 'id' => '#utheater', 'dc_id' => array('THEASTBA')),
array('school' => 'undergrad', 'id' => '#uwomens', 'dc_id' => array('WOMENSTBA')),
array('school' => 'undergrad', 'id' => '#uclassicciv', 'dc_id' => array('CLCIVBA')),

// THEO - Candler School of Theology
array('school' => 'candler', 'id' => '#cstdivinity', 'dc_id' => array('MDVDIVIN')),
array('school' => 'candler', 'id' => '#csttheo', 'dc_id' => array('THEOLSTMTS')),
array('school' => 'candler', 'id' => '#cstpc', 'dc_id' => array('THDCOUNSEL')),

// PUBH - Rollins School of Public Health
array('school' => 'rollins', 'id' => '#apepi', 'dc_id' => array('APEPIMPH')),
array('school' => 'rollins', 'id' => '#hcom', 'dc_id' => array('HCOMMPH')),
array('school' => 'rollins', 'id' => '#mchepi', 'dc_id' => array('MCHEPIMPH')),
array('school' => 'rollins', 'id' => '#ms', 'dc_id' => array('MSMPH')),
array('school' => 'rollins', 'id' => '#ps', 'dc_id' => array('PSMPH')),
array('school' => 'rollins', 'id' => '#bios',  'dc_id' => array('BIOSMPH','BIOSMSPH')),
array('school' => 'rollins', 'id' => '#info',  'dc_id' => array('INFOMSPH')),
array('school' => 'rollins', 'id' => '#eoh', 'dc_id' => array('EOHMPH')),
array('school' => 'rollins', 'id' => '#eohepi', 'dc_id' => array('EOHEPIMPH')),
array('school' => 'rollins', 'id' => '#eohih', 'dc_id' => array('EOHIHMPH')),
array('school' => 'rollins', 'id' => '#epi', 'dc_id' => array('EPIMPH', 'EPIMPSH')),
array('school' => 'rollins', 'id' => '#glepi', 'dc_id' => array('GLEPIMPH','GLEPIMSPH'))
);

?>
