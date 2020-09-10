<?php

namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use MongoDB;

class ObjectController extends AbstractRestfulController
{
    private $_config;
    private $_repository;
    private $_activityLog;

    public function __construct(APIFCoreRepositoryInterface $repository, ActivityLogManagerInterface $activityLog, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_activityLog = $activityLog;
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

    private function _assembleLogData ($datasetId, $key) {
        $timestamp = time();
        $data = [
            '@context' => "some context",
            "type" => "Activity",
            "summary" => "some summary",
            "actor" => [
                'type' => 'Key',
                'name' => $key
            ],
            'dataset' => $datasetId,
            'uri' =>   $this->getRequest()->getUriString(),
            'method' =>   $this->getRequest()->getMethod(),
            'object' =>  [
                'type' => 'document',
                'name' => 'name',
                'content' => json_decode($this->getRequest()->getContent())
            ]
        ];

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

    /*
     * GET - Handling a GET request
     * brings back all docs from a dataset (subject to limit), or a query
     * if query param is provided
     */
    public function get($id) {
        $key = $this->_getAuth()['user'];

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
                http_response_code(400);
                echo 'Bad request, malformed JSON query';
                exit();
            }
        }
        $data = $this->_repository->findDocs($id,$key,$query,(int)$limitParam,$sortTerms);

        //TODO - Apply pagination here
        if (!is_null($pageSizeParam)){
            $pageStart = ($pageParam - 1) * $pageSizeParam;
            $data = array_slice($data,$pageStart,$pageSizeParam);
        }

        //Activity Log
        $logData = $this->_assembleLogData($datasetUUID, $key);
        $this->_activityLog->logActivity($logData);

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
        $datasetUUID = $this->params()->fromRoute('id', null);
        if (is_null($datasetUUID)) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            echo 'Bad request, missing dataset id';
            exit();
        }

        //$object = json_decode($data);
        $object = $data;
        if ($object == null) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            echo 'Bad request, malformed JSON';
            exit();
        }
        $annotated = $this->_annotateObject($object, $datasetUUID);
        $response = $this->_repository->insertDoc($datasetUUID, $annotated, $key);
        $this->getResponse()->setStatusCode(201);
        http_response_code(201);

        //Activity Log
        $logData = $this->_assembleLogData($datasetUUID, $key);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($annotated);
    }

    /*
     * UPDATE - Handling a PUT request
     */
    public function update($id, $data) {
        $key = $this->_getAuth()['user'];
        $docID = $this->params()->fromRoute('doc-id', null);
        if (!$docID) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            echo 'Bad request, missing document id';
            exit();
        }
        $datasetUUID = $id;
        if (!$datasetUUID) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            echo 'Bad request, missing dataset id';
            exit();
        }
        $object = $data;
        if ($object == null) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            echo 'Bad request, malformed JSON';
            exit();
        }
        //Any _id supplied in the JSON is ignored/overwritten with the one passed in the URL path
        $object['_id'] = $docID;
        $annotated = $this->_annotateObject($object, $datasetUUID);
        $annotated['_updated'] = true;

        $response = $this->_repository->updateDoc($datasetUUID, $docID, $annotated, $key);
        if ($response == "UPDATED") {
            $this->getResponse()->setStatusCode(204);
            http_response_code(204);
        }
        elseif ($response == "CREATED") {
            $this->getResponse()->setStatusCode(201);
            http_response_code(204);
        }
        else {
            //something went wrong
        }

        //Activity Log
        $logData = $this->_assembleLogData($datasetUUID, $key);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($annotated);
    }

    public function delete($id)  {
        $key = $this->_getAuth()['user'];
        $docID = $this->params()->fromRoute('doc-id', null);
        if (is_null($docID)) {
            $this->getResponse()->setStatusCode(400);
            http_response_code(400);
            $this->getResponse()->setContent('Bad request, missing document id');
            //echo 'Bad request, missing document id';
            $response = [
                "message" => "Bad request, missing document id",
            ];
            //exit();
        }
        else {
            $result = $this->_repository->deleteDoc($id,$docID,$key);
            if ($result == "DELETED") {
                $this->getResponse()->setStatusCode(204);
                http_response_code(204);
                $response = [
                    "message" => "Object deleted",
                ];
            }
            else {
                $this->getResponse()->setStatusCode(200);
                $response = [
                    "message" => "No items to delete",
                ];
                //echo 'no items to delete';
            }
        }

        //Activity Log
        $logData = $this->_assembleLogData($datasetUUID, $key);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($response);
    }

}