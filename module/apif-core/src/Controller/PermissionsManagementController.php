<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use MongoDB;


class PermissionsManagementController extends AbstractRestfulController
{
    private $_config;
    private $_repository;

    public function __construct(APIFCoreRepositoryInterface $repository, ActivityLogManagerInterface $activityLog, array $config)
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
            // FIXME Throw exception and manage response in the action method
            header('WWW-Authenticate: Basic realm="Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Authentication credentials missing';
            exit;
        }
        return $auth;
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

    public function getList()
    {
        //get all keys
        $auth = $this->_getAuth();
        try {
            $keys = $this->_repository->getAllKeys($auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve permissions - ' . $ex->getMessage()]);
        }

        //Activity Log
        $datasetUUID = null;
        $action = "ManagementRetrievePermissionsList";
        $summary = "Retrieve all dataset/key permissions";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($keys);
    }

    public function get($id)
    {
        //get one key
        $auth = $this->_getAuth();
        try {
            $key = $this->_repository->getKey($id,$auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve key permissions - ' . $ex->getMessage()]);
        }

        //Activity Log
        $datasetUUID = null;
        $action = "ManagementRetrievePermission";
        $summary = "Retrieve single key permissions";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        return new JsonModel($key);
    }

    public function create($data) {
        //get the key from the URL and pass to the update() function
        $key = $this->params()->fromRoute('id', null);
        if (!$key) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing key in URL']);
        }
        try {
            return $this->update($key,$data);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to set key permissions - ' . $ex->getMessage()]);
        }
    }


    public function update($id, $data) {
        //set permissions here
        $auth = $this->_getAuth();
        $datasetParam = $data['dataset-uuid'];
        $readParam = $data['read'];
        $writeParam = $data['write'];
        if (is_null($datasetParam) || is_null($readParam) || is_null($writeParam)) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request, missing dataset id or read/write parameters']);
        }

        try {
            $this->_repository->setKeyPermissions($id, $datasetParam, $readParam, $writeParam, $auth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to set key permissions - ' . $ex->getMessage()]);
        }

        //Activity Log
        $datasetUUID = $datasetParam;
        $action = "ManagementUpdatePermissions";
        $summary = "Update or create dataset/key permissions";
        $logData = $this->_assembleLogData($datasetUUID, $auth['user'], $action, $summary);
        $this->_activityLog->logActivity($logData);

        return new JsonModel();
    }

    public function delete($id) {

    }
}
