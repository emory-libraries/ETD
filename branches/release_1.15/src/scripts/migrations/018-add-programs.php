#!/usr/bin/php -q
<?php
/**
 * Update ETDs program listings to a new school, department or program.
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
 $scriptname updates program list with new schools, departments and programs
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// Get the pid from the config.xml file
if (! Zend_Registry::isRegistered("config")) {
  throw new FoxmlException("Configuration not registered, cannot retrieve pid");
}
$config = Zend_Registry::get("config");
if (! isset($config->programs_collection->pid) || $config->programs_collection->pid == "") {
  throw new FoxmlException("Configuration does not contain vocabulary pid, cannot initialize");
}
$pid = $config->programs_collection->pid;
     
// programs 
// department code does not have # because it is only referenced at this point and is referenced without the #
$program_data = array(
array("id"=>'grad', "label"=>'Laney Graduate School'),

array("level01"=>'grad', "id"=>'humanities', "label"=>'Humanities'),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'arthistory', "label"=>'Art History', "identifiers"=>array('ARTHISTMA')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'complit', "label"=>'Comparative Literature', "identifiers"=>array('COMPLITMA','COMPLITPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'english', "label"=>'English', "identifiers"=>array('ENGLISHMA','ENGLISHPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'filmstudies', "label"=>'Film Studies', "identifiers"=>array('FILMSTMA')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'french', "label"=>'French', "identifiers"=>array('FRENCHPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'ILA', "label"=>'Graduate Institute of the Liberal Arts', "identifiers"=>array('ILAMA','ILAPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'history', "label"=>'History', "identifiers"=>array('HISTORYMA')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'jewishstudies', "label"=>'Jewish Studies', "identifiers"=>array('JEWISHMA')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'music', "label"=>'Music'),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'philosophy', "label"=>'Philosophy', "identifiers"=>array('PHILMA','PHILPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'spanish', "label"=>'Spanish', "identifiers"=>array('SPANISHMA','SPANISHPHD')),
array("level01"=>'grad', "level02"=>'humanities', "id"=>'religion', "label"=>'Religion'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'american', "label"=>'American Religious Cultures'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'comparative', "label"=>'Comparative Literature and Religion'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'ethics', "label"=>'Ethics and Society'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'hebrew', "label"=>'Hebrew Bible'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'historical', "label"=>'Historical Studies in Theology and Religion'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'newtestament', "label"=>'New Testament'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'religiouspractices', "label"=>'Person, Community and Religious Practices'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'theological', "label"=>'Theological Studies'),
array("level01"=>'grad', "level02"=>'humanities', "level03"=>'religion', "id"=>'asian', "label"=>'West and South Asian Religions'),

array("level01"=>'grad', "id"=>'natlhealthsci', "label"=>'Natural/Health Sciences'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'biomedeng', "label"=>'Biomedical Engineering'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'biostat', "label"=>'Biostatistics', "identifiers"=>array('BIOSTATMS','BIOSTATPHD')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'chemistry', "label"=>'Chemistry', "identifiers"=>array('CHEMMS','CHEMPHD')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'clinicalresearch', "label"=>'Clinical Research Curriculum Program', "identifiers"=>array('CLNRSRCHMS')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'epi', "label"=>'Epidemiology', "identifiers"=>array('EPIDMS','EPIDPHD')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'healthpolicy', "label"=>'Health Services and Research Health Policy'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'mathcompsci', "label"=>'Math and Computer Science'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'nursing', "label"=>'Nursing', "identifiers"=>array('NURSINGPHD')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'physics', "label"=>'Physics', "identifiers"=>array('PHYSICSPHD')),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'behavsci', "label"=>'Behavioral Sciences and Health Education'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'biosci', "label"=>'Biological and Biomedical Sciences'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'biochem', "label"=>'Biochemistry, Cell &amp; Developmental Biology'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'genetics', "label"=>'Genetics and Molecular Biology'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'immunology', "label"=>'Immunology'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'microbiology', "label"=>'Microbiology &amp; Molecular Genetics'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'pharmacology', "label"=>'Molecular &amp; Systems Pharmacology'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'neuroscience', "label"=>'Neuroscience'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'nutrition', "label"=>'Nutrition and Health Sciences'),
array("level01"=>'grad', "level02"=>'natlhealthsci', "level03"=>'biosci', "id"=>'population', "label"=>'Population Biology, Ecology &amp; Evolution'),

array("level01"=>'grad', "id"=>'socsci', "label"=>'Social Sciences'), 
array("level01"=>'grad', "level02"=>'socsci', "id"=>'anthro', "label"=>'Anthropology', "identifiers"=>array('ANTHMA','ANTHPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'business', "label"=>'Business', "identifiers"=>array('BUSPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'econ', "label"=>'Economics', "identifiers"=>array('ECONMA','ECONPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'education', "label"=>'Educational Studies', "identifiers"=>array('EDSMA','EDSPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'polisci', "label"=>'Political Science', "identifiers"=>array('POLISCIMA','POLISCIPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'psychology', "label"=>'Psychology', "identifiers"=>array('PSYCMA','PSYCPHD')),
array("level01"=>'grad', "level02"=>'socsci', "level03"=>'psychology', "id"=>'clinicalpsych', "label"=>'Clinical Psychology'),
array("level01"=>'grad', "level02"=>'socsci', "level03"=>'psychology', "id"=>'cognitivepsych', "label"=>'Cognitive and Developmental Psychology'),
array("level01"=>'grad', "level02"=>'socsci', "level03"=>'psychology', "id"=>'animalbehavior', "label"=>'Neuroscience and Animal Behavior'),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'sociology', "label"=>'Sociology', "identifiers"=>array('SOCMA','SOCPHD')),
array("level01"=>'grad', "level02"=>'socsci', "id"=>'womensstudies', "label"=>"Women's Studies", "identifiers"=>array('WMNSTMA','WMNSTPHD')),
  
// UCOL - Undergraduates
array("id"=>'undergrad', "label"=>'Emory College'),
array("level01"=>'undergrad', "id"=>'uhumanities', "label"=>'Humanities'),    
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uafr', "label"=>'African Studies', "identifiers"=>array('AFSBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uaas', "label"=>'African American Studies', "identifiers"=>array('AASBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uamerican', "label"=>'American Studies', "identifiers"=>array('AMERSTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uanc', "label"=>'Ancient Mediterranean Studies', "identifiers"=>array('ANCMEDBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uarthist', "label"=>'Art History', "identifiers"=>array('ARTHISTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uarthistvis', "label"=>'Art History and Visual Arts', "identifiers"=>array('ARTHVARTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uasian', "label"=>'Asian and Asian American Studies', "identifiers"=>array('ASIANAMBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uchinese', "label"=>'Chinese Language and Literature', "identifiers"=>array('CHINLLBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uclassics', "label"=>'Classics', "identifiers"=>array('CLASSICSBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uclassichist', "label"=>'Classics and History', "identifiers"=>array('CLASHISTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uclassicphil', "label"=>'Classics and Philosophy'),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ucomplit', "label"=>'Comparative Literature', "identifiers"=>array('LITBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'udance', "label"=>'Dance and Movement Studies', "identifiers"=>array('DNCMVSTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ueng', "label"=>'English', "identifiers"=>array('ENGLISHBA','ENGLSHBAMA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uengwri', "label"=>'English and Creative Writing', "identifiers"=>array('ENGCWBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uenghist', "label"=>'English and History', "identifiers"=>array('ENVSBS','ENVSBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ufilm', "label"=>'Film Studies', "identifiers"=>array('FILMSTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ufrench', "label"=>'French Studies', "identifiers"=>array('FRENSTUDBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ugerman', "label"=>'German', "identifiers"=>array('GERMANSTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uids', "label"=>'Interdisciplinary Studies in Society and Culture', "identifiers"=>array('IDSSCBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uitalian', "label"=>'Italian Studies', "identifiers"=>array('ITALSTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ujapan', "label"=>'Japanese', "identifiers"=>array('JAPANBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'ujewish', "label"=>'Jewish Studies', "identifiers"=>array('JEWISHBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uling', "label"=>'Linguistics', "identifiers"=>array('LINGBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'umideast', "label"=>'Middle Eastern Studies', "identifiers"=>array('MESASBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'umusic', "label"=>'Music', "identifiers"=>array('MUSICBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uphil', "label"=>'Philosophy', "identifiers"=>array('PHILBA','PHILBAMA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uphilrel', "label"=>'Philosophy and Religion', "identifiers"=>array('PHILRELBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uplaywrt', "label"=>'Playwriting', "identifiers"=>array('PLAYWRTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urel', "label"=>'Religion', "identifiers"=>array('RELBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urelanthro', "label"=>'Religion and Anthropology', "identifiers"=>array('RELANTHBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urelciv', "label"=>'Religion and Classical Civilization'),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urelhist', "label"=>'Religion and History', "identifiers"=>array('RELHISTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urelsoc', "label"=>'Religion and Sociology', "identifiers"=>array('RELSOCBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'urussian', "label"=>'Russian', "identifiers"=>array('RUSSLCBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uspanish', "label"=>'Spanish', "identifiers"=>array('SPANISHBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'utheater', "label"=>'Theater Studies', "identifiers"=>array('THEASTBA')),
array("level01"=>'undergrad', "level02"=>'uhumanities', "id"=>'uclassicciv', "label"=>'Classical Civilizations, Greek, and Latin', "identifiers"=>array('CLCIVBA')),

array("level01"=>'undergrad', "id"=>'unatlhealthsci', "label"=>'Natural Sciences and Mathematics'),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'ubio', "label"=>'Biology', "identifiers"=>array('BIOLOGYBA','BIOLOGYBS','BIOLGYBSMS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'uchem', "label"=>'Chemistry', "identifiers"=>array('CHEMBA','CHEMBS','CHEMBSMS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'ucompsci', "label"=>'Computer Science', "identifiers"=>array('COMPSCIBA','COMPSCIBS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'umath', "label"=>'Mathematics', "identifiers"=>array('MATHBA','MATHBS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'umathcs', "label"=>'Mathematics and Computer Science', "identifiers"=>array('MATHCSBS','MATHCSBSMS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'umathpolisci', "label"=>'Mathematics and Political Science', "identifiers"=>array('MATHPOLSBA')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'uneuro', "label"=>'Neuroscience and Behavioral Biology', "identifiers"=>array('NEUROBBBS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'uphysics', "label"=>'Physics', "identifiers"=>array('PHYSICSBA','PHYSICSBS')),
array("level01"=>'undergrad', "level02"=>'unatlhealthsci', "id"=>'uphysastronomy', "label"=>'Physics and Astronomy', "identifiers"=>array('PHYSASTBA','PHYSASTBS')),

array("level01"=>'undergrad', "id"=>'usocsci', "label"=>'Social Sciences'),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uanthro', "label"=>'Anthropology', "identifiers"=>array('ANTHBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uecon', "label"=>'Economics', "identifiers"=>array('ECONBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'ueconhist', "label"=>'Economics and History', "identifiers"=>array('ECONHISTBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'ueconmath', "label"=>'Economics and Mathematics', "identifiers"=>array('ECONMATHBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'ued', "label"=>'Educational Studies', "identifiers"=>array('EDSBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uenv', "label"=>'Environmental Studies', "identifiers"=>array('ENVSBA','ENVSBS')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uhist', "label"=>'History', "identifiers"=>array('HISTORYBA','HISTRYBAMA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uhistart', "label"=>'History and Art History', "identifiers"=>array('HSTARHSTBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uintl', "label"=>'International Studies', "identifiers"=>array('INTLSTUBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'ujournalism', "label"=>'Journalism'),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'ulatinam', "label"=>'Latin American and Caribbean Studies', "identifiers"=>array('LACSBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'usociology', "label"=>'Sociology', "identifiers"=>array('SOCBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'upolisci', "label"=>'Political Science', "identifiers"=>array('POLISCIBA','POLISCBAMA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'upsych', "label"=>'Psychology', "identifiers"=>array('PSYCHBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'upsychling', "label"=>'Psychology and Linguistics', "identifiers"=>array('PSYCLINGBA')),
array("level01"=>'undergrad', "level02"=>'usocsci', "id"=>'uwomens', "label"=>"Women's Studies", "identifiers"=>array('WOMENSTBA')),

// THEO - Candler School of Theology
array("id"=>'candler', "label"=>'Candler School of Theology'),
array("level01"=>'candler', "id"=>'cstdivinity', "label"=>'Divinity', "identifiers"=>array('MDVDIVIN')),
array("level01"=>'candler', "id"=>'csttheo', "label"=>'Theological Studies', "identifiers"=>array('THEOLSTMTS')),
array("level01"=>'candler', "id"=>'cstpc', "label"=>'Pastoral Counseling', "identifiers"=>array('THDCOUNSEL')),

// PUBH - Rollins School of Public Health
array("id"=>'rollins', "label"=>'Rollins School of Public Health'),
array("level01"=>'rollins', "id"=>'rsph-cmph', "label"=>'Career Masters of Public Health'),
array("level01"=>'rollins', "id"=>'rsph-bb', "label"=>'Biostatistics and Bioinformatics'),
array("level01"=>'rollins', "id"=>'rsph-eh', "label"=>'Environmental Health'),
array("level01"=>'rollins', "id"=>'rsph-ep', "label"=>'Epidemiology'),
array("level01"=>'rollins', "id"=>'rsph-hpm', "label"=>'Health Policy and Management', "identifiers"=>array('HPMMPH','HPMMSPH')),
array("level01"=>'rollins', "id"=>'rsph-ih', "label"=>'Hubert Department of Global Health', "identifiers"=>array('IHMPH')),
array("level01"=>'rollins', "id"=>'rsph-bshe', "label"=>'Behavioral Sciences and Health Education', "identifiers"=>array('BSHEMPH')),
array("level01"=>'rollins', "level02"=>'rsph-cmph', "id"=>'rsph-apepi', "label"=>'Applied Epidemiology', "identifiers"=>array('APEPIMPH')),
array("level01"=>'rollins', "level02"=>'rsph-cmph', "id"=>'rsph-hcom', "label"=>'Health Care Outcomes Management', "identifiers"=>array('HCOMMPH')),
array("level01"=>'rollins', "level02"=>'rsph-cmph', "id"=>'rsph-mchepi', "label"=>'Maternal and Child Health Epidemiology', "identifiers"=>array('MCHEPIMPH')),
array("level01"=>'rollins', "level02"=>'rsph-cmph', "id"=>'rsph-ms', "label"=>'Management Science', "identifiers"=>array('MSMPH')),
array("level01"=>'rollins', "level02"=>'rsph-cmph', "id"=>'rsph-ps', "label"=>'Prevention Science', "identifiers"=>array('PSMPH')),
array("level01"=>'rollins', "level02"=>'rsph-bb', "id"=>'rsph-bios', "label"=>'Biostatistics', "identifiers"=>array('BIOSMPH','BIOSMSPH')),
array("level01"=>'rollins', "level02"=>'rsph-bb', "id"=>'rsph-info', "label"=>'Public Health Informatics', "identifiers"=>array('INFOMSPH')),
array("level01"=>'rollins', "level02"=>'rsph-eh', "id"=>'rsph-eoh', "label"=>'Environmental and Occupational Health', "identifiers"=>array('EOHMPH')),
array("level01"=>'rollins', "level02"=>'rsph-eh', "id"=>'rsph-eohepi', "label"=>'Environmental &amp; Occupational Health Epidemiology', "identifiers"=>array('EOHEPIMPH')),
array("level01"=>'rollins', "level02"=>'rsph-eh', "id"=>'rsph-eohih', "label"=>'Global Environmental Health', "identifiers"=>array('EOHIHMPH')),
array("level01"=>'rollins', "level02"=>'rsph-ep', "id"=>'rsph-epi', "label"=>'Epidemiology', "identifiers"=>array('EPIMPH', 'EPIMPSH')),
array("level01"=>'rollins', "level02"=>'rsph-ep', "id"=>'rsph-glepi', "label"=>'Global Epidemiology', "identifiers"=>array('GLEPIMPH','GLEPIMSPH')),
);

  $programs = null;
  $programs  = new foxmlPrograms(); // access existing or create new object.
  
  if (!$programs) {
    $logger->err("Failed to create/access fedora object [$pid]");
    return;
  }
  $skos = $programs->skos;

  foreach ($program_data as $pgm) {

    if ($skos->findLabelbyId('#'.$pgm["id"]) == null) {
      $logger->info("Adding collection " . '#' . $pgm["id"]);
      
      // Add the collection
      if (isset($pgm["identifiers"])) {
        /**
         * @todo remove the dc identifiers not in this list or any duplicates.
         */      
        $skos->addCollection('#'.$pgm["id"], $pgm["label"], $pgm["identifiers"]);      
      }
      else {     
        $skos->addCollection('#'.$pgm["id"], $pgm["label"]);
      }
    } 
      
    // Set the collection as a member of another collection
    if (isset($pgm["level01"]) && isset($pgm["level02"]) && isset($pgm["level03"])) {
      if (! $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}");       
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label = $pgm["label"];   
      }    
    }
    elseif (isset($pgm["level01"]) && isset($pgm["level02"])) {  
      if (! $skos->{$pgm["level01"]}->{$pgm["level02"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}->{$pgm["level02"]}");       
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label = $pgm["label"];   
      }    
    }
    elseif (isset($pgm["level01"])) { 
      if (! $skos->{$pgm["level01"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}");       
        $skos->{$pgm["level01"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["id"]}->label = $pgm["label"];   
      }
    }
    else {    
      if (! $skos->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to programs collection.");       
        $skos->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["id"]}->label = $pgm["label"];   
      }          
    }
  }

  // if record has changed, save to Fedora; or create fedora object when not found.
  if ($skos->hasChanged()){  
    if (!$opts->noact) {
      try {
        $result = $programs->save("updating program list to include new data.");
      }
      catch (FedoraObjectNotFound $e) {
        $result = $programs->ingest("ingesting program list to include new data.");
      }
      if ($result) {
        $logger->info("Successfully updated programs ($pid) at $result");
      } else {
        $logger->err("Error updating programs $pid)");
      }
    } else {  // no-act mode, simulate saving
      $logger->info("Updating programs $pid (simulated)");
    }

  } else {
    $logger->info("Program listing is unchanged, not saving.");
  }
