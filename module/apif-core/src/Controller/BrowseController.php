<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use MongoDB;

class BrowseController extends AbstractRestfulController
{
    private $_config;
    private $_repository;
    private $_readLogger;
    private $_activityLog;

    public function __construct(APIFCoreRepositoryInterface $repository, ActivityLogManagerInterface $activityLog, array $config, $readLogger)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_readLogger = $readLogger;
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

    private function _wrapMetadata ($input, $metadata) {
        $output = $metadata;
        $output['results'] = $input;
        return $output;
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

    public function options() {
        $this->getResponse()->getHeaders()->addHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
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
        /*
        $logEntry = [
            'method' => 'GET',
            'controller' => 'BROWSE'
        ];
        */
        //$this->_readLogger->info(json_encode($logEntry));
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];

        $metadata = [];

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', null);
        $sortParam = $this->params()->fromQuery('sort', null);
        $pageParam = $this->params()->fromQuery('page', 1);
        $pageSizeParam = $this->params()->fromQuery('pagesize', 100);
        $fieldsParam = $this->params()->fromQuery('fields', null);
        //Sorting
        $sortTerms = [];
        if (!is_null($sortParam)){
            $exploded = explode(",",$sortParam);
            foreach ($exploded as $term){
                if (substr($term,0,1) == "-") {
                    $sortTerms[substr($term,1)] = -1;
                }
                else {
                    $sortTerms[$term] = 1;
                }
            }
        }

        //Fields to return
        $fields = null;
        if (!is_null($fieldsParam)){
            $fields = [];
            $exploded = explode(",",$fieldsParam);
            foreach ($exploded as $field) {
                if (substr($field, 0, 1) == "-") {
                    $fields[substr($field, 1)] = 0;
                } else {
                    $fields[$field] = 1;
                }
            }
        }

        //Do this before any response might happen (including errors)
        $this->getResponse()->getHeaders()->addHeaders([
            'Access-Control-Allow-Origin' => '*'
        ]);

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

        //Pagination...
        if (!is_null($pageParam)){
            if (is_null($pageSizeParam)){
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, page number specified without page size']);
            }
            if (!is_null($limitParam)){
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, limit parameter cannot be used with pagination']);
            }
            $limitParam = $pageSizeParam;
        }

        if ((!is_null($pageSizeParam)) && (is_null($pageParam))){
            $pageParam = 1;
            if (!is_null($limitParam)){
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request, limit parameter cannot be used with pagination']);
            }
            $limitParam = $pageSizeParam;
        }

        try {
            //$data = $this->_repository->findDocs($id,$key,$pwd,$query,(int)$limitParam,$sortTerms,$fields);
            $data = $this->_repository->findDocsPaged($id,$key,$pwd,$query,(int)$limitParam,$sortTerms,$fields,(int)$pageParam);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve documents - ' . $ex->getMessage()]);
        }

        $metadata['messages'] = [];
        $metadata['sort'] = $sortTerms;
        $metadata['pagesize'] = $limitParam;
        $metadata['page'] = $pageParam;
        $metadata['documentCount'] = count($data);

        /*
        if (!is_null($pageSizeParam)){
            $pageStart = ($pageParam - 1) * $pageSizeParam;
            $data = array_slice($data,$pageStart,$pageSizeParam);
            $metadata['page'] = $pageParam;
            $metadata['pageSize'] = (int)$pageSizeParam;
        }
        */

        //$logEntry['metadata'] = $metadata;
        //$this->_readLogger->info(json_encode($logEntry));

        //Activity Log
        $datasetUUID = $id;
        $action = "Browse";
        $summary = "Retrieve multiple documents via the browse interface";
        $logData = $this->_assembleLogData($datasetUUID, $key, $action, $summary);
        $this->_activityLog->logActivity($logData);

        /*
        $this->getResponse()->getHeaders()->addHeaders([
            'Access-Control-Allow-Origin' => '*'
        ]);
        */
        return new JsonModel($this->_wrapMetadata($data, $metadata));
    }
}