<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class FileController extends AbstractRestfulController
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

    /*
     * GET SINGLE FILE
     */
    public function get($id) {
        $docID = $this->params()->fromRoute('id', null);
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        return new JsonModel(['message' => 'file controller GET doc '.$docID]);
    }

    /*
     * GET LIST OF ALL FILES IN DATASET
     */
    public function getList() {
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        return new JsonModel(['message' => 'file controller GET dataset '.$datasetID]);
    }

    /*
     * CREATE - Handling a POST request
     */
    public function create($data) {
        return new JsonModel(['message' => 'file controller POST (create file)']);
    }

    /*
     * UPDATE - Handling a PUT request
     */
    public function update($id, $data) {
        return new JsonModel(['message' => 'file controller PUT (overwrite file)']);
    }

    /*
     * DELETE - Handling a DELETE request
     */
    public function delete($id) {
        return new JsonModel(['message' => 'file controller DELETE (delete file)']);
    }

}