<?php


namespace APIF\Core\Controller;


use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class BrowseController extends AbstractRestfulController
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

    private function _wrapMetadata ($input) {
        $output = $input;
        return $output;
    }

    /*
 * GET - Handling a GET request
 * brings back all docs from a dataset (subject to limit), or a query
 * if query param is provided
 */
    public function get($id) {
        $key = $this->_getAuth()['user'];

        //Get URL params
        $queryParam = $this->params()->fromQuery('query', "");
        $limitParam = $this->params()->fromQuery('limit', null);
        $sortParam = $this->params()->fromQuery('sort', null);
        $pageParam = $this->params()->fromQuery('page', 1);
        $pageSizeParam = $this->params()->fromQuery('pagesize', null);

        //Sorting
        $sortTerms = [];
        if (!is_null($sortParam)){
            $exploded = explode(",",$sortParam);
            foreach ($exploded as $term){
                if (substr($term,0,1) == "-") {
                    $sortTerms[$term] = -1;
                }
                else {
                    $sortTerms[$term] = 1;
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
        $data = $this->_repository->findDocs($id,$key,$query,(int)$limitParam,$sortTerms);

        //TODO - Apply pagination here
        if (!is_null($pageSizeParam)){
            $pageStart = ($pageParam - 1) * $pageSizeParam;
            $data = array_slice($data,$pageStart,$pageSizeParam);
        }
        return new JsonModel($this->_wrapMetadata($data));
    }
}