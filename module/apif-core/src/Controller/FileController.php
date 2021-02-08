<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Repository\FileRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\Http\Response\Stream;
use Laminas\Http\Headers;

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
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $filename = $this->params()->fromRoute('id', null);
        $datasetID = $this->params()->fromRoute('dataset-id', null);

        //check read access
        if (!$this->_coreRepository->checkReadAccess($datasetID, $key, $pwd)) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'You do not have read access on this dataset']);
        }


        $result = $this->_coreRepository->getDatasetFile($datasetID, $filename);

        if (is_null($result)) {
            $this->getResponse()->setStatusCode(404);
            return new JsonModel(['error' => 'File not found']);
        }
        else {
            $fullpath = $this->_fileRepository->getFileLocation($result, $datasetID);

            $response = new Stream();
            $response->setStream(fopen($fullpath, 'r'));
            $response->setStatusCode(200);
            $response->setStreamName($result['filenameOriginal']);
            $headers = new Headers();
            $headers->addHeaders(array(
                'Content-Disposition' => 'attachment; filename="' . $result['filenameOriginal'] .'"',
                'Content-Type' => $result['type'],
                //'Content-Length' => filesize($fullpath),
                'Content-Length' => $result['size'],
                'Expires' => '@0', // @0, because zf2 parses date as string to \DateTime() object
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public'
            ));
            $response->setHeaders($headers);
            return $response;
        }
    }

    /*
     * GET LIST OF ALL FILES IN DATASET
     */
    public function getList() {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $datasetID = $this->params()->fromRoute('dataset-id', null);

        //check read access
        if (!$this->_coreRepository->checkReadAccess($datasetID, $key, $pwd)) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'You do not have read access on this dataset']);
        }

        $result = $this->_coreRepository->getDatasetFileList($datasetID);
        if (is_null($result)) {
            $arrayResponse = [];
        }
        else {
            $arrayResponse = [];
            foreach ($result as $key => $value){
                $arrayResponse[] = $value;
            }
        }

        return new JsonModel($arrayResponse);
    }

    /*
     * CREATE - Handling a POST request
     */
    public function create($data) {
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        $overwriteFilename = $this->params()->fromRoute('id', null);

        //check valid submission
        $request = $this->getRequest();
        // Make certain to merge the $_FILES info!
        $post = array_merge_recursive(
            $request->getPost()->toArray(),
            $request->getFiles()->toArray()
        );

        //check title and description present
        if (is_null($post['title']) || is_null($post['description'])) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request. Missing title or description']);
        }
        //Check file is present
        if (is_null($post['file'])) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request. Missing file']);
        }
        //check write access
        if (!$this->_coreRepository->checkWriteAccess($datasetID, $key, $pwd)) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'You do not have write access on this dataset']);
        }

        //if this is an update/overwrite
        if (!is_null($overwriteFilename)){
            //check filename matches the filename requested for update
            if (!($post['file']['name'] == $overwriteFilename)) {
                $this->getResponse()->setStatusCode(400);
                return new JsonModel(['error' => 'Bad request. Filename does not match the filename requested for update']);
            }
        }

        $fileUploadResult = $this->_fileRepository->writeFile($post['file'],$datasetID);
        if (!$fileUploadResult) {
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
            $overwrittenFile = $this->_coreRepository->writeFileMetadata($metaItem, $datasetID, $overwriteFilename);
        }catch (\Throwable $ex) {
            //remove orphaned file
            $this->_fileRepository->deleteFile($metaItem['filename'], $datasetID);
            return new JsonModel(['error' => 'Failed to create metadata - ' . $ex->getMessage()]);
        }

        if (!is_null($overwrittenFile)) {
            //FIXME - this function is currently empty and needs a body
            $this->_fileRepository->deleteFile($overwrittenFile, $datasetID);
            $this->getResponse()->setStatusCode(204);
            return new JsonModel(['message' => 'File updated']);
        }
        else {
            $this->getResponse()->setStatusCode(201);
            return new JsonModel(['message' => 'File created']);
        }
    }

    /*
     * DELETE - Handling a DELETE request
     */
    public function delete($id) {
        $filename = $this->params()->fromRoute('id', null);
        $datasetID = $this->params()->fromRoute('dataset-id', null);
        $key = $this->_getAuth()['user'];
        $pwd = $this->_getAuth()['pwd'];

        if (is_null($datasetID || is_null($filename))) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel(['error' => 'Bad request. Missing dataset ID or filename']);
        }

        //check write access
        if (!$this->_coreRepository->checkWriteAccess($datasetID, $key, $pwd)) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(['error' => 'You do not have write access on this dataset']);
        }

        //Remove metadata
        try{
            $localFilename = $this->_coreRepository->removeFileMetadata($filename, $datasetID);
        }catch (\Throwable $ex) {
            $this->getResponse()->setStatusCode(500);
            return new JsonModel(['error' => 'Failed to remove file metadata - ' . $ex->getMessage()]);
        }

        //Remove file
        $deleteResult = $this->_fileRepository->deleteFile($localFilename, $datasetID);

        if ($deleteResult) {
            $this->getResponse()->setStatusCode(204);
            return new JsonModel(['message' => 'File deleted']);
        }
        else {
            $this->getResponse()->setStatusCode(500);
            return new JsonModel(['error' => 'Unable to delete file']);
        }

    }

}