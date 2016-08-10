<?php


/**
 * REST interface to Emory Persistent ID server to generate new arks
 *
 * @category Etd
 * @package Etd_Service
 * @subpackage Etd_Service_Persis
 */

class PersisServiceException extends Exception {}
class PersisServiceUnavailable extends PersisServiceException {}
class PersisServiceUnauthorized extends PersisServiceException {}

/**
 * @package Etd_Service
 * @subpackage Etd_Service_Persis
 */
class Etd_Service_Persis {
    /**
     *
     * @var string username for accessing persis service
     */
    private $username;
    /**
     *
     * @var string password for accessing persis service
     */
    private $password;
    /**
     *
     * @var int numeric domain id in which new arks will be generated
     */
    private $domain_id;
    /**
     *
     * @var Persistent_IdentifierService
     */
    private $service;

    /**
     * constants for accessing the portions of a parsed ark, as returned by parseArk function
     */
    const NMA = 0;
    const NAAN = 1;
    const NOID = 2;
    const QUALIFIER = 3;

   /**
    * @var string $noid_charset characters allowed in NOID portion of ark (using template .zek)
    */
    private $noid_charset = "0123456789bcdfghjkmnpqrstvwxz";

    /**
     *
     * @var string $ark_regexp regular expression to use for matching arks
     */
    private $ark_regexp;

  /**
   * Initialize a connection to the Persis service
   *
   * $config is an array of key/value pairs or an instance of Zend_Config
   * containing configuration options.  These options are common to most adapters:
   *
   * url            => (string) base url to the persistent id server
   * username       => (string) user account to authenticate with
   * password       => (string) password for authentication
   * domain_id      => (int)    numeric domain id within which new arks should be created

   * @param  array|Zend_Config $config Array or instance of Zend_Config with configuration
   * errors on Parameters must be in an array or a Zend_Config object
   * @throws PersisServiceException
   * @throws PersisServiceUnavailable
   */
    public function __construct($config) {

        // config should either be an array or Zend_Config; if Zend_Config, convert to array
        if (!is_array($config)) {
            if ($config instanceof Zend_Config) {
                $config = $config->toArray();
            }
            else {
                   throw new PersisServiceException('Parameters must be in an array or a Zend_Config object');
            }
        }

        $this->_checkRequiredOptions($config);

        $this->username = $config["username"];
        $this->password = $config["password"];
        $this->domain_id = $config["domain_id"];
        $this->pidman = $config["url"];

        // For creating DOIs
        $this->ezid_url = $config["ezid_url"];
        $this->ezid_password = $config["ezid_password"];
        $this->ezid_username = $config["ezid_username"];


        $this->ark_regexp = "|^(https?://[a-z.]+)/ark:/([0-9]+)/([" .
                                $this->noid_charset . "]+)/?(.*)?$|i";
    }


  /**
   * check that required options are included in configuration
   * @param array $config
   * @errors missing required
   * @throws PersisServiceException
   */
    protected function _checkRequiredOptions(array $config) {
        foreach (array("url", "username", "password", "domain_id") as $required) {
            if (!array_key_exists($required, $config))
            throw new PersisServiceException("Configuration does not include value for $required");

        }
    }

  /**
   * Generate and return a new ark
   *
   * @param string $url url that the new ark should resolve to
   * @param string $title title or description to be stored in the persistent id server
   * @param qualifier $qualifier ark qualifier, if any; optional, defaults to none
   * @param int $proxy_id id for proxy setting; optional, defaults to none
   * @param string $external_system external system name; optional, defaults to none
   * @param string $external_key key/id within specified external system; optional, defaults to none
   * @return string ark
   * @throws PersisServiceException
   * @throws PersisServiceUnauthorized
   */
    public function generateArk($url, $title, $qualifier = null,
                $proxy_id = null, $external_system = null, $external_key = null) {

        // Use the PidMan REST client to generateArk
        $logger = Zend_Registry::get('logger');
        $payload = array('domain' => $this->pidman . 'domains/' . $this->domain_id . '/', 'name' => $title, 'target_uri' => $url );
        $ch = curl_init($this->pidman . '/ark/');

        curl_setopt($ch,CURLOPT_FAILONERROR,true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $ark = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201){
                $error = "Bad response from pidman. Response was: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $logger->err($error);
                throw new PersisServiceUnauthorized($error);
        }
        if (curl_error($ch)){
            $logger->err(curl_error($ch));
            throw new PersisServiceUnauthorized("Failed to generate ARK: " .  curl_error($ch));
            curl_close($ch);
            return null;
        } else {
            curl_close($ch);
            return $ark;
        }
    }

  /**
   * Generate and return a new purl
   *
   * @param string $url url that the new purl should resolve to
   * @param string $title title or description to be stored in the persistent id server
   * @param int $proxy_id id for proxy setting; optional, defaults to none
   * @param string $external_system external system name; optional, defaults to none
   * @param string $external_key key/id within specified external system; optional, defaults to none
   * @return string purl
   * @throws PersisServiceException
   * @throws PersisServiceUnauthorized
   */
    public function generatePurl($url, $title, $proxy_id = null,
                 $external_system = null, $external_key = null) {
      $request = new GeneratePurl();
      $request->username = $this->username;
      $request->password = $this->password;
      $request->uri = $url;
      $request->name = $title;
      $request->proxy_id = $proxy_id;
      $request->domain_id = $this->domain_id;
      $request->external_system = $external_system;
      $request->external_system_key = $external_key;

      try {
    $purl = $this->service->GeneratePurl($request);
      } catch (SoapFault $e) {
    // throw an exception with an appropriate error message
    switch($e->faultstring) {
    case "request canceled: not authorized":
        throw new PersisServiceUnauthorized($e->getMessage());
    default:
        throw new PersisServiceException("Unknown error:" . $e->faultstring);
    }
    return null;
      }
      return $purl->return;
    }

