<?php

require_once("Fedora_API_Service_types.php");

/**
 * Fedora_API_A_Service class
 *
 * @author    auto-generated by wsdl2php
 */
class Fedora_API_A_Service extends SoapClient {

  private static $classmap = array(
                                    'describeRepository' => 'describeRepository',
                                    'describeRepositoryResponse' => 'describeRepositoryResponse',
                                    'getObjectProfile' => 'getObjectProfile',
                                    'getObjectProfileResponse' => 'getObjectProfileResponse',
                                    'listMethods' => 'listMethods',
                                    'listMethodsResponse' => 'listMethodsResponse',
                                    'listDatastreams' => 'listDatastreams',
                                    'listDatastreamsResponse' => 'listDatastreamsResponse',
                                    'getDatastreamDissemination' => 'getDatastreamDissemination',
                                    'getDatastreamDisseminationResponse' => 'getDatastreamDisseminationResponse',
                                    'getDissemination' => 'getDissemination',
                                    'parameters' => 'parameters',
                                    'getDisseminationResponse' => 'getDisseminationResponse',
                                    'findObjects' => 'findObjects',
                                    'findObjectsResponse' => 'findObjectsResponse',
                                    'resumeFindObjects' => 'resumeFindObjects',
                                    'resumeFindObjectsResponse' => 'resumeFindObjectsResponse',
                                    'getObjectHistory' => 'getObjectHistory',
                                    'getObjectHistoryResponse' => 'getObjectHistoryResponse',
                                    'ingest' => 'ingest',
                                    'ingestResponse' => 'ingestResponse',
                                    'modifyObject' => 'modifyObject',
                                    'modifyObjectResponse' => 'modifyObjectResponse',
                                    'getObjectXML' => 'getObjectXML',
                                    'getObjectXMLResponse' => 'getObjectXMLResponse',
                                    'export' => 'export',
                                    'exportResponse' => 'exportResponse',
                                    'purgeObject' => 'purgeObject',
                                    'purgeObjectResponse' => 'purgeObjectResponse',
                                    'addDatastream' => 'addDatastream',
                                    'addDatastreamResponse' => 'addDatastreamResponse',
                                    'modifyDatastreamByReference' => 'modifyDatastreamByReference',
                                    'modifyDatastreamByReferenceResponse' => 'modifyDatastreamByReferenceResponse',
                                    'modifyDatastreamByValue' => 'modifyDatastreamByValue',
                                    'modifyDatastreamByValueResponse' => 'modifyDatastreamByValueResponse',
                                    'setDatastreamState' => 'setDatastreamState',
                                    'setDatastreamStateResponse' => 'setDatastreamStateResponse',
                                    'setDatastreamVersionable' => 'setDatastreamVersionable',
                                    'setDatastreamVersionableResponse' => 'setDatastreamVersionableResponse',
                                    'compareDatastreamChecksum' => 'compareDatastreamChecksum',
                                    'compareDatastreamChecksumResponse' => 'compareDatastreamChecksumResponse',
                                    'getDatastream' => 'getDatastream',
                                    'getDatastreamResponse' => 'getDatastreamResponse',
                                    'getDatastreams' => 'getDatastreams',
                                    'getDatastreamsResponse' => 'getDatastreamsResponse',
                                    'getDatastreamHistory' => 'getDatastreamHistory',
                                    'getDatastreamHistoryResponse' => 'getDatastreamHistoryResponse',
                                    'purgeDatastream' => 'purgeDatastream',
                                    'purgeDatastreamResponse' => 'purgeDatastreamResponse',
                                    'getNextPID' => 'getNextPID',
                                    'getNextPIDResponse' => 'getNextPIDResponse',
                                    'getRelationships' => 'getRelationships',
                                    'getRelationshipsResponse' => 'getRelationshipsResponse',
                                    'addRelationship' => 'addRelationship',
                                    'addRelationshipResponse' => 'addRelationshipResponse',
                                    'purgeRelationship' => 'purgeRelationship',
                                    'purgeRelationshipResponse' => 'purgeRelationshipResponse',
                                    'ComparisonOperator' => 'ComparisonOperator',
                                    'Condition' => 'Condition',
                                    'Datastream' => 'Datastream',
                                    'DatastreamBindingMap' => 'DatastreamBindingMap',
                                    'dsBindings' => 'dsBindings',
                                    'DatastreamBinding' => 'DatastreamBinding',
                                    'DatastreamControlGroup' => 'DatastreamControlGroup',
                                    'DatastreamDef' => 'DatastreamDef',
                                    'FieldSearchQuery' => 'FieldSearchQuery',
                                    'conditions' => 'conditions',
                                    'FieldSearchResult' => 'FieldSearchResult',
                                    'resultList' => 'resultList',
                                    'ListSession' => 'ListSession',
                                    'MethodParmDef' => 'MethodParmDef',
                                    'MIMETypedStream' => 'MIMETypedStream',
                                    'header' => 'header',
                                    'ObjectFields' => 'ObjectFields',
                                    'ObjectMethodsDef' => 'ObjectMethodsDef',
                                    'methodParmDefs' => 'methodParmDefs',
                                    'ObjectProfile' => 'ObjectProfile',
                                    'objModels' => 'objModels',
                                    'Property' => 'Property',
                                    'RelationshipTuple' => 'RelationshipTuple',
                                    'RepositoryInfo' => 'RepositoryInfo',
                                    'passByRef' => 'passByRef',
                                    'passByValue' => 'passByValue',
                                    'datastreamInputType' => 'datastreamInputType',
                                    'userInputType' => 'userInputType',
                                    'defaultInputType' => 'defaultInputType',
                                   );

