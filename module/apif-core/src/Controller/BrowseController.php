<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class BrowseController extends AbstractRestfulController
{
    private $_config;
    private $_repository;
    private $_readLogger;

    public function __construct(APIFCoreRepositoryInterface $repository, array $config, $readLogger)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_readLogger = $readLogger;
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

    private function _wrapMetadata ($input, $metadata) {
        $output = $metadata;
        $output['results'] = $input;
        return $output;
    }

    /*
 * GET - Handling a GET request
 * brings back all docs from a dataset (subject to limit), or a query
 * if query param is provided
 */
    public function get($id) {
        $logEntry = [
            'method' => 'GET',
            'controller' => 'BROWSE'
        ];
        //$this->_readLogger->info(json_encode($logEntry));
        $key = $this->_getAuth()['user'];

        $metadata = [];

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', $this->_config['mongodb']['queryLimit']);
        $sortParam = $this->params()->fromQuery('sort', null);
        $pageParam = $this->params()->fromQuery('page', 1);
        $pageSizeParam = $this->params()->fromQuery('pagesize', null);
        $fieldsParam = $this->params()->fromQuery('fields', null);

        //Sorting
        $sortTerms = [];
        if (!is_null($sortParam)){
            $exploded = explode(",",$sortParam);
            foreach ($exploded as $term){
                if (substr($term,0,1) == "-") {
                    $sortTerms[substr($term,1)] = -1;
                }
                else {
                    $sortTerms[$term] = 1;
                }
            }
        }

        //Fields to return
        $fields = null;
        if (!is_null($fieldsParam)){
            $fields = [];
            $exploded = explode(",",$fieldsParam);
            foreach ($exploded as $field) {
                if (substr($field, 0, 1) == "-") {
                    $fields[substr($field, 1)] = 0;
                } else {
                    $fields[$field] = 1;
                }
            }
        }

        //Assign params to query options
        if ($queryParam == ""){
            $query = [];
        }
        else{
            $query = json_decode($queryParam);
            if ($query == null) {
                http_response_code(400);
                echo 'Bad request, malformed JSON query';
                exit();
            }
        }

        $data = $this->_repository->findDocs($id,$key,$query,(int)$limitParam,$sortTerms,$fields);

        //$metadata['query'] = json_encode($query);
        $metadata['messages'] = [];
        $metadata['sort'] = $sortTerms;
        $metadata['limit'] = $limitParam;
        $metadata['documentCount'] = count($data);

        if (!is_null($pageSizeParam)){
            $pageStart = ($pageParam - 1) * $pageSizeParam;
            $data = array_slice($data,$pageStart,$pageSizeParam);
            $metadata['page'] = $pageParam;
            $metadata['pageSize'] = (int)$pageSizeParam;
        }

        $logEntry['metadata'] = $metadata;

        //$this->_readLogger->info(json_encode($logEntry));

        return new JsonModel($this->_wrapMetadata($data, $metadata));
    }
}