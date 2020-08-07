<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;


class PermissionsManagementController extends AbstractRestfulController
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
            $auth = [
                'user'  => $_SERVER['PHP_AUTH_USER'],
                'pwd'   => $_SERVER['PHP_AUTH_PW']
            ];
            return $auth;
        }
    }

    public function getList()
    {
        //get all keys
        $auth = $this->_getAuth();
        $keys = $this->_repository->getAllKeys($auth);
        return new JsonModel($keys);
    }

    public function get($id)
    {
        //get one key
        $auth = $this->_getAuth();
        $key = $this->_repository->getKey($id,$auth);
        return new JsonModel($key);
    }

    public function create($data) {
        //get the key from the URL and pass to the update() function
        $key = $this->params()->fromRoute('id', null);
        if (!$key) {
            $this->getResponse()->setStatusCode(400);
            echo 'Bad request, missing key in URL';
            exit();
        }
        return $this->update($key);
    }

    public function update($id) {
        //set permissions here
        $auth = $this->_getAuth();
        $datasetParam = $this->params()->fromQuery('dataset-uuid', null);
        $readParam = $this->params()->fromQuery('read', null);
        $writeParam = $this->params()->fromQuery('write', null);
        if (is_null($datasetParam) || is_null($readParam) || is_null($writeParam)) {
            $this->getResponse()->setStatusCode(400);
            echo 'Bad request, missing dataset id or read/write parameters';
            exit();
        }

        $this->_repository->setKeyPermissions($id, $datasetParam, $readParam, $writeParam, $auth);
        return new JsonModel([]);
    }

    public function delete($id) {

    }
}