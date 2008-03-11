<?php

  // minimal solr query ... abstract better to search by field, do
  // facets, limit result set, etc.

class solr {

  private $baseurl;
  private $facet_fields;
  private $facet_limit;
  
  function __construct($hostname, $port, $path = "solr/select") {
    $this->baseurl = "http://$hostname:$port/$path";

    $this->facet_fields = array();
    $this->facet_limit = 5;	// is this reasonable?
  }

  function query($queryString, $start = null, $max = null, $facet_limit = null) {	// solr defaults to 1, 10
    // allow a facet count override for this query only 
    if (is_null($facet_limit)) {
      $facet_limit = $this->facet_limit;
    }
    //    print "DEBUG: query=$queryString";
    $url = $this->baseurl . "?q=$queryString";
    if ($start == 1) $url .= "&start=0";		// if you start with 1, you miss the first record
    elseif ($start !== null) $url .= "&start=$start";
    if ($max !== null) 	$url .= "&rows=$max";
    if(count($this->facet_fields)) {
      $url .= "&facet=true&facet.mincount=1&facet.limit=" . $facet_limit;	// sane defaults?
      foreach ($this->facet_fields as $field) {
	$url .= "&facet.field=$field";
      }
    }
    $url .= "&wt=phps&fq=status:published";
    //    print "DEBUG: <a href='$url'>solr query</a> (query = $queryString)<br/>\n";
    //    $val = file_get_contents($url);
    $val = file_post_contents($url);			// switched to post for long queries
    //print "DEBUG: solr response: $val";
    //    print "<pre>"; print_r(unserialize($val)); print "</pre>";
    return unserialize($val);
  }

  function addFacets(array $field) {
    foreach ($field as $f) 
      array_push($this->facet_fields, $f);
  }

  function clearFacets() {
    unset($this->facet_fields);
    $this->facet_fields = array();
  }

  // find all terms for a single field
  function browse($field) {
    $this->clearFacets();
    $this->addFacets(array($field));
    $this->facet_limit = -1;	// no limit
    // probably need to add sort option...
    return $this->query("*:*", 0, 0);	// find facets on all records, return none
  }
  
}

function solrQuery($query) {
  $host = "mothra.library.emory.edu";
  $port = "8983";
  $baseurl = "solr/select";
  //  return unserialize(file_get_contents("http://$host:$port/$baseurl?q=$query&facet=true&facet.mincount=1&facet.field=advisor_lastnamefirst&facet.field=year&facet.field=subject_facet&facet.field=author_lastnamefirst&facet.field=committee_lastnamefirst&facet.limit=5&wt=phps"));

  return unserialize(file_get_contents("http://$host:$port/$baseurl?q=*:*&facet=true&facet.mincount=1&facet.field=committee_lastnamefirst&rows=0&wt=phps"));
}


// fixme: could do date faceting... might be cool


// copied from php.net comments
function file_post_contents($url,$headers=false) {
    $url = parse_url($url);

    if (!isset($url['port'])) {
      if ($url['scheme'] == 'http') { $url['port']=80; }
      elseif ($url['scheme'] == 'https') { $url['port']=443; }
    }
    $url['query']=isset($url['query'])?$url['query']:'';

    $url['protocol']=$url['scheme'].'://';
    $eol="\r\n";

    $headers =  "POST ".$url['protocol'].$url['host'].$url['path']." HTTP/1.0".$eol.
                "Host: ".$url['host'].$eol.
                "Referer: ".$url['protocol'].$url['host'].$url['path'].$eol.
                "Content-Type: application/x-www-form-urlencoded".$eol.
                "Content-Length: ".strlen($url['query']).$eol.
                $eol.$url['query'];
    $fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 30);
    if($fp) {
      fputs($fp, $headers);
      $result = '';
      while(!feof($fp)) { $result .= fgets($fp, 128); }
      fclose($fp);
      if (!$headers) {
        //removes headers
	$pattern="/^.*\r\n\r\n/s";
        $result=preg_replace($pattern,'',$result);
      }
      $pattern="/^.*\r\n\r\n/s";
      $result=preg_replace($pattern,'',$result);

      return $result;
    }
}

?>