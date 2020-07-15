<?php


namespace APIF\Core\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;

class QueryController extends AbstractRestfulController
{
    private $_config;

    public function __construct(array $config)
    {
        $this->_config = $config;
    }

    public function get($id) {
        $data = [
            'id' => $id,
            'name' => 'jason',
            'age' => 42
        ];

        return new JsonModel(['data' => $data]);
    }

    public function getList() {
        $data = [
            'name' => 'jason',
            'age' => 42
        ];

        return new JsonModel($data);
    }
}