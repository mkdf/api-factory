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

    public function retrieveSimpleSchema($id) {
        $this->_connectDB($this->_config['mongodb']['adminUser'],$this->_config['mongodb']['adminPwd']);
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
            return $data[0]['schema'];
        }
        catch (\Throwable $ex) {
            throw ($ex);
        }
    }


    public function createSchema($schemaEntry, $auth) {
        try {
            $this->_connectDB($auth['user'],$auth['pwd']);
            $sd = $this->_schemaDataset;
            $collection = $this->_db->$sd;
            $escaped = $this->_escapeDollars($schemaEntry);
            $insertOneResult = $collection->insertOne($escaped);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        return $escaped;
    }

    public function updateSchema($id, $annotated, $auth) {
        try {
            $this->_connectDB($auth['user'],$auth['pwd']);
            $sd = $this->_schemaDataset;
            $collection = $this->_db->$sd;
            $escaped = $this->_escapeDollars($annotated);
            $updateOneResult = $collection->replaceOne(['_id' => $id], $escaped, ['upsert' => true]);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        return $escaped;
    }

    public function assignSchemaToDataset($schemaId, $datasetId, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $md = $this->_config['metadata']['dataset'];
        $sd = $this->_schemaDataset;
        $metadataCollection = $this->_db->$md;
        $schemaCollection = $this->_db->$sd;

        //Check dataset exists
        $data = $this->_db->listCollections([
            'filter' => [
                'name' => $datasetId,
            ],
        ]);
        if (iterator_count($data) == 0) {
            throw new \Exception("No such dataset: ".$datasetId);
        }

        //Check schema exists
        $data = $schemaCollection->findOne(['_id' => $schemaId], []);
        if (is_null($data)) {
            throw new \Exception("No such schema: ".$schemaId);
        }

        //Retrieve dataset metadata (if exists), first
        $result = $metadataCollection->findOne(['_id' => $datasetId], []);
        if (is_null($result)){
            $result = [
                '_id' => $datasetId,
                'schemaValidation' => true,
                'schemas' => []
            ];
            $result['schemas'][] = $schemaId;
            //Write record back to dataset
            $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
            $response = 201;
        }
        else {
            //Add schema to schema list, if not already there
            if (in_array($schemaId, iterator_to_array($result['schemas']))) {
                $response = 200;
            }
            else {
                $result['schemas'][] = $schemaId;
                //Write record back to dataset
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
                $response = 201;
            }
        }
        return $response;
    }

    public function deleteSchemaFromDataset($schemaId, $datasetId, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $md = $this->_config['metadata']['dataset'];
        $sd = $this->_schemaDataset;
        $metadataCollection = $this->_db->$md;
        $schemaCollection = $this->_db->$sd;

        //Check dataset exists
        $data = $this->_db->listCollections([
            'filter' => [
                'name' => $datasetId,
            ],
        ]);
        if (iterator_count($data) == 0) {
            throw new \Exception("No such dataset: ".$datasetId);
        }

        //Check schema exists
        $data = $schemaCollection->findOne(['_id' => $schemaId], []);
        if (is_null($data)) {
            throw new \Exception("No such schema: ".$schemaId);
        }

        //Retrieve dataset metadata (if exists), first
        $result = $metadataCollection->findOne(['_id' => $datasetId], []);
        if (is_null($result)){
            //No metadata record for this dataset and, hence, no schema associated with it. Do nothing
            $response = 200;
        }
        else {
            //Check if schema is associated with dataset
            if (in_array($schemaId, iterator_to_array($result['schemas']))) {
                //delete from array and write back to DB
                $newSchemasArray = iterator_to_array($result['schemas']);
                foreach (array_keys($newSchemasArray, $schemaId) as $key) {
                    unset($newSchemasArray[$key]);
                }
                $result['schemas'] = $newSchemasArray;
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
                $response = 204;
            }
            else {
                //no schema in list. Do nothing
                $response = 200;
            }
        }
        return $response;

    }

}