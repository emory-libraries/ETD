#!/usr/bin/php -q
<?php

// ZendFramework, etc.
ini_set("include_path", "../app/::../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

require("Zend/Loader.php");
Zend_Loader::registerAutoload();

require_once("models/stats.php");

  /* what parameters are needed?
     - base url?
     - path to access log?
   */


$opts = new Zend_Console_Getopt(
  array(
	'noact|n'	=> "no action: don't insert anything into db",
	'url|u=s'	=> "relative url (without hostname) for site",
	'logfile|l=s'	=> "path to access log file",
  )
);

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $opts->getUsageMessage();
  exit;
}
$url = $opts->url;

if (!$url) {
  print "Error: base url param required\n";
  exit;
}
$logfile = $opts->logfile;
if (!$logfile) {
  print "Error: logfile param required\n";
  exit;
}

$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");

// sqlite db for statistics data
$config = new Zend_Config_Xml('../config/statistics.xml', $env_config->mode);
$db = Zend_Db::factory($config);
Zend_Registry::set('stat-db', $db);	


$start_time = date('Y-m-d H:i:s');
$count = 0;

$botDB = new BotObject();
$statDB = new StatObject();


$ip_reg = "(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})";
$date_reg = "\[(.*?)\]";
$pid_reg = "([a-zA-Z]+:[0-9a-z]+)";
// fez view url
//$view = preg_quote($url,'/')."\/?view\.php\?.*pid=([a-zA-Z]*:[0-9]+)";
// etd08 view url
$view = $url . "/?view/record/pid/$pid_reg";
print "DEBUG: view reg is $view\n";
// fez download url
//$download = preg_quote($url,'/')."\/?eserv\.php\?.*pid=([a-zA-Z]*:[0-9]+)&dsID=(\S*).*";
// etd08 download url
$download = $url . "/?file/view/pid/$pid_reg";
print "DEBUG: download reg is $download\n";
$bot_reg = "\/robots\.txt";
$ok = "HTTP\/1..\" (200|302)";	// FIXME: temporary? because of way files are handled right now
$agent_reg = '("[^"]*")';

$file = fopen($logfile, "r");
while (!feof($file)) {
  $buffer = fgets($file, 4096);
 if (preg_match("{^$ip_reg - - $date_reg \"GET $bot_reg $ok.* \"-\" $agent_reg}i",$buffer,$matches)) {
  // robot
   $ip = $matches[1];
   $agent  = $matches[3];
   if (knownRobot($ip, $agent)) continue;	// already a known robot
   else {	// save as a known robot	FIXME: better to save agent string and check against that?
     $data = array("ip" => $ip);
     if ($opts->noact) { print "Not inserting $ip into db as robot\n"; }
     else $botDB->insert($data);
   }
 } elseif (preg_match("{^$ip_reg - - $date_reg \"GET $view $ok .* \"-\" $agent_reg}i",$buffer,$matches)) {
 // abstract view
   continue;	// tmp
   $ip = $matches[1];
   $date = $matches[2];
   $pid = $matches[3];
   $agent = $matches[4];
   $type = "abstract";

   // if it's a robot, ignore it
   if (knownRobot($ip, $agent)) continue;

   // fixme: need to install geoip pecl
   //   $country = geoip_country_code_by_name($ip);
   
   $data = array("ip" => $ip, "date" => $date, "pid" => $pid, "type" => $type, "country" => $country);
   if ($opts->noact)  print "Not inserting $ip into db - abstract view of $pid\n"; 
   else $statDB->insert($data);
 } elseif (preg_match("{^$ip_reg - - $date_reg \"GET $download $ok .* \"-\" $agent_reg}i",$buffer,$matches)) {
   // file download
   $ip = $matches[1];
   $date = $matches[2];
   $pid = $matches[3];
   $agent = $matches[4];
   $type = "file";

   // if it's a robot, ignore it
   if (knownRobot($ip, $agent)) continue;

   //$country = geoip_country_code_by_name($ip);
   if ($opts->noact) print  "Not inserting $ip into db - file download\n"; 
   else $data = array("ip" => $ip, "date" => $date, "pid" => $pid, "type" => $type);
   $statDB->insert($data);
 }


} // end looping through file


			  /*
$stats = new StatObject();
$data = array(
	      "ip" => $ip,
	      "date" => $date,
	      "country" => $country,
	      "pid" => $pid,
	      "type" => $type);	// abstract/file
$stats->insert($data);
			  */

function knownRobot($ip, $agent) {

  // rule out by agent if possible, first
  if (preg_match("/(Googlebot|Yahoo! Slurp|msnbot|Lycos_Spider|MSRBOT|Ask Jeeves\/Teoma|Yahoo-MMCrawler|Gigabot)/", $agent)) {
    // don't need to store in db - recognizable as a bot from hostname
    return true;
  }

  
  // check if the ip is in the db as a known robot
  $hostname = gethostname($ip);
  // from fez:  preg_match("/^.*((googlebot.com)|(slurp)|(jeeves)|(yahoo)|(msn)).*/i"
  if (preg_match("/^.*((googlebot\.com)|(crawl\.yahoo\.net)|(crawler.*ask\.com)|(livebot.*search\.live\.com)).*/i", $hostname)) {
    // don't need to store in db - recognizable as a bot from hostname
    return true;
  }

  // last resort - check in the DB
  $bot = new BotObject();
  $robot = $bot->findByIp($ip);
  if ($robot)
    return true;
  else return false;
}

	function gethostname($ip) {
	  global $dns_cache;
	  if (isset($dns_cache[$ip])) return $dns_cache[$ip];
	  else {
	    $dns_cache[$ip] = gethostbyaddr($ip);
	    return $dns_cache[$ip];
	  }
	}

?>