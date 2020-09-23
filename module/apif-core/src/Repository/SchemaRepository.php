<?php


namespace APIF\Core\Repository;

use  MongoDB\Client;

class SchemaRepository implements SchemaRepositoryInterface
{
    private $_config;
    private $_client;
    private $_db;
    private $_queryLimit;
    private $_queryOptions;
    private $_adminUser;
    private $_adminPassword;
    private $_schemaDataset;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_adminUser = $this->_config['mongodb']['adminUser'];
        $this->_adminPassword = $this->_config['mongodb']['adminPwd'];
        $this->_schemaDataset = $this->_config['schema']['dataset'];
    }

    private function _connectDB($user, $pwd = 'none') {
        $DBCONNECTIONSTRING = 'mongodb://'.$this->_config['mongodb']['host'].':'.$this->_config['mongodb']['port'].'/'.$this->_config['mongodb']['database'];
        $DBNAME = $this->_config['mongodb']['database'];
        $this->_queryLimit = $this->_config['mongodb']['queryLimit'];
        $this->_queryOptions = [
            'limit' => $this->_queryLimit,
            'sort' => [
                '_timestamp' => -1
            ],
        ];

        if ($pwd == 'none') {
            $pwd = $user;
        }

        try {
            //db connection
            $this->_client = new Client($DBCONNECTIONSTRING, [
                'username' => $user,
                'password' => $pwd,
                'db' => $DBNAME
            ]);
            $this->_db = $this->_client->$DBNAME;
        } catch (\Throwable $ex) {
            http_response_code(500);
            echo 'Fatal error connecting to MongoDB: ' . $ex->getMessage();
            exit();
        }
    }

    public function findSchemas($query = [], $limit = null, $sort = null) {
        $this->_connectDB($this->_adminUser, $this->_adminPassword);
        $sd = $this->_schemaDataset;
        $collection = $this->_db->$sd;
        $data = [];
        if (!is_null($limit)){
            $this->_queryOptions['limit'] = $limit;
        }
        if (!is_null($sort)){
            $this->_queryOptions['sort'] = array_merge($sort,$this->_queryOptions['sort']);
        }

        try {
            $result = $collection->find($query, $this->_queryOptions);
            $data = $result->toArray();
            return $data;
        }
        catch (\Throwable $ex) {
            http_response_code(500);
            echo 'Fatal error retrieving documents: ' . $ex->getMessage();
            exit();
        }
    }

}