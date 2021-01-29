<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Repository\FileRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class FileController extends AbstractRestfulController
{
    private $_config;
    private $_coreRepository;
    private $_fileRepository;
    private $_readLogger;
    private $_activityLog;

    public function __construct(APIFCoreRepositoryInterface $coreRepository, FileRepositoryInterface $fileRepository, ActivityLogManagerInterface $activityLog, array $config, $readLogger)
    {
        $this->_config = $config;
        $this->_coreRepository = $coreRepository;
        $this->_fileRepository = $fileRepository;
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
        return new JsonModel(['message' => 'file controller GET single file: '.$docID]);
    }

    /*
     * GET LIST OF ALL FILES IN DATASET
     */
    public function getList() {
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        return new JsonModel(['message' => 'file controller GET dataset file list for dataset: '.$datasetID]);
    }

    /*
     * CREATE - Handling a POST request
     */
    public function create($data) {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $datasetID = $this->params()->fromRoute('dataset-id', null);

        //check valid submission
        $request = $this->getRequest();
        // Make certain to merge the $_FILES info!
        $post = array_merge_recursive(
            $request->getPost()->toArray(),
            $request->getFiles()->toArray()
        );
        if (is_null($post['title']) || is_null($post['description'])) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request. Missing title or description']);
        }
        if (is_null($post['file'])) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request. Missing file']);
        }

        /*
         * Process:
         *  - check permissions
         *  - write file to store (or fail)
         *  - create metadata (or fail and remove stored file)
         *  - return metadata entry
         */

        //check write access
        if (!$this->_coreRepository->checkWriteAccess($datasetID, $key, $pwd)) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'You do not have write access on this dataset']);
        }

        print_r($post);
        //move file into correct location
        if (!$this->_fileRepository->writeFile($post['file'],$datasetID)) {
            $this->getResponse()->setStatusCode(500);
            return new JsonModel(['error' => 'Error occurred during file upload']);
        }

        //create metadata
        $metaItem = [
            'title' => $post['title'],
            'description' => $post['description'],
            'filename' => basename($post['file']['tmp_name']),
            'filenameOriginal' => $post['file']['name'],
            'type' => $post['file']['type'],
            'size' => $post['file']['size']
        ];
        try {
            $metaSuccess = $this->_coreRepository->writeFileMetadata($metaItem, $datasetID, false);
        }catch (\Throwable $ex) {
            //$this->_handleException($ex);
            // FIXME - there was a problem creating the metadata, remove the file from the file store and inform user.
            return new JsonModel(['error' => 'Failed to create metadata - ' . $ex->getMessage()]);
        }

        //return metadata

        return new JsonModel(['message' => 'file controller POST (create file)']);

    }

    /*
     * UPDATE - Handling a PUT request
     */
    public function update($id, $data) {
        $docID = $this->params()->fromRoute('id', null);
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        return new JsonModel(['message' => 'file controller PUT (overwrite file)']);
    }

    /*
     * DELETE - Handling a DELETE request
     */
    public function delete($id) {
        $docID = $this->params()->fromRoute('id', null);
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        return new JsonModel(['message' => 'file controller DELETE (delete file)']);
    }

}