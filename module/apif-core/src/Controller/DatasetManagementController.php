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

    public function getList()
    {
        return new JsonModel(['message' => 'get dataset list']);
    }

    public function get($id)
    {
        return new JsonModel(['message' => 'get dataset item']);
    }

    public function create($data) {

    }

    public function update($id, $data) {

    }

    public function delete($id) {

    }
}