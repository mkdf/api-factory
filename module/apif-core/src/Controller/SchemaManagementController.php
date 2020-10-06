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
        $schema['@id'] = $id;
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
            $schemaObj = json_decode($schemaParam);
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

        //Create schema
        try {
            $auth = $this->_getAuth();
            $response = $this->_repository->createSchema($schemaEntry, $auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to create schema - ' . $ex->getMessage()]);
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