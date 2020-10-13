<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\SchemaRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use APIF\Core\Service\JsonModel;

class SchemaRetrievalController extends AbstractRestfulController
{
    private $_config;
    private $_repository;

    public function __construct(SchemaRepositoryInterface $repository, array $config)
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
            throw new \Exception('Authentication credentials missing');
        }
        return $auth;
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

    public function get($id) {
        //get a single schema (schema body only, no metadata)

        //remove '.json' from schema ID if supplied.
        if (substr($id, -5) == ".json") {
            $id = substr($id,0,-5);
        }

        try {
            $response = $this->_repository->retrieveSimpleSchema($id);
        }
        catch (\Throwable $ex) {
            $this->_handleException($ex);
            return new JsonModel(['error' => 'Failed to retrieve schema details - ' . $ex->getMessage()]);
        }
        //FIXME - REWRITE SCHEMA ID/URI HERE...
        if (!(substr($response['$id'],0,4) == "http")) {
            $urlPrefix = ($_SERVER['HTTPS']) ? "https://" : "http://";
            $response['$id'] = $urlPrefix . $_SERVER['SERVER_NAME'] . "/schemas/" . $response['$id'] . ".json";

        }
        //FIXME - Give 404 on empty $response
        $jsonModel = new JsonModel($response);
        $jsonModel->setOption('prettyPrint',true);
        return $jsonModel;
    }

}