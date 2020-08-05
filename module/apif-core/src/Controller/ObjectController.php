<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;

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

        $queryTxt = $this->params()->fromQuery('query', "");
        if ($queryTxt == ""){
            $query = [];
        }
        else{
            $query = [];
        }

        $data = $this->_repository->findDocs($id,$key,$query);

        return new JsonModel(['data' => $data]);
    }

    public function getList() {
        //not used
        return new JsonModel([]);
    }

    public function create($data) {
        return new JsonModel();
    }
}