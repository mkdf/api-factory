<?php

namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Repository\PolicyRepositoryInterface;
use APIF\Core\Repository\SchemaRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use APIF\Core\Service\SchemaValidatorInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use APIF\Core\Service\JsonModel;

//This is just used for creating Mongo compatible IDs in activity log packets
use MongoDB;

class ObjectController extends AbstractRestfulController
{
    private $_config;
    private $_repository;
    private $_activityLog;
    private $_schemaValidator;
    private $_schemaRepository;
    private $_policyRepository;

    public function __construct(APIFCoreRepositoryInterface $repository, ActivityLogManagerInterface $activityLog, SchemaValidatorInterface $schemaValidator, SchemaRepositoryInterface $schemaRepository, PolicyRepositoryInterface  $policyRepository, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_activityLog = $activityLog;
        $this->_schemaValidator = $schemaValidator;
        $this->_schemaRepository = $schemaRepository;
        $this->_policyRepository = $policyRepository;
    }

    private function _getAuth() {
        //Check AUTH has been passed
        $request_method = $_SERVER["REQUEST_METHOD"];
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Dataset key must be provided as HTTP Basic Auth username';
            exit;
        } else {
            $auth = [
                'user'  => $_SERVER['PHP_AUTH_USER'],
                'pwd'   => $_SERVER['PHP_AUTH_PW']
            ];
            return $auth;
        }
    }

    /*
     *
     *
      "@type": ["al:Create", "al:ActivityLogEntry"],
        "al:datasetId": "datahub__activity_log",
        "al:documentId": "datahub__activity_log",
    "al:summary": "short description",

        "al:request": {
      "@type": "al:HTTPRequest",
      "al:agent": {
        "@type": "al:Agent",
        "al:key": "datahub__activity_log_key123"
      },
      "al:endpoint": "http://apif-beta.local/object/datahub__activity_log",
      "al:httpRequestMethod": "al:POST",
      "al:parameters": [],
      "al:payload": "{}"
    },

     *
     */


    private function _assembleLogData ($datasetId, $key, $action, $description, $docID = null) {
        $timestamp = time();
        $OID = new MongoDB\BSON\ObjectId();
        $idString = (string)$OID;

        $summary = $action."[".$this->getRequest()->getMethod()."] - ".$this->getRequest()->getUriString()." - Dataset:".$datasetId." - Key:".$key." - ".$description;

        $data = [
            "_id" => $idString,
            "@id" => $idString,
            "@context" => "https://mkdf.github.io/context",
            "@type" => [
                "al:".$action,
                "al:ActivityLogEntry"
            ],
            //"al:datasetId" => $datasetId,
            //"al:documentId" => "docID",
            "al:summary" => $summary,
            "al:request" => [
                "@type" => "al:HTTPRequest",
                "al:agent" => [
                    "@type" => "al:Agent",
                    "al:key" => $key
                ],
                "al:endpoint" => $this->getRequest()->getUriString(),
                "al:httpRequestMethod" => "al:".$this->getRequest()->getMethod(),
                "al:parameters" => $this->params()->fromQuery(),
                "al:payload" => $this->getRequest()->getContent()
            ]
        ];
        if (!is_null($docID)) {
            $data["al:documentId"] = $docID;
        }
        if (!is_null($datasetId)) {
            $data["al:datasetId"] = $datasetId;
        }

        //Add timestamp data
        $data['_timestamp'] = $timestamp;
        $data['_timestamp_year'] = (int)date("Y",$timestamp);
        $data['_timestamp_month'] = (int)date("m",$timestamp);
        $data['_timestamp_day'] = (int)date("d",$timestamp);
        $data['_timestamp_hour'] = (int)date("H",$timestamp);
        $data['_timestamp_minute'] = (int)date("i",$timestamp);
        $data['_timestamp_second'] = (int)date("s",$timestamp);

        return $data;
    }

    private function _annotateObject($input, $uuid){
        /*
         * add extra metadata and return new annotated object
         * Also add an _id field if one hasn't been submitted
         */
        //$object = json_decode($input, true);
        $object = $input;

        $timestamp = time();
        //echo date("d/m/Y H:i:s",$timestamp);

        //if no _id supplied, generate a string version of a Mongo ObjectID
        if (!array_key_exists('_id',$object)){
            $OID = new MongoDB\BSON\ObjectId();
            $idString = (string)$OID;
            $object['_id'] = $idString;
        }
        //convert _id to string if necessary
        $object['_id'] = (string)$object['_id'];

        $object['_datasetid'] = $uuid;
        $object['_timestamp'] = $timestamp;

        #explode timestamp and add additional attributes for year, month, dat, hour, second.
        $object['_timestamp_year'] = (int)date("Y",$timestamp);
        $object['_timestamp_month'] = (int)date("m",$timestamp);
        $object['_timestamp_day'] = (int)date("d",$timestamp);
        $object['_timestamp_hour'] = (int)date("H",$timestamp);
        $object['_timestamp_minute'] = (int)date("i",$timestamp);
        $object['_timestamp_second'] = (int)date("s",$timestamp);

        return $object;
    }

    private function _handleException($ex) {
        if (is_a($ex, MongoDB\Driver\Exception\AuthenticationException::class) ){
            $this->getResponse()->setStatusCode(403);
        }elseif(is_a($ex->getPrevious(), MongoDB\Driver\Exception\AuthenticationException::class)){
            $this->getResponse()->setStatusCode(403);
        }elseif(is_a($ex, \Throwable::class)){
            $this->getResponse()->setStatusCode(500);
        }else{
            // This will never happen
            $this->getResponse()->setStatusCode(500);
        }
    }


    public function options() {

        $this->getResponse()->getHeaders()->addHeaders([
            //'Access-Control-Allow-Origin' => '*',
            //'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Max-Age' => '86400',

        ]);

        return new JsonModel([]);
    }


    /*
     * GET - Handling a GET request
     * brings back all docs from a dataset (subject to limit), or a query
     * if query param is provided
     */
    public function get($id) {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];

        $docID = $this->params()->fromRoute('doc-id', null);
        if ($docID) {
            try {
                $data = $this->_repository->getSingleDoc($id, $key, $pwd, $docID);
                return new JsonModel($data);
            }catch (\Throwable $ex) {
                $this->_handleException($ex);
                return new JsonModel(['error' => 'Failed to retrieve document - ' . $ex->getMessage()]);
            }
        }

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', $this->_config['mongodb']['queryLimit']);
        $sortParam = $this->params()->fromQuery('sort', null);
        $pageParam = $this->params()->fromQuery('page', 1);
        $pageSizeParam = $this->params()->fromQuery('pagesize', null);

        //Sorting
        $sortTerms = [];
        if (!is_null($sortParam)){
            $exploded = explode(",",$sortParam);
            foreach ($exploded as $term){
                if (substr($term,0,1) == "-") {
                    $sortTerms[$term] = -1;
                }
                else {
                    $sortTerms[$term] = 1;
                }
            }
        }

        //Assign params to query options
        if ($queryParam == ""){
            $query = [];
        }
        else{
            $query = json_decode($queryParam);
            if ($query == null) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, malformed JSON query']);
            }
        }
        try {
            $data = $this->_repository->findDocs($id, $key, $pwd, $query,(int)$limitParam,$sortTerms);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve documents - ' . $ex->getMessage()]);
        }

        // Apply pagination here
        if (!is_null($pageSizeParam)){
            $pageStart = ($pageParam - 1) * $pageSizeParam;
            $data = array_slice($data,$pageStart,$pageSizeParam);
        }

        //Activity Log
        $datasetUUID = $id;
        $action = "RetrieveMany";
        $summary = "Retrieve multiple documents";
        $logData = $this->_assembleLogData($datasetUUID, $key, $action, $summary);
        $this->_activityLog->logActivity($logData);

        $licenses = $this->_policyRepository->getLicenses($datasetUUID, $key);
        $this->getResponse()->getHeaders()->addHeaders([
            //'Access-Control-Allow-Origin' => '*',
            'Licenses' => 'license1, license2',
        ]);

        return new JsonModel($data);
    }

    /*
    public function getList() {
        //not used
        return new JsonModel([]);
    }
    */

    /*
     * CREATE - Handling a POST request
     */
    public function create($data) {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $datasetUUID = $this->params()->fromRoute('id', null);
        if (is_null($datasetUUID)) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing dataset id']);
        }

        //$object = json_decode($data);
        $object = $data;
        if ($object == null) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, malformed JSON']);

        }
        $annotated = $this->_annotateObject($object, $datasetUUID);

        //CHECK IF SCHEMA VALIDATION ENABLED FOR THIS DATASET...
        $validationSchemas = $this->_schemaRepository->getValidationSchemas($datasetUUID);
        if (!is_null($validationSchemas)) {
            try {
                if (!$this->_schemaValidator->validate($object, $validationSchemas)) {
                    //This shouldn't happen as failed validation throws an exception which is caught further below
                    $this->getResponse()->setStatusCode(400);
                    return new JsonModel(['error' => 'JSON schema validation failed']);
                }
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => "JSON Schema validation error", 'details' => json_decode($ex->getMessage())]);
            }

        }


        try {
            $response = $this->_repository->insertDoc($datasetUUID, $annotated, $key, $pwd);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to insert documents - ' . $ex->getMessage()]);
        }
        $this->getResponse()->setStatusCode(201);
        //http_response_code(201);

        //Activity Log
        $action = "Create";
        $summary = "Create new document";
        $logData = $this->_assembleLogData($datasetUUID, $key, $action, $summary, $annotated['_id']);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($annotated);
    }

    /*
     * UPDATE - Handling a PUT request
     */
    public function update($id, $data) {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $docID = $this->params()->fromRoute('doc-id', null);
        if (!$docID) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing document id']);
        }
        $datasetUUID = $id;
        if (!$datasetUUID) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing dataset id']);
        }
        $object = $data;
        if ($object == null) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, malformed JSON']);
        }
        //Any _id supplied in the JSON is ignored/overwritten with the one passed in the URL path
        $object['_id'] = $docID;
        $annotated = $this->_annotateObject($object, $datasetUUID);
        $annotated['_updated'] = true;

        //CHECK IF SCHEMA VALIDATION ENABLED FOR THIS DATASET...
        $validationSchemas = $this->_schemaRepository->getValidationSchemas($datasetUUID);
        if (!is_null($validationSchemas)) {
            try {
                if (!$this->_schemaValidator->validate($object, $validationSchemas)) {
                    //This shouldn't happen as failed validation throws an exception which is caught further below
                    $this->getResponse()->setStatusCode(400);
                    return new JsonModel(['error' => 'JSON schema validation failed']);
                }
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => "JSON Schema validation error", 'details' => json_decode($ex->getMessage())]);
            }

        }

        try {
            $response = $this->_repository->updateDoc($datasetUUID, $docID, $annotated, $key, $pwd);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to update document - ' . $ex->getMessage()]);
        }
        if ($response == "UPDATED") {
            $this->getResponse()->setStatusCode(204);
        }
        elseif ($response == "CREATED") {
            $this->getResponse()->setStatusCode(201);
        }
        else {
            //something went wrong
        }

        //Activity Log
        $action = "Update";
        $summary = "Update or create a specified documment";
        $logData = $this->_assembleLogData($datasetUUID, $key, $action, $summary, $docID);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($annotated);
    }

    public function delete($id)  {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $docID = $this->params()->fromRoute('doc-id', null);
        if (is_null($docID)) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing document id']);
        }
        else {
            try {
                $result = $this->_repository->deleteDoc($id,$docID,$key,$pwd);
            }catch (\Throwable $ex) {
                $this->_handleException($ex);
                return new JsonModel(['error' => 'Failed to delete document - ' . $ex->getMessage()]);
            }

            if ($result == "DELETED") {
                $this->getResponse()->setStatusCode(204);
                $response = [
                    "message" => "Object deleted",
                ];
            }
            else {
                $this->getResponse()->setStatusCode(200);
                $response = [
                    "message" => "No items to delete",
                ];
            }
        }

        //Activity Log
        $datasetUUID = $id;
        $action = "Delete";
        $summary = "Remove a document from dataset";
        $logData = $this->_assembleLogData($datasetUUID, $key, $action, $summary, $docID);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($response);
    }

}