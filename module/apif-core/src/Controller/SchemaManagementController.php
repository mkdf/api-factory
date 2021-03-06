<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\SchemaRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use APIF\Core\Service\JsonModel;
use Laminas\Mvc\Controller\AbstractRestfulController;

use MongoDB;

class SchemaManagementController extends AbstractRestfulController
{
    private $_config;
    private $_repository;
    private $_activityLog;

    public function __construct(SchemaRepositoryInterface $repository, ActivityLogManagerInterface $activityLog, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_activityLog = $activityLog;
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

        //Activity Log
        $datasetUUID = $this->_config['schema']['dataset'];
        $action = "RetrieveSchemaDetails";
        $summary = "Retrieve full details for a schema";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

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

        //Activity Log
        $datasetUUID = $this->_config['schema']['dataset'];
        $action = "RetrieveSchemaList";
        $summary = "Retrieve full list of schemas";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($schemas);
    }

    public function create($data) {
        //Get URL params
        $schemaIdParam = $this->params()->fromPost('schema-id', null);
        $schemaParam = $this->params()->fromPost('schema', null);
        $externalParam = $this->params()->fromPost('external', null);
        //Check the schemaId and schema have been provided...
        if (is_null($schemaIdParam) || is_null($externalParam)) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing schema id, schema or external flag']);
        }

