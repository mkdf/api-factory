<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\SchemaRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class SchemaManagementController extends AbstractRestfulController
{
    private $_config;
    private $_repository;

    public function __construct(SchemaRepositoryInterface $repository, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
    }

    private function _getAuth() {
        //Check AUTH has been passed
        $request_method = $_SERVER["REQUEST_METHOD"];
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $auth = [
                'user'  => $_SERVER['PHP_AUTH_USER'],
                'pwd'   => $_SERVER['PHP_AUTH_PW']
            ];

        }
        elseif (!is_null($this->params()->fromQuery('user', null)) && !is_null($this->params()->fromQuery('pwd', null))) {
            $auth = [
                'user'  => $this->params()->fromQuery('user'),
                'pwd'   => $this->params()->fromQuery('pwd')
            ];
        }
        elseif (!is_null($this->params()->fromPost('user', null)) && !is_null($this->params()->fromPost('pwd', null))) {
            $auth = [
                'user'  => $this->params()->fromPost('user'),
                'pwd'   => $this->params()->fromPost('pwd')
            ];
        }
        else {
            throw new \Exception('Authentication credentials missing');
        }
        return $auth;
    }

    private function _annotateObject($input){
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

        //$object['_datasetid'] = $uuid;
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

    private function _rewriteSchemaId ($schema, $id) {
        $schema['$id'] = $id;
        return $schema;
    }

    public function get($id) {
        //get a single schema details, with metadata (not simply the schema alone)
        try {
            $auth = $this->_getAuth();
            $schema = $this->_repository->findSingleSchemaDetails($id, $auth);
        }
        catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve schema details - ' . $ex->getMessage()]);
        }
        return new JsonModel($schema);
    }

    public function getList() {
        //get all schemas
        try {
            $auth = $this->_getAuth();
            $schemas = $this->_repository->findSchemas($auth);
        }
        catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve schema list - ' . $ex->getMessage()]);
        }
        return new JsonModel($schemas);
    }

    public function create($data) {
        //Get URL params
        $schemaIdParam = $this->params()->fromPost('schema-id', null);
        $schemaParam = $this->params()->fromPost('schema', null);
        $externalParam = $this->params()->fromPost('external', null);
        //Check the schemaId and schema have been provided...
        if (is_null($schemaIdParam) || is_null($schemaParam) || is_null($externalParam)) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing schema id, schema or external flag']);
        }

        try {
            $schemaObj = json_decode($schemaParam, true);
            //FIXME - ALSO VALIDATE HERE AGAINST JSON-SCHEMA-SCHEMA
        }
        catch (\Throwable $ex) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, invalid JSON in schema']);
        }

        if ($externalParam == 0) {
            $schemaObj = $this->_rewriteSchemaId($schemaObj, $schemaIdParam);
        }

        $schemaEntry = [
            "_id" => $schemaIdParam,
            "schema_type" => "JSON-SCHEMA",
            "schema" => $schemaObj,
            "schema_str" => json_encode($schemaObj)
        ];
        $annotated = $this->_annotateObject($schemaEntry);

        //Create schema
        try {
            $auth = $this->_getAuth();
            $response = $this->_repository->createSchema($annotated, $auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to create schema - ' . $ex->getMessage()]);
            //FIXME - Duplicate key error (schema ID already exists) should be handled here more gracefully
        }

        $this->getResponse()->setStatusCode(201);

        //Activity Log
        $datasetUUID = $this->_config['schema']['dataset'];
        $action = "CreateSchema";
        $summary = "Create a new schema";
        //$logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        //$this->_activityLog->logActivity($logData);

        return new JsonModel($response);
    }

    public function update($id, $data) {

    }

    public function delete($id) {

    }

}