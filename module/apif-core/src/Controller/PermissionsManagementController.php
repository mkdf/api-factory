<?php


namespace APIF\Core\Controller;

use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;


class PermissionsManagementController extends AbstractRestfulController
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
        return new JsonModel(['message' => 'get permissions list']);
    }

    public function get($id)
    {
        return new JsonModel(['message' => 'get permissions item']);
    }

    public function create($data) {

    }

    public function update($id, $data) {

    }

    public function delete($id) {

    }
}