<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;


class DatasetManagementController extends AbstractRestfulController
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
        $auth = $this->_getAuth();
        $datasetList = $this->_repository->getDatasetList($auth);
        return new JsonModel($datasetList);
    }

    public function get($id)
    {
        $auth = $this->_getAuth();
        $datasetInfo = $this->_repository->getDataset($id,$auth);
        return new JsonModel($datasetInfo);
    }

    //POST
    public function create($data) {
        $auth = $this->_getAuth();
        //Get URL params
        $uuidParam = $this->params()->fromQuery('uuid', null);
        $keyParam = $this->params()->fromQuery('key', null);
        //Check the datasetUUID and access key have been provided...
        if (!$uuidParam || !$keyParam) {
            $this->getResponse()->setStatusCode(400);
            echo 'Bad request, missing dataset id or access key';
            exit();
        }

        //Create dataset (and read/write roles for this dataset)
        $this->_repository->createDataset($uuidParam, $auth);

        //Create key (DB user) and assign to dataset read/write roles
        $this->_repository->createKey($keyParam, $uuidParam, $auth);

        return new JsonModel([]);
    }

    //PUT
    public function update($id, $data) {
        //Build a replication of POST/create() here, only with PUT the dataset ID is passed
        //in the URL, so only the key needs to be passed as a param
        $auth = $this->_getAuth();
        //Get URL params
        $uuidParam = $id; //this comes from the URL in a PUT
        $keyParam = $this->params()->fromQuery('key', null);
        //Check the datasetUUID and access key have been provided...
        if (!$keyParam) {
            $this->getResponse()->setStatusCode(400);
            echo 'Bad request, access key';
            exit();
        }

        //Create dataset (and read/write roles for this dataset)
        $this->_repository->createDataset($uuidParam, $auth);

        //Create key (DB user) and assign to dataset read/write roles
        $this->_repository->createKey($keyParam, $uuidParam, $auth);

        return new JsonModel([]);
    }

    public function delete($id) {

    }
}