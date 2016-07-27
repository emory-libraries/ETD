<?php

/**
 * Fedora Generic Search Service OperationsService
 * (auto-generated from GSearch wsdl file via wsdl2php)
 *
 *
 */
class FedoraGsearch_OperationsService extends SoapClient {

  private static $classmap = array(
                                   );

  public function __construct($wsdl = "http://localhost:8080/fedoragsearch/services/FgsOperations?wsdl", $options = array()) {
    $options = array();
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
   * @param string $action
   * @param string $value
   * @param string $repositoryName
   * @param string $indexName
   * @param string $indexDocXslt
   * @param string $resultPageXslt
   * @return string
   */
  public function updateIndex($action, $value, $repositoryName, $indexName, $indexDocXslt, $resultPageXslt) {
    return $this->__soapCall('updateIndex', array($action, $value, $repositoryName, $indexName, $indexDocXslt, $resultPageXslt),       array(
            'uri' => 'http://server.fedoragsearch.defxws.dk',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param string $query
   * @param int $hitPageStart
   * @param int $hitPageSize
   * @param int $snippetsMax
   * @param int $fieldMaxLength
   * @param string $indexName
   * @param string $sortFields
   * @param string $resultPageXslt
   * @return string
   */
  public function gfindObjects($query, $hitPageStart, $hitPageSize, $snippetsMax, $fieldMaxLength, $indexName, $sortFields, $resultPageXslt) {
    return $this->__soapCall('gfindObjects', array($query, $hitPageStart, $hitPageSize, $snippetsMax, $fieldMaxLength, $indexName, $sortFields, $resultPageXslt),       array(
            'uri' => 'http://server.fedoragsearch.defxws.dk',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param string $startTerm
   * @param int $termPageSize
   * @param string $fieldName
   * @param string $indexName
   * @param string $resultPageXslt
   * @return string
   */
  public function browseIndex($startTerm, $termPageSize, $fieldName, $indexName, $resultPageXslt) {
    return $this->__soapCall('browseIndex', array($startTerm, $termPageSize, $fieldName, $indexName, $resultPageXslt),       array(
            'uri' => 'http://server.fedoragsearch.defxws.dk',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param string $repositoryName
   * @param string $resultPageXslt
   * @return string
   */
  public function getRepositoryInfo($repositoryName, $resultPageXslt) {
    return $this->__soapCall('getRepositoryInfo', array($repositoryName, $resultPageXslt),       array(
            'uri' => 'http://server.fedoragsearch.defxws.dk',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param string $indexName
   * @param string $resultPageXslt
   * @return string
   */
  public function getIndexInfo($indexName, $resultPageXslt) {
    return $this->__soapCall('getIndexInfo', array($indexName, $resultPageXslt),       array(
            'uri' => 'http://server.fedoragsearch.defxws.dk',
            'soapaction' => ''
           )
      );
  }

}