        if ($externalParam == 0) {
            //IF THIS IS A LOCAL SCHEMA (NOT EXTERNAL), CHECK FOR VALID ID & REWRITE "$id" ATTRIBUTE
            //ID SHOULD BE ALPHA-NUMERIC ONLY (inc hyphen and underscore), NO SPECIAL CHARS, DOTS, SLASHES... AND WITHOUT .JSON APPENDED
            try {
                $schemaObj = json_decode($schemaParam, true);
                if (!$schemaObj) {
                    throw new \Exception("Error: unable to parse JSON schema");
                }
                //FIXME - ALSO VALIDATE HERE AGAINST JSON-SCHEMA-SCHEMA
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, invalid JSON in schema']);
            }

            if(preg_match('/^[a-zA-Z_\-0-9]+$/', $schemaIdParam)) {
                $schemaObj = $this->_rewriteSchemaId($schemaObj, $schemaIdParam);
                $urlPrefix = ($_SERVER['HTTPS']) ? "https://" : "http://";
                $localURI = $urlPrefix . $_SERVER['SERVER_NAME'] . "/schemas/" . $schemaObj['$id'] . ".json";
            }
            else {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, Schema ID may only contain alphanumeric characters, hyphens(-) and underscores(_)']);
            }

            $newSchemaID = $schemaIdParam;
            $schemaEntry = [
                "_id" => $newSchemaID,
                "schema_type" => "JSON-SCHEMA",
                "external" => false,
                "schema" => $schemaObj,
                "schema_str" => json_encode($schemaObj)
            ];
        }
        else {
            //Processing for external schemas...
            /*
             * CREATE AN '_id' ATTRIBUTE FROM THE URI SUPPLIED:
             * http://example.org/schemas/weather.json becomes...
             * 'external-weather-123456789', where 123456789 is a random string.
             */
            if (filter_var($schemaIdParam, FILTER_VALIDATE_URL)) {
                $urlParsed = parse_url($schemaIdParam);
                $newSchemaID = $urlParsed['host'] . $urlParsed['path'];
                if (substr($newSchemaID, -5) == ".json") {
                    $newSchemaID = substr($newSchemaID,0,-5);
                }
                $search = ['/','.'];
                $replace = ['-','-'];
                $newSchemaID = str_replace($search, $replace, $newSchemaID);
                $urlPrefix = ($_SERVER['HTTPS']) ? "https://" : "http://";
                $localURI = $urlPrefix . $_SERVER['SERVER_NAME'] . "/schemas/" . $newSchemaID . ".json";

            } else {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Supplied external schema ID is not a valid URL']);
            }
            //Retrieve schema body from remote URL...
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $schemaIdParam,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Accept: application/json"
                    ),
                ));
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if (!json_decode($response)) {
                    throw new \Exception("Error: Unable to parse JSON");
                }
                if ($httpCode != 200) {
                    throw new \Exception("Error: HTTP response ".$httpCode);
                }
                curl_close($curl);
                $schemaBody = $response;
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Unable to retrieve and parse valid JSON schema from the supplied URL']);
            }

            $schemaEntry = [
                "_id" => $newSchemaID,
                "schema_type" => "JSON-SCHEMA",
                "external" => true,
                "schema" => json_decode($schemaBody, true),
                "schema_str" => $schemaBody
            ];
        }

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
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        //return new JsonModel($response);
        return new JsonModel(['schemaURI' => $annotated['schema']['$id'], 'schemaId' => $newSchemaID,'localURI' => $localURI]);
    }

    public function update($id, $data) {
        //if schema is external, no need to take any form input, just re-request the schema from the remote address
        $schemaParam = $data['schema'];

        //First, retrieve the schema
        try {
            $auth = $this->_getAuth();
            $schema = $this->_repository->findSingleSchemaDetails($id, $auth);
            if (count($schema) == 0) {
                throw new \Exception("Schema not found");
            }
        }
        catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve schema - ' . $ex->getMessage()]);
        }
        if ($schema[0]['external']) {
            //Schema is external, just re-request the schema body from the remote address
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $schema[0]['schema']['$id'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Accept: application/json"
                    ),
                ));
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if (!json_decode($response)) {
                    throw new \Exception("Error retrieving remote schema: Unable to parse JSON");
                }
                if ($httpCode != 200) {
                    throw new \Exception("Error retrieving remote schema: HTTP response ".$httpCode);
                }
                curl_close($curl);
                $schemaEntry =$schema[0];
                $schemaEntry['schema'] = json_decode($response, true);
                $schemaEntry['schema_str'] = $response;
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => $ex]);
            }
        }
        else {
            //Update local schema with new schema body supplied
            //Check the schema has been provided...
            if (is_null($schemaParam)) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, missing schema body for local schema update']);
            }
            try {
                $schemaObj = json_decode($schemaParam, true);
                if (!$schemaObj) {
                    throw new \Exception();
                }
                //FIXME - ALSO VALIDATE HERE AGAINST JSON-SCHEMA-SCHEMA
            }
            catch (\Throwable $ex) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, invalid JSON in schema']);
            }
            $schemaObj = $this->_rewriteSchemaId($schemaObj, $id);
            $schemaEntry =$schema[0];
            $schemaEntry['schema'] = $schemaObj;
            $schemaEntry['schema_str'] = json_encode($schemaObj);
        }
        $schemaEntry['_updated'] = true;
        $annotated = $this->_annotateObject($schemaEntry);

        //update schema
        try {
            $updateResponse = $this->_repository->updateSchema($id, $annotated, $auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to update schema - ' . $ex->getMessage()]);
            //FIXME - Duplicate key error (schema ID already exists) should be handled here more gracefully
        }

        //Activity Log
        $datasetUUID = $this->_config['schema']['dataset'];
        $action = "Update schema";
        $summary = "Update an existing schema";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        $this->getResponse()->setStatusCode(204);
        return new JsonModel();
    }

    public function delete($id) {

    }

    public function assignmentAction () {
        try {
            $auth = $this->_getAuth();
        }catch (\Throwable $ex) {
            $this->getResponse()->setStatusCode(401);
            return new JsonModel(['error' => 'Authentication required']);
        }
        $schemaId = $this->params()->fromRoute('id', null);
        $datasetId = $this->params()->fromRoute('datasetid', null);
        if (is_null($schemaId) || is_null($datasetId)) {
            //This should never happen
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, schema id or dataset id missing']);
        }

        //Check if a schema body was passed...
        $schemaParam = $this->params()->fromPost('schema', null);
        if (!is_null($schemaParam)) {
            $embeddedSchema = json_decode($schemaParam, true);
        } else {
            $embeddedSchema = null;
        }

        $message = [
            'message' => "",
            'schemaId' => $schemaId,
            'datasetId' => $datasetId
        ];
        switch ($_SERVER['REQUEST_METHOD']) {
            case "POST":
                //embed local dataset schema
                if (!is_null($embeddedSchema)) {
                    $embeddedSchemaId = $schemaId . "-" . $datasetId;
                    try {
                        $embeddedSchema['$id'] = $embeddedSchemaId;
                        $this->_repository->embedSchemaToDataset($embeddedSchemaId, $datasetId, $embeddedSchema, $auth);
                        $message['message'] = "Schema successfully embedded to dataset";
                        $message['schemaId'] = $embeddedSchemaId;

                        //Activity Log
                        $datasetUUID = $this->_config['schema']['dataset'];
                        $action = "AddEmbeddedSchema";
                        $summary = "Embed a local schema with a dataset";
                        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
                        $this->_activityLog->logActivity($logData);
                    }
                    catch (\Exception $ex) {
                        $this->_handleException($ex);
                        return new JsonModel(['error' => 'Failed to create embedded dataset schema - ' . $ex->getMessage()]);
                    }
                }
                //assign schema from catalogue
                else {
                    try {
                        if ($this->_repository->assignSchemaToDataset($schemaId, $datasetId, $auth) == 201) {
                            $message['message'] = "Schema successfully assigned to dataset";
                            $this->getResponse()->setStatusCode(201);
                            //Activity Log
                            $datasetUUID = $this->_config['schema']['dataset'];
                            $action = "AddSchemaAssignment";
                            $summary = "Assign an existing catalogue schema to a dataset";
                            $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
                            $this->_activityLog->logActivity($logData);
                        }
                        else {
                            $message['message'] = "Schema already assigned to dataset";
                            $this->getResponse()->setStatusCode(200);
                        }
                    }
                    catch (\Exception $ex) {
                        $this->_handleException($ex);
                        return new JsonModel(['error' => 'Failed to assign schema to dataset - ' . $ex->getMessage()]);
                    }
                }

                break;
            case "DELETE":
                try {
                    if ($this->_repository->deleteSchemaFromDataset($schemaId, $datasetId, $auth) == 204) {
                        $message['message'] = "Schema successfully removed from dataset";
                        $this->getResponse()->setStatusCode(204);

                        //Activity Log
                        $datasetUUID = $this->_config['schema']['dataset'];
                        $action = "RemoveSchemaAssignment";
                        $summary = "Remove a schema assignment from a dataset";
                        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
                        $this->_activityLog->logActivity($logData);
                    }
                    else {
                        $message['message'] = "No such schema assigned to dataset";
                        $this->getResponse()->setStatusCode(200);
                    }
                }
                catch (\Exception $ex) {
                    $this->_handleException($ex);
                    return new JsonModel(['error' => 'Failed to remove schema from dataset - ' . $ex->getMessage()]);
                }
                break;
            default:
                $this->getResponse()->setStatusCode(201);
                $message['message'] = "Bad request - HTTP method not supported: ".$_SERVER['REQUEST_METHOD'];
        }



        return new JsonModel($message);
    }

}