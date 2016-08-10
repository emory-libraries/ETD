<?php

class describeRepository {
}

class describeRepositoryResponse {
  public $repositoryInfo; // RepositoryInfo
}

class getObjectProfile {
  public $pid; // string
  public $asOfDateTime; // string
}

class getObjectProfileResponse {
  public $objectProfile; // ObjectProfile
}

class listMethods {
  public $pid; // string
  public $asOfDateTime; // string
}

class listMethodsResponse {
  public $objectMethod; // ObjectMethodsDef
}

class listDatastreams {
  public $pid; // string
  public $asOfDateTime; // string
}

class listDatastreamsResponse {
  public $datastreamDef; // DatastreamDef
}

class getDatastreamDissemination {
  public $pid; // string
  public $dsID; // string
  public $asOfDateTime; // string
}

class getDatastreamDisseminationResponse {
  public $dissemination; // MIMETypedStream
}

class getDissemination {
  public $pid; // string
  public $serviceDefinitionPid; // string
  public $methodName; // string
  public $parameters; // parameters
  public $asOfDateTime; // string
}

class parameters {
  public $parameter; // Property
}

class getDisseminationResponse {
  public $dissemination; // MIMETypedStream
}

class findObjects {
  public $resultFields; // ArrayOfString
  public $maxResults; // nonNegativeInteger
  public $query; // FieldSearchQuery
}

class findObjectsResponse {
  public $result; // FieldSearchResult
}

class resumeFindObjects {
  public $sessionToken; // string
}

class resumeFindObjectsResponse {
  public $result; // FieldSearchResult
}

class getObjectHistory {
  public $pid; // string
}

class getObjectHistoryResponse {
  public $modifiedDate; // string
}

class ingest {
  public $objectXML; // base64Binary
  public $format; // string
  public $logMessage; // string
}

class ingestResponse {
  public $objectPID; // string
}

class modifyObject {
  public $pid; // string
  public $state; // string
  public $label; // string
  public $ownerId; // string
  public $logMessage; // string
}

class modifyObjectResponse {
  public $modifiedDate; // string
}

class getObjectXML {
  public $pid; // string
}

class getObjectXMLResponse {
  public $objectXML; // base64Binary
}

class export {
  public $pid; // string
  public $format; // string
  public $context; // string
}

class exportResponse {
  public $objectXML; // base64Binary
}

class purgeObject {
  public $pid; // string
  public $logMessage; // string
  public $force; // boolean
}

class purgeObjectResponse {
  public $purgedDate; // string
}

class addDatastream {
  public $pid; // string
  public $dsID; // string
  public $altIDs; // ArrayOfString
  public $dsLabel; // string
  public $versionable; // boolean
  public $MIMEType; // string
  public $formatURI; // string
  public $dsLocation; // string
  public $controlGroup; // string
  public $dsState; // string
  public $checksumType; // string
  public $checksum; // string
  public $logMessage; // string
}

class addDatastreamResponse {
  public $datastreamID; // string
}

class modifyDatastreamByReference {
  public $pid; // string
  public $dsID; // string
  public $altIDs; // ArrayOfString
  public $dsLabel; // string
  public $MIMEType; // string
  public $formatURI; // string
  public $dsLocation; // string
  public $checksumType; // string
  public $checksum; // string
  public $logMessage; // string
  public $force; // boolean
}

class modifyDatastreamByReferenceResponse {
  public $modifiedDate; // string
}

class modifyDatastreamByValue {
  public $pid; // string
  public $dsID; // string
  public $altIDs; // ArrayOfString
  public $dsLabel; // string
  public $MIMEType; // string
  public $formatURI; // string
  public $dsContent; // base64Binary
  public $checksumType; // string
  public $checksum; // string
  public $logMessage; // string
  public $force; // boolean
}

class modifyDatastreamByValueResponse {
  public $modifiedDate; // string
}

class setDatastreamState {
  public $pid; // string
  public $dsID; // string
  public $dsState; // string
  public $logMessage; // string
}

class setDatastreamStateResponse {
  public $modifiedDate; // string
}

class setDatastreamVersionable {
  public $pid; // string
  public $dsID; // string
  public $versionable; // boolean
  public $logMessage; // string
}

class setDatastreamVersionableResponse {
  public $modifiedDate; // string
}

class compareDatastreamChecksum {
  public $pid; // string
  public $dsID; // string
  public $versionDate; // string
}

class compareDatastreamChecksumResponse {
  public $checksum; // string
}

class getDatastream {
  public $pid; // string
  public $dsID; // string
  public $asOfDateTime; // string
}

class getDatastreamResponse {
  public $datastream; // Datastream
}

class getDatastreams {
  public $pid; // string
  public $asOfDateTime; // string
  public $dsState; // string
}

class getDatastreamsResponse {
  public $datastream; // Datastream
}

class getDatastreamHistory {
  public $pid; // string
  public $dsID; // string
}

class getDatastreamHistoryResponse {
  public $datastream; // Datastream
}

class purgeDatastream {
  public $pid; // string
  public $dsID; // string
  public $startDT; // string
  public $endDT; // string
  public $logMessage; // string
  public $force; // boolean
}

class purgeDatastreamResponse {
  public $purgedVersionDate; // string
}

class getNextPID {
  public $numPIDs; // nonNegativeInteger
  public $pidNamespace; // string
}

class getNextPIDResponse {
  public $pid; // string
}

class getRelationships {
  public $pid; // string
  public $relationship; // string
}

class getRelationshipsResponse {
  public $relationships; // RelationshipTuple
}

