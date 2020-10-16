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
                'schemas' => [
                    'catalogue' => [],
                    'embedded' => []
                ]
            ];
            $result['schemas']['catalogue'][] = $schemaId;
            //Write record back to dataset
            $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
            $response = 201;
        }
        else {
            //Add schema to schema list, if not already there
            if (in_array($schemaId, iterator_to_array($result['schemas']['catalogue']))) {
                $response = 200;
            }
            else {
                $result['schemas']['catalogue'][] = $schemaId;
                //Write record back to dataset
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
                $response = 201;
            }
        }
        return $response;
    }

    public function embedSchemaToDataset($embeddedSchemaId, $datasetId, $embeddedSchema, $auth) {
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

        //Check schema doesn't exist in catalogue with the same ID
        $data = $schemaCollection->findOne(['_id' => $embeddedSchemaId], []);
        if (!is_null($data)) {
            throw new \Exception("Schema ID already exists in public catalogue, please choose a different ID: ".$embeddedSchemaId);
        }

        $result = $metadataCollection->findOne(['_id' => $datasetId], []);
        if (is_null($result)){
            $result = [
                '_id' => $datasetId,
                'schemaValidation' => true,
                'schemas' => [
                    'catalogue' => [],
                    'embedded' => []
                ]
            ];
            $result['schemas']['embedded'][$embeddedSchemaId] = $this->_escapeDollars($embeddedSchema);
            //Write record back to dataset
            $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
            $response = 201;
        }
        else {
            //Add schema to schema list, if not already there
            if (array_key_exists($embeddedSchemaId, iterator_to_array($result['schemas']['embedded']))) {
                throw new \Exception("Embedded schema id already exists: ".$embeddedSchemaId);
            }
            else {
                $result['schemas']['embedded'][$embeddedSchemaId] = $this->_escapeDollars($embeddedSchema);
                //Write record back to dataset
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $result, ['upsert' => true]);
                $response = 201;
            }
        }

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

        //Retrieve dataset metadata (if exists), first
        $metadataResult = $metadataCollection->findOne(['_id' => $datasetId], []);
        if (is_null($metadataResult)){
            //No metadata record for this dataset and, hence, no schema associated with it. Do nothing
            $response = 200;
            return $response;
        }

        //Check schema exists
        $schemaFoundInCatalogue = false;
        $schemaFoundEmbedded = false;
        $data = $schemaCollection->findOne(['_id' => $schemaId], []);
        if (!is_null($data)) {
            $schemaFoundInCatalogue = true;
            if (in_array($schemaId, iterator_to_array($metadataResult['schemas']['catalogue']))) {
                //delete from array and write back to DB
                $newSchemasArray = iterator_to_array($metadataResult['schemas']['catalogue']);
                foreach (array_keys($newSchemasArray, $schemaId) as $key) {
                    unset($newSchemasArray[$key]);
                }
                $metadataResult['schemas']['catalogue'] = $newSchemasArray;
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $metadataResult, ['upsert' => true]);
                $response = 204;
            }
            else {
                //no schema in list. Do nothing
                $response = 200;
            }
        }
        else {
            if (array_key_exists($schemaId, iterator_to_array($metadataResult['schemas']['embedded']))) {
                $schemaFoundEmbedded = true;
                //delete from array and write back to DB
                //echo "found embedded schema.. ";
                $newEmbeddedArray = iterator_to_array($metadataResult['schemas']['embedded']);
                unset($newEmbeddedArray[$schemaId]);
                $metadataResult['schemas']['embedded'] = $newEmbeddedArray;
                //echo "writing back to DB... ";
                $insertOneResult = $metadataCollection->replaceOne(['_id' => $datasetId], $metadataResult, ['upsert' => true]);
                $response = 204;
            }
            else {
                //no schema in list. Do nothing
                $response = 200;
            }
        }

        return $response;

    }

    public function getValidationSchemas($datasetId) {
        $this->_connectDB($this->_config['mongodb']['adminUser'],$this->_config['mongodb']['adminPwd']);
        $md = $this->_config['metadata']['dataset'];
        $sd = $this->_schemaDataset;
        $metadataCollection = $this->_db->$md;
        $schemaCollection = $this->_db->$sd;

        //get metadata for dataset
        $result = $metadataCollection->findOne(['_id' => $datasetId], []);

        $schemasInCatalogue = true;
        if (is_null($result) || (iterator_to_array($result)['schemaValidation'] == false) || (sizeof(iterator_to_array($result)['schemas']['catalogue']) == 0)) {
            //no catalogue schemas found in metadata.
            //Check for embedded schemas
            $schemasInCatalogue = false;
            if (sizeof(iterator_to_array($result)['schemas']['embedded']) == 0) {
                //no embedded schemas found either. Return.
                return null;
            }
        }

        $schemas = [];

        //get schemas from the catalogue
        if ($schemasInCatalogue) {
            $schemaIdListCatalogue = iterator_to_array($result)['schemas']['catalogue'];
            $query = [
                '_id' => [
                    '$in' => $schemaIdListCatalogue
                ]
            ];
            $projection = [
                'schema_str' => 1,
                'schema' => 1,
            ];
            $schemasResult = $schemaCollection->find($query, ["projection" => $projection]);
            foreach ($schemasResult->toArray() as $item) {
                $schemas[] = $this->_cleanDollars($item);
            }
        }

        //get embedded schemas
        if (sizeof(iterator_to_array($result)['schemas']['embedded']) > 0) {
            foreach (iterator_to_array($result)['schemas']['embedded'] as $id => $embeddedSchema) {
                //wrap the schema in the same format that catalogue schemas are retrieved from the DB in, so they can be used interchangeably
                $wrapper = [
                    'id' => "$id",
                    'schema' => $this->_cleanDollars($embeddedSchema),
                    'schema_str' => json_encode($this->_cleanDollars($embeddedSchema))
                ];
                $schemas[] = $wrapper;
            }
        }
        return $schemas;
    }

}