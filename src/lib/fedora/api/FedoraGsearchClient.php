<?php

require_once('FedoraGsearch_OperationsService.php');

/**
 * FedoraGsearchClient - wrapper around SOAP client for
 * acessing the Fedora Generic Search Service
 */

class FedoraGsearchClient {

    /**
     * gsearch soap client instance
     * @var FedoraGsearch_OperationsService
     */
    protected $client;
    /**
     * default index name to use for gsearch operations
     * @var string
     */
    public $index;
    /**
     * default repository name to use for gsearch operations
     * @var string
     */
    public $repository;

    /**
     * Initialize GSearch soap client
     * @param string $base_url gsearch base url, e.g.
     *          http://localhost:8080/fedoragsearch/
     * @param string $index gsearch index name
     * @param string $repository gsearch repository name
     */
    public function __construct($base_url, $index, $repository) {
        $wsdl = $base_url . '/services/FgsOperations?wsdl';
        $this->client = new FedoraGsearch_OperationsService($wsdl);
        $this->index = $index;
        $this->repository = $repository;
    }

    /**
     * Index a single object by pid. Returns true if the result indicates
     * that a single index document was either updated or inserted.
     *
     * @param string $pid
     * @return boolean
     */
    public function index_object($pid) {
        $response = $this->client->updateIndex('fromPid', $pid,
                                $this->repository, $this->index, '', '');
        $sxml = simplexml_load_string($response);
        $updated = $sxml->updateIndex;
        // if a single document was updated or inserted, consider the object index successful
        if ($updated['updateTotal'] == 1 or $updated['insertTotal'] == 1) {
            return true;
        }
        // result also includes deleteTotal, indexName, and docCount 
        return false;
    }
}