class addRelationship {
  public $pid; // string
  public $relationship; // string
  public $object; // string
  public $isLiteral; // boolean
  public $datatype; // string
}

class addRelationshipResponse {
  public $added; // boolean
}

class purgeRelationship {
  public $pid; // string
  public $relationship; // string
  public $object; // string
  public $isLiteral; // boolean
  public $datatype; // string
}

class purgeRelationshipResponse {
  public $purged; // boolean
}

class ComparisonOperator {
  const has = 'has';
  const eq = 'eq';
  const lt = 'lt';
  const le = 'le';
  const gt = 'gt';
  const ge = 'ge';
}

class Condition {
  public $property; // string
  public $operator; // ComparisonOperator
  public $value; // string
  // convenience function to set all three values at once
  public function __construct($prop, $op, $val) {
    $this->property = $prop;
    $this->operator = $op;
    $this->value =  $val;
  }
}

class Datastream {
  public $controlGroup; // DatastreamControlGroup
  public $ID; // string
  public $versionID; // string
  public $altIDs; // ArrayOfString
  public $label; // string
  public $versionable; // boolean
  public $MIMEType; // string
  public $formatURI; // string
  public $createDate; // string
  public $size; // long
  public $state; // string
  public $location; // string
  public $checksumType; // string
  public $checksum; // string
}

class DatastreamBindingMap {
  public $dsBindMapID; // string
  public $dsBindMechanismPID; // string
  public $dsBindMapLabel; // string
  public $state; // string
  public $dsBindings; // dsBindings
}

class dsBindings {
  public $dsBinding; // DatastreamBinding
}

class DatastreamBinding {
  public $bindKeyName; // string
  public $bindLabel; // string
  public $datastreamID; // string
  public $seqNo; // string
}

class DatastreamControlGroup {
  const E = 'E';
  const M = 'M';
  const X = 'X';
  const R = 'R';
}

class DatastreamDef {
  public $ID; // string
  public $label; // string
  public $MIMEType; // string
}

class FieldSearchQuery {
  public $conditions; // conditions
  public $terms; // string
}

/* NOTE: current version of php has a bug with generating xsd:choice for request object;
  in this case, it always includes the first element and ignores the second;
  Using custom FieldSearchQuery object that only has term and not conditions as workaround. */
class FieldSearchQuery_TermOnly {
  public $terms; // string
}

class conditions {
  public $condition; // Condition
}

class FieldSearchResult {
  public $listSession; // ListSession
  public $resultList; // resultList
}

class resultList {
  public $objectFields; // ObjectFields
}

class ListSession {
  public $token; // string
  public $cursor; // nonNegativeInteger
  public $completeListSize; // nonNegativeInteger
  public $expirationDate; // string
}

class MethodParmDef {
  public $parmName; // string
  public $parmType; // string
  public $parmDefaultValue; // string
  public $parmDomainValues; // ArrayOfString
  public $parmRequired; // boolean
  public $parmLabel; // string
  public $parmPassBy; // string
  public $PASS_BY_REF; // passByRef
  public $PASS_BY_VALUE; // passByValue
  public $DATASTREAM_INPUT; // datastreamInputType
  public $USER_INPUT; // userInputType
  public $DEFAULT_INPUT; // defaultInputType
}

class MIMETypedStream {
  public $MIMEType; // string
  public $stream; // base64Binary
  public $header; // header
}

class header {
  public $property; // Property
}

class ObjectFields {
  public $pid; // string
  public $label; // string
  public $state; // string
  public $ownerId; // string
  public $cDate; // string
  public $mDate; // string
  public $dcmDate; // string
  public $title; // string
  public $creator; // string
  public $subject; // string
  public $description; // string
  public $publisher; // string
  public $contributor; // string
  public $date; // string
  public $type; // string
  public $format; // string
  public $identifier; // string
  public $source; // string
  public $language; // string
  public $relation; // string
  public $coverage; // string
  public $rights; // string
}

class ObjectMethodsDef {
  public $PID; // string
  public $serviceDefinitionPID; // string
  public $methodName; // string
  public $methodParmDefs; // methodParmDefs
  public $asOfDate; // string
}

class methodParmDefs {
  public $methodParmDef; // MethodParmDef
}

class ObjectProfile {
  public $pid; // string
  public $objLabel; // string
  public $objModels; // objModels
  public $objCreateDate; // string
  public $objLastModDate; // string
  public $objDissIndexViewURL; // string
  public $objItemIndexViewURL; // string
}

class objModels {
  public $model; // string
}

class Property {
  public $name; // string
  public $value; // string
}

class RelationshipTuple {
  public $subject; // string
  public $predicate; // string
  public $object; // string
  public $isLiteral; // boolean
  public $datatype; // string
}

class RepositoryInfo {
  public $repositoryName; // string
  public $repositoryVersion; // string
  public $repositoryBaseURL; // string
  public $repositoryPIDNamespace; // string
  public $defaultExportFormat; // string
  public $OAINamespace; // string
  public $adminEmailList; // ArrayOfString
  public $samplePID; // string
  public $sampleOAIIdentifier; // string
  public $sampleSearchURL; // string
  public $sampleAccessURL; // string
  public $sampleOAIURL; // string
  public $retainPIDs; // ArrayOfString
}

class passByRef {
  const URL_REF = 'URL_REF';
}

class passByValue {
  const VALUE = 'VALUE';
}

class datastreamInputType {
  const fedora_datastreamInputType = 'fedora:datastreamInputType';
}

class userInputType {
  const fedora_userInputType = 'fedora:userInputType';
}

class defaultInputType {
  const fedora_defaultInputType = 'fedora:defaultInputType';
}
