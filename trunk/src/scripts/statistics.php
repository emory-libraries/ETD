#!/usr/bin/php -q
<?php

// set paths, load config files;
// set up connection objects for fedora, solr, ESD, and stats db
require_once("bootstrap.php");

require_once("models/stats.php");
require_once("models/EtdFactory.php");

  /* what parameters are needed?
     - base url?
     - path to access log?
   */

$opts = new Zend_Console_Getopt(
  array(
	'noact|n'	=> "no action: don't insert anything into db",
	'url|u=s'	=> "relative url (without hostname) for site (defaults to /)",
	'logfile|l=s'	=> "path to access log file",
	'verbose|v'	=> "verbose information",
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
  $url = "/";
  if ($opts->verbose) print "No parameter specified for url; assuming /\n";
}
$logfile = $opts->logfile;
if (!$logfile) {
  print "Error: logfile parameter required\n";
  exit;
}

$start_time = date('Y-m-d H:i:s');
$count = 0;

$dns_cache;
$etd_published;

$botDB = new BotObject();
$statDB = new StatObject();
$lastrunDB = new LastRunObject();
$lastrun = $lastrunDB->findLast();
if ($lastrun) {
  $lastruntime = strtotime($lastrun->start_time, 0);
  $new = false;		// no new content until we get past what was processed last time
  $lastline = $lastrun->lastentry;
} else {
  // no information about the last run - everything is new
  if ($opts->verbose) print "No information about last run - will include any hits found\n";
  $new = true;
  $lastline = "";
}


$ip_reg = "(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})";
$date_reg = "\[(.*?)\]";
$pid_reg = "([a-zA-Z]+(:|%3A)[0-9a-z]+)";	// colon may be urlencoded
$referrer = '("[^"]+")';
// view url for an etd record page 
$view = $url . "/?view/record/pid/$pid_reg";
// download url for an etd file
$download = $url . "/?file/view/pid/$pid_reg/[^\s]+";	// added filename onto url
$bot_reg = "\/robots\.txt";
$ok = "HTTP/1..\" (200|302)";	// FIXME: temporary? because of way files are handled right now
$agent_reg = '("[^"]*")';

$file = fopen($logfile, "r");
if (!$file) {
  print "Error: could not open $logfile\n";
  exit;
}


while (!feof($file)) {
  $buffer = fgets($file, 4096);
  if (!$new && preg_match("{^$ip_reg - - $date_reg .*i}i",$buffer,$matches)) {
    $date = $matches[2];

    if ($buffer == $lastrun->lastentry) {
      $new = true;	// everything after this line should be new
      continue;
    } elseif (strtotime($date, 0) < $lastruntime) {
	continue;	// included in the last run
    } elseif (strtotime($date, 0) > $lastruntime) {
      $new = true;      // we are now past the date/time of the last run -- any hits should be new
    }
  }
  
 if (preg_match("{^$ip_reg - - $date_reg \"GET $bot_reg $ok.* $referrer $agent_reg}i",$buffer,$matches)) {
  // robot
   $ip = $matches[1];
   $agent  = $matches[3];
   if (knownRobot($ip, $agent)) continue;	// already a known robot
   else {	// save as a known robot	FIXME: better to save agent string and check against that?
     $data = array("id" => null, "ip" => $ip);
     if ($opts->verbose) print "Found robot $ip\n"; 
     if (!$opts->noact)  $botDB->insert($data);
   }
 } elseif (preg_match("{^$ip_reg - - $date_reg \"GET $view $ok .* $referrer $agent_reg}i",$buffer,$matches)) {
 // abstract view
   $ip = $matches[1];
   $date = $matches[2];
   // FIXME: check this - date needs to be formatted so sqlite can handle it
   $date = date('Y-m-d H:i:s', strtotime($date, 0));

   $pid = $matches[3];
   $pid = urldecode($pid);	// if pid was urlencoded, convert back to normal pid format
   $agent = $matches[7];  
   $type = "abstract";

   // if it's a robot, ignore it
   if (knownRobot($ip, $agent)) continue;

   if ($pubdate = etdPublishedDate($pid)) {
     // if the view was before the record was published, don't include it
     if (strtotime($date, 0) < $pubdate)   continue;
   } else {
     continue;	   // if the record is not published, don't record access
   }

   $country = geoip_country_code_by_name($ip);
   
   $data = array("id" => null, "ip" => $ip, "date" => $date, "pid" => $pid, "type" => $type, "country" => $country);
   if (!$opts->noact)  $statDB->insert($data);
   if ($opts->verbose) print "Abstract view: $ip $date $country $pid\n";
   
   $count++;
   
 } elseif (preg_match("{^$ip_reg - - $date_reg \"GET $download $ok .* $referrer $agent_reg}i",$buffer,$matches)) {
   // file download
   $ip = $matches[1];
   $date = $matches[2];
   // FIXME: check this - date needs to be formatted so sqlite can handle it
   $date = date('Y-m-d H:i:s', strtotime($date, 0));

   $pid = $matches[3];
   $pid = urldecode($pid);
   $agent = $matches[7]; 
   $type = "file";

   // if it's a robot, ignore it
   if (knownRobot($ip, $agent)) continue;

   if ($pubdate = etdfilePublishedDate($pid)) {
     // if the view was before the record was published or before embargo ends, don't include it
     if (strtotime($date, 0) < $pubdate) continue;
   } else {
     continue;	   // if the record is not published, don't record access
   }


   $country = geoip_country_code_by_name($ip);
   if (!$opts->noact) {
     $data = array("id" => null, "ip" => $ip, "date" => $date, "pid" => $pid, "type" => $type, "country" => $country);
     $statDB->insert($data);
   }
   if ($opts->verbose) print "File download: $ip $date $country $pid\n";

   $count++;
 }

 if ($buffer != "") $lastline = $buffer;	// save last line for last run information
} // end looping through file



$end_time = date('Y-m-d H:i:s');

print "Found $count hits in " . (strtotime($end_time,0) - strtotime($start_time,0)) . " seconds.\n";

if (!$opts->noact) {
  $rundata = array("id" => null, "lastentry" => $lastline, "date" => date("Y-m-d"), "inserted" => $count,
		   "start_time" => $start_time, "end_time" => $end_time);
  $lastrunDB->insert($rundata);
}


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


function etdPublishedDate($pid) {
  global $etd_published;
  if (!isset($etd_published[$pid])) {	// if publication status/date not already found, retrieve and cache
    try {
      $etd = EtdFactory::etdByPid($pid);
    } catch (FedoraObjectNotFound $e) {
      return $etd_published[$pid] = false;	// object no longer exists - we don't care about it
    } catch (FedoraAccessDenied $e) {
      return $etd_published[$pid] = false;	// object is not publicly available -- not published
    } catch (FoxmlBadContentModel $e) {
      return $etd_published[$pid] = false;	// not actually an etd (file with bad url?)
    } catch (FoxmlException $e) {
      return $etd_published[$pid] = false;	// not publicly available = not published
    }
    if ($etd->status() == "published") $etd_published[$pid] = strtotime($etd->mods->originInfo->issued, 0);
    else $etd_published[$pid] = false;
  }
  return  $etd_published[$pid];
}

function etdfilePublishedDate($pid) {
  global $etd_published;
  if (!isset($etd_published[$pid])) {	// if publication status/date not already found, retrieve and cache
    try {
      $etdfile = new etd_file($pid);
    } catch (FedoraObjectNotFound $e) {
      return $etd_published[$pid] = false;	// object no longer exists - we don't care about it
    } catch (FedoraAccessDenied $e) {
      return $etd_published[$pid] = false;	// object is not publicly available -- embargoed
    } catch (FoxmlException $e) {
      return $etd_published[$pid] = false;	// object is not publicly available -- not published
    }
    if ($etdfile->etd->status() == "published") {
      if ($embargo = $etdfile->etd->mods->embargo_end)	// if there is an embargo, use embargo end as pub date
	$etd_published[$pid] = strtotime($embargo, 0);
      else			// otherwise, use main record pub date
	$etd_published[$pid] = strtotime($etdfile->etd->mods->originInfo->issued, 0);
    } else {
      $etd_published[$pid] = false;
    }
  }
  return  $etd_published[$pid];
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