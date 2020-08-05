<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use MongoDB;

class ObjectController extends AbstractRestfulController
{
    private $_config;
    private $_repository;

    public function __construct(APIFCoreRepositoryInterface $repository, array $config)
    {
        $this->_config = $config;
        $this->_repository = $repository;
    }

    private function _getAuth() {
        //Check AUTH has been passed
        $request_method = $_SERVER["REQUEST_METHOD"];
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            //TODO - issue these headers via proper LAMINAS protocol
            header('WWW-Authenticate: Basic realm="Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Dataset key must be provided as HTTP Basic Auth username';
            exit;
        } else {
            return $_SERVER['PHP_AUTH_USER'];
        }
    }

    public function get($id) {
        //$id is the dataset-id (mongodb collection)
        //TODO - Check for existence of a doc-id...
        //TODO - get query from request
        $key = $this->_getAuth();

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', "");
        $sortParam = $this->params()->fromQuery('sort', "");

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

        $data = $this->_repository->findDocs($id,$key,$query);
        return new JsonModel(['data' => $data]);
    }

    public function getList() {
        //not used
        return new JsonModel([]);
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

    //Handling POST requests
    public function create($data) {
        $key = $this->_getAuth();
        $datasetUUID = $this->params()->fromRoute('id', null);
        if (!$datasetUUID) {
            http_response_code(400);
            echo 'Bad request, missing dataset id';
            exit();
        }

        //$object = json_decode($data);
        $object = $data;
        if ($object == null) {
            http_response_code(400);
            echo 'Bad request, malformed JSON';
            exit();
        }
        $annotated = $this->_annotateObject($object, $datasetUUID);
        $response = $this->_repository->insertDoc($datasetUUID, $annotated, $key);

        return new JsonModel($annotated);
    }


}