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

    public function create($data) {

    }

    public function update($id, $data) {

    }

    public function delete($id) {

    }
}