    /**
     * Generate and return a new ark
     *
     * @param etd object
     * @return string DOI
     * @throws PersisServiceException
     * @throws PersisServiceUnauthorized
     */
      public function generateDoi($etd) {

          // Taken from
          // http://ezid.cdlib.org/doc/apidoc.html#php-examples
          $logger = Zend_Registry::get('logger');

          // EasyID wants a multiline string.
          $payload = "_target: " . $etd->mods->identifier . "\n";
          $payload .= "datacite.creator: " . $etd->mods->author . "\n";
          $payload .= "datacite.publicationyear:" . $etd->mods->date . "\n";
          $payload .= "datacite.title: " . $etd->mods->title . "\n";
          $payload .= "datacite.publisher: Emory University";

          $ch = curl_init();

          curl_setopt($ch, CURLOPT_URL, $this->ezid_url . $this->etd->pid);
          curl_setopt($ch, CURLOPT_USERPWD, $this->ezid_username . ":" . $this->ezid_password);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
          curl_setopt($ch, CURLOPT_HTTPHEADER,
            array('Content-Type: text/plain; charset=UTF-8',
                  'Content-Length: ' . strlen($payload)));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          $doi = curl_exec($ch);


          if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201){
                  $error = "Bad response from pidman. Response was: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
                  $logger->err($error);
  		            throw new PersisServiceUnauthorized($error);
          }
          if (curl_error($ch)){
              $logger->err(curl_error($ch));
              throw new PersisServiceUnauthorized("Failed to generate ARK: " .  curl_error($ch));
              curl_close($ch);
              return null;
          } else {
              curl_close($ch);
              return $doi;
          }
      }

  /**
   * Strip out the unique id and return a fedora-style pid
   *
   * @deprecated use parseArk function instead
   *
   * @param string $ark ark generated by persistent id server
   * @param string $namespace namespace for the fedora pid (default: emory)
   * @return string pid
   */
    public function pidfromArk($ark, $namespace = "emory") {
        $parsed = $this->parseArk($ark);
        return $namespace . ":" . $parsed[Etd_Service_Persis::NOID];
    }

  /**
   * parse an ark into its component parts; warn if it cannot be parsed
   *
   * @param string $ark full format of ark, as generated by persistent id server
   * @return null|array of nma, naan, noid, and qualifier (if any)
   */
    public function parseArk($ark) {
        if (preg_match($this->ark_regexp, $ark, $matches)) {
            $nma = $matches[1];       // name mapping authority
            $naan = $matches[2];      // name assigning authority number
            $noid = $matches[3];      // noid (unique id)
            $result = array($nma, $naan, $noid);

            // if any qualifier is present, include it
            if (isset($matches[4]) &&  $matches[4] != '') {
                $qualifier = $matches[4];
                $result[] = $qualifier;
            }
            return $result;
        } else {
            trigger_error("Could not parse '$ark'; are you sure it is an ark?", E_USER_WARNING);
            // unable to parse
            return null;
        }
    }

    /**
     *  Check if string matches pattern for an ark (full format)
     * @param string $ark
     * @return boolean
     */
    public function isArk($ark) {
        return preg_match($this->ark_regexp, $ark);
    }

    /**
     * Basic sanity-check that a noid looks reasonable (not necessarily valid)
     * -- full validation not easy or really needed here.
     *
     * @param string $noid
     * @return boolean
     */
    public function isNoid($noid) {
        return preg_match("/^[" . $this->noid_charset . "]+$/", $noid);
    }

  /**
   * Add a new target/qualifier to an existing ark
   *
   * @param string $ark existing ark (full format or noid only)
   * @param string $qualifier qualifier string to be appended to ark for new target
   * @param string $uri url to which the new target should resolve
   * @param string $proxy_id
   */
    public function addArkTarget($ark, $qualifier, $uri, $proxy_id = null) {
      if ($this->isArk($ark)) {
    // full ark has been given; strip out noid from the end
    $parsed = $this->parseArk($ark);
    $noid = $parsed[Emory_Service_Persis::NOID];
      } elseif ($this->isNoid($ark)) {
    // if param was not full ark, check that only the noid portion has been passed in
    $noid = $ark;
      } else {
	throw new PersisServiceException("'$ark' is not a valid ark or noid");
      }

      $rqst = new AddArkTarget();
      $rqst->username = $this->username;
      $rqst->password = $this->password;
      $rqst->noid = $noid;
      $rqst->qualifier = $qualifier;
      $rqst->uri = $uri;
      $rqst->proxy_id = $proxy_id;

      try {
    $result = $this->service->AddArkTarget($rqst);
      } catch (SoapFault $e) {

    switch($e->faultstring) {
    case "request canceled: not authorized":
    	throw new PersisServiceUnauthorized($e->getMessage());
    // any other cases?
    default:
    	throw new PersisServiceException("Unknown error:" . $e->faultstring);
    }
    return null;
      }
      return $result->return;
    }
}