  public function Fedora_API_A_Service($wsdl = "http://localhost:8080/fedora/wsdl?api=API-A", $options = array()) {
    foreach(self::$classmap as $key => $value) {
      if(!isset($options['classmap'][$key])) {
        $options['classmap'][$key] = $value;
      }
    }
    //    $options["features"] = SOAP_SINGLE_ELEMENT_ARRAYS;
    parent::__construct($wsdl, $options);
  }

  /**
   *
   *
   * @param describeRepository $parameters
   * @return describeRepositoryResponse
   */
  public function describeRepository(describeRepository $parameters) {
    return $this->__soapCall('describeRepository', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param getObjectProfile $parameters
   * @return getObjectProfileResponse
   */
  public function getObjectProfile(getObjectProfile $parameters) {
    return $this->__soapCall('getObjectProfile', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param listMethods $parameters
   * @return listMethodsResponse
   */
  public function listMethods(listMethods $parameters) {
    return $this->__soapCall('listMethods', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param listDatastreams $parameters
   * @return listDatastreamsResponse
   */
  public function listDatastreams(listDatastreams $parameters) {
    return $this->__soapCall('listDatastreams', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param getDatastreamDissemination $parameters
   * @return getDatastreamDisseminationResponse
   */
  public function getDatastreamDissemination(getDatastreamDissemination $parameters) {
    return $this->__soapCall('getDatastreamDissemination', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param getDissemination $parameters
   * @return getDisseminationResponse
   */
  public function getDissemination(getDissemination $parameters) {
    return $this->__soapCall('getDissemination', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param findObjects $parameters
   * @return findObjectsResponse
   */
  public function findObjects(findObjects $parameters) {
    $result = $this->__soapCall('findObjects', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
    return $result;
  }

  /**
   *
   *
   * @param resumeFindObjects $parameters
   * @return findObjectsResponse
   */
  public function resumeFindObjects(resumeFindObjects $parameters) {
    return $this->__soapCall('resumeFindObjects', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param getObjectHistory $parameters
   * @return getObjectHistoryResponse
   */
  public function getObjectHistory(getObjectHistory $parameters) {
    return $this->__soapCall('getObjectHistory', array($parameters),       array(
            'uri' => 'http://www.fedora.info/definitions/1/0/api/',
            'soapaction' => ''
           )
      );
  }

}
