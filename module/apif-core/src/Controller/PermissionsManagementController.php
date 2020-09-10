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
            $jm = new JsonModel();
            $jm->setVariable("message",'Bad request, missing key in URL');
            return $jm;
        }
        return $this->update($key,$data);
    }


    public function update($id, $data) {
        //set permissions here
        $auth = $this->_getAuth();
        $datasetParam = $data['dataset-uuid'];
        $readParam = $data['read'];
        $writeParam = $data['write'];
        if (is_null($datasetParam) || is_null($readParam) || is_null($writeParam)) {
            $this->getResponse()->setStatusCode(400);
            $jm = new JsonModel();
            $jm->setVariable("message",'Bad request, missing dataset id or read/write parameters');
            return $jm;
        }

        $this->_repository->setKeyPermissions($id, $datasetParam, $readParam, $writeParam, $auth);
        return new JsonModel();
    }

    public function delete($id) {

    }
}
