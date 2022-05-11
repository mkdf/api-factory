<?php

namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use MongoDB;

class ActivityController extends AbstractRestfulController
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

    private function getPermissionsOnDataset($roles, $datasetId) {
        $permissions = [
            'read' => false,
            'write' => false
        ];
        foreach ($roles as $role) {
            $roleLabel = $role['role'];
            $permissionLabel = substr($roleLabel, -1);
            $datasetLabel =  substr($roleLabel, 0, -2);

            // If we find a matching dataset...
            if ($datasetLabel == $datasetId) {
                if (($permissionLabel == 'R') AND ($permissions['read'] == false)) {
                    $permissions['read'] = true;
                }
                if (($permissionLabel == 'W') AND ($permissions['write'] == false)) {
                    $permissions['write'] = true;
                }
            }
        }

        return $permissions;
    }

    public function get($id) {
        // Credentials supplied with the request
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];

        //Get URL params
        $timestampParam = $this->params()->fromQuery('timestamp', "");
        $limitParam = $this->params()->fromQuery('limit', "");
        $sortParam = $this->params()->fromQuery('sort', "");
        // This should = 0 or 1 if timestamp not supplied or an invalid string supplied.
        // Either way, all items will be returned.
        $timestamp = intval($timestampParam);
        $limit = intval($limitParam);
        $sort = intval($sortParam);

        // Admin credentials for querying the permissions DB
        $adminUser = $this->_config['mongodb']['adminUser'];
        $adminPwd = $this->_config['mongodb']['adminPwd'];
        $adminAuth = [
            'user' => $adminUser,
            'pwd' => $adminPwd
        ];

        // CHECK PERMISSIONS ON DATASET
        try {
            $results = $this->_repository->getKey($key,$adminAuth);
        }catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve permissions - ' . $ex->getMessage()]);
        }

        $roles = $results[0]['users'][0]['roles'];
        $permissions = $this->getPermissionsOnDataset($roles, $id);

        // ######
        // Access is granted to activity log if user/key has READ permission on this dataset
        // ######
        //if ($permissions['read'] AND $permissions['write']) {
        if ($permissions['read']) {
            $activityLogId = $this->_config['activityLog']['dataset'];
            // ONLY RETRIEVE WRITE ACTIONS, NOT READS.
            $query = [
                'al:datasetId' => $id,
                '$or' => [
                    ['@type' => 'al:Create'],
                    ['@type' => 'al:Update'],
                    ['@type' => 'al:Delete'],
                    ['@type' => 'al:CreateFile'],
                    ['@type' => 'al:DeleteFile'],
                    ['@type' => 'al:OverwriteFile'],
                ],
                '_timestamp' => ['$gte' => $timestamp]
            ];
            // DO NOT RETURN ANY FIELDS WHICH CONTAIN THE KEY USED IN THE ORIGINAL REQUEST
            $projection = [
                'al:summary' => false,
                'al:request.al:agent' => false,
            ];

            if ($timestamp <= 0) { //no valid timestamp supplied
                if ($limit <= 0) {
                    $limit = 100; //if no timestamp or limit specified, limit to 100
                }
            }
            if ($limit <= 0) {
                $limit = null;
            }
            if ($sort != 1) {
                $sort = null;
            }
            else {
                $sort = '_timestamp';
            }
            $docs = $this->_repository->findDocs($activityLogId, $adminUser, $adminPwd, $query, $limit, $sort, $projection);
        }
        else {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'Authorization failed, you do not have access to activity entries for this dataset']);
        }

        return new JsonModel($docs);
    }
}