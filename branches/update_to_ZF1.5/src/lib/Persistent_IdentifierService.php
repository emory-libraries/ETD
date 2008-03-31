<?php

/**
 * Persistent_IdentifierService class
 * 
 *  
 * 
 * @author    {author}
 * @copyright {copyright}
 * @package   {package}
 */
class Persistent_IdentifierService extends SoapClient {

  private static $classmap = array(
                                   );

  public function Persistent_IdentifierService($wsdl = "http://wilson:3005/persis_api/service.wsdl", $options = array()) {
    foreach(self::$classmap as $key => $value) {
      if(!isset($options['classmap'][$key])) {
        $options['classmap'][$key] = $value;
      }
    }
    parent::__construct($wsdl, $options);
  }

  /**
   *  
   *
   * @param string $username
   * @param string $password
   * @param string $uri
   * @param string $name
   * @param string $qualifier
   * @param int $domain_id
   * @param int $proxy_id
   * @param string $external_system
   * @param string $external_system_key
   * @return string
   */
  public function GenerateArk($username, $password, $uri, $name, $qualifier, $domain_id, $proxy_id, $external_system, $external_system_key) {
    return $this->__soapCall('GenerateArk', array($username, $password, $uri, $name, $qualifier, $domain_id, $proxy_id, $external_system, $external_system_key),       array(
            'uri' => 'urn:ActionWebService',
            'soapaction' => ''
           )
      );
  }

  /**
   *  
   *
   * @param string $username
   * @param string $password
   * @param string $uri
   * @param string $name
   * @param int $domain_id
   * @param int $proxy_id
   * @param string $external_system
   * @param string $external_system_key
   * @return string
   */
  public function GeneratePurl($username, $password, $uri, $name, $domain_id, $proxy_id, $external_system, $external_system_key) {
    return $this->__soapCall('GeneratePurl', array($username, $password, $uri, $name, $domain_id, $proxy_id, $external_system, $external_system_key),       array(
            'uri' => 'urn:ActionWebService',
            'soapaction' => ''
           )
      );
  }

}

?>
