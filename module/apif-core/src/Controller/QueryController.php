<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;

class QueryController extends AbstractRestfulController
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

    /*
     * POST - Handling a POST request
     * brings back all docs from a dataset (subject to limit), or a query
     * if query body is provided
     *
     * The action is misleadingly called "create" since this is the LAMINAS REST controller action
     * associats with POST requests.
     */
    public function create($data) {
        $key = $this->_getAuth()['user'];

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', null);
        $sortParam = $this->params()->fromQuery('sort', "");

        $datasetUUID = $this->params()->fromRoute('id', null);
        if (is_null($datasetUUID)) {
            $this->getResponse()->setStatusCode(400);
            echo 'Bad request, missing dataset id';
            exit();
        }
        if (is_null($data)){
            $query = [];
        }
        else {
            $query = $data;
        }

        //If the $data payload that has been passed is not valid JSON, LAMINAS
        //just gives us a null object
        //TODO - we should differentiate between an empty query that returns all docs and a malformed query

        $response = $this->_repository->findDocs($datasetUUID,$key,$query,(int)$limitParam);
        return new JsonModel($response);
    }

}