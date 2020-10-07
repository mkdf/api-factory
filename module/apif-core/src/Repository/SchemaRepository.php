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
            throw ($ex);
        }
    }

    //Remove double underscore from $ symbols
    private function _cleanDollars($input) {
        $inputJson = json_encode($input);
        return json_decode(str_replace('__$','$',$inputJson),true);
    }

    //Add double underscore to $ symbols
    private function _escapeDollars($input) {
        $inputJson = json_encode($input);
        return json_decode(str_replace('$', '__$', $inputJson),true);
    }

    public function findSchemas($auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $sd = $this->_schemaDataset;
        $collection = $this->_db->$sd;
        $data = [];
        $query = [];
        try {
            $result = $collection->find($query, $this->_queryOptions);
            $data = $result->toArray();
            foreach ($data as &$item) {
                $item = $this->_cleanDollars($item);
            }
            return $data;
        }
        catch (\Throwable $ex) {
            throw ($ex);
        }
    }

    public function findSingleSchemaDetails($id,$auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $sd = $this->_schemaDataset;
        $collection = $this->_db->$sd;
        $query = [
            "_id" => strval($id)
        ];
        try {
            $result = $collection->find($query, []);
            $data = $result->toArray();
            foreach ($data as &$item) {
                $item = $this->_cleanDollars($item);
            }
            return $data;
        }
        catch (\Throwable $ex) {
            throw ($ex);
        }
    }

    public function createSchema($schemaEntry, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $sd = $this->_schemaDataset;
        $collection = $this->_db->$sd;
        $escaped = $this->_escapeDollars($schemaEntry);
        try {
            $insertOneResult = $collection->insertOne($escaped);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        return $escaped;
    }

}