<?php


namespace APIF\Core\Repository;

use  MongoDB\Client;
use MongoDB\Driver\Exception\AuthenticationException;

class APIFCoreRepository implements APIFCoreRepositoryInterface
{
    private $_config;
    private $_client;
    private $_db;
    private $_queryLimit;
    private $_queryOptions;

    public function __construct($config)
    {
        $this->_config = $config;
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
            throw $ex;
        }
    }

    public function checkReadAccess($dataset, $key, $pwd) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$dataset;
        $this->_queryOptions['limit'] = 1;
        try {
            $result = $collection->find([], $this->_queryOptions);
            //$data = $result->toArray();
            return true;
        }
        catch (\Throwable $ex) {
            return false;
            //throw $ex;
        }
    }

    public function checkWriteAccess($dataset, $key, $pwd) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$dataset;
        $this->_queryOptions['limit'] = 1;
        $id = "write-test-".strval(rand(1000000000,9999999999));
        $data = [
            "_id" => $id
        ];
        try {
            $insertOneResult = $collection->insertOne($data);
            $deleteResult = $collection->deleteOne(['_id' => $id]);
            return true;
        }
        catch (\Throwable $ex) {
            return false;
            //throw $ex;
        }
    }

    public function findDocs($datasetId, $key, $pwd, $query, $limit = null, $sort = null ,$projection = null) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        $data = [];
        if (!is_null($limit)){
            $this->_queryOptions['limit'] = $limit;
        }
        if (!is_null($sort)){
            $this->_queryOptions['sort'] = array_merge($sort,$this->_queryOptions['sort']);
        }
        if (!is_null($projection)){
            $this->_queryOptions['projection'] = $projection;
        }
        try {
            $result = $collection->find($query, $this->_queryOptions);
            $data = $result->toArray();
            return $data;
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
    }

    public function findDocsPaged($datasetId, $key, $pwd, $query, $limit = null, $sort = null, $projection = null, $page = 1) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        $data = [];

        if (!is_null($limit)){
            $this->_queryOptions['limit'] = $limit;
        }
        else {
            $this->_queryOptions['limit'] = $this->_config['mongodb']['queryLimit'];
        }

        $this->_queryOptions['skip'] = $this->_queryOptions['limit'] * ($page - 1);

        if (!is_null($sort)){
            $this->_queryOptions['sort'] = array_merge($sort,$this->_queryOptions['sort']);
        }
        if (!is_null($projection)){
            $this->_queryOptions['projection'] = $projection;
        }
        try {
            //print_r($this->_queryOptions);
            $result = $collection->find($query, $this->_queryOptions);
            $data = $result->toArray();
            return $data;
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
    }

    public function getSingleDoc ($datasetId, $key, $pwd, $docID) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        $query = [
            "_id" => strval($docID)
        ];
        $data = [];
        try {
            $result = $collection->find($query,[]);
            $data = $result->toArray();
            return $data;
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
    }

    public function insertDoc($datasetId, $object, $key, $pwd) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        try {
            $insertOneResult = $collection->insertOne($object);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        return $object;
    }

    public function updateDoc($datasetId, $docID, $object, $key, $pwd) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        try {
            $replaceOneResult = $collection->replaceOne(['_id' => $docID], $object, ['upsert' => true]);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        if ($replaceOneResult->getModifiedCount() > 0) {
            return ("UPDATED");
        } else {
            return ("CREATED");
        }
    }

    public function deleteDoc($datasetId, $docID, $key, $pwd) {
        $this->_connectDB($key, $pwd);
        $collection = $this->_db->$datasetId;
        try {
            $deleteResult = $collection->deleteOne(['_id' => $docID]);
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        if ($deleteResult->getDeletedCount() > 0) {
            return ("DELETED");
        } else {
            return null;
        }
    }

    public function getDatasetList($auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $collectionArray = array();
        try {
            foreach ($this->_db->listCollections() as $collectionInfo) {
                array_push($collectionArray, $collectionInfo["name"]);
            }
        }
        catch (\Throwable $ex) {
            throw $ex;
        }

        return $collectionArray;
    }

    public function getDataset($id,$auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        try {
            $data = $this->_db->listCollections([
                'filter' => [
                    'name' => $id,
                ],
            ]);
            $exist = 0;
            foreach ($data as $collectionInfo) {
                $exist = 1;
            }
            $summary = [];
            if ($exist) {
                $total_docs = $this->_db->$id->estimatedDocumentCount();
                $summary = array();
                $summary['datasetID'] = $id;
                $summary['totalDocs'] = $total_docs;
            }
        }
        catch (\Throwable $ex) {
            throw $ex;
        }
        return $summary;
    }

    public function createDataset($datasetID, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        //Create dataset
        try {
            $result = $this->_db->createCollection($datasetID, []);
        }
        catch (\Throwable $ex) {
            //Most likely error here is that the collection already exists
            throw $ex;
        }

        //Create index(es)

        //Create read role for dataset...

        //check if read role exists exists:
        $cursor = $this->_db->command([
            'rolesInfo' => [
                'role' => $datasetID . "-R",
                'db' => $this->_config['mongodb']['database']
            ]
        ]);
        $cursorItem = $cursor->toArray()[0];
        if (sizeof($cursorItem['roles']) > 0) {
            //role already exists. No need to create it
        }
        else {
            //role doesn't exist, create it
            $result = $this->_db->command([
                'createRole' => $datasetID . "-R",
                'privileges' => [
                    [
                        'resource' => [
                            'db' => $this->_config['mongodb']['database'],
                            'collection' => $datasetID
                        ],
                        'actions' => [ "find" ]
                    ]
                ],
                'roles' => []
            ]);
        }

        //Create write role for dataset...

        //check if read role exists exists:
        $cursor = $this->_db->command([
            'rolesInfo' => [
                'role' => $datasetID . "-W",
                'db' => $this->_config['mongodb']['database']
            ]
        ]);
        $cursorItem = $cursor->toArray()[0];
        if (sizeof($cursorItem['roles']) > 0) {
            //role already exists. No need to create it
        }
        else {
            //role doesn't exist, create it
            $result = $this->_db->command([
                'createRole' => $datasetID . "-W",
                'privileges' => [
                    [
                        'resource' => [
                            'db' => $this->_config['mongodb']['database'],
                            'collection' => $datasetID
                        ],
                        'actions' => [ "update", "insert", "remove" ]
                    ]
                ],
                'roles' => []
            ]);
        }
        return true;
    }

    public function setKeyPermissions($key, $datasetID, $readAccess, $writeAccess, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        //check if user exists:
        $cursor = $this->_db->command([
            'usersInfo' => [
                'user' => $key,
                'db' => $this->_config['mongodb']['database']
            ]
        ]);

        //if user(key) not exist, create it
        $cursorItem = $cursor->toArray()[0];
        if (sizeof($cursorItem['users']) == 0) {
            //user doesn't exist, create it
            $result = $this->_db->command([
                'createUser' => $key,
                'pwd' => $key,
                'roles' => []
            ]);
        }

        //Assign roles
        $readRole = $datasetID . "-R";
        $writeRole = $datasetID . "-W";

        //if read access:
        if ($readAccess) {
            $result = $this->_db->command([
                'grantRolesToUser' => $key,
                'roles' => [$readRole]
            ]);
        }
        else {
            //remove read permissions
            $result = $this->_db->command([
                'revokeRolesFromUser' => $key,
                'roles' => [$readRole]
            ]);
        }

        //if write access:
        if ($writeAccess) {
            $result = $this->_db->command([
                'grantRolesToUser' => $key,
                'roles' => [$writeRole]
                //'db' => 'datahub'
            ]);
        }
        else {
            //remove write permissions
            $result = $this->_db->command([
                'revokeRolesFromUser' => $key,
                'roles' => [$writeRole]
                //'db' => 'datahub'
            ]);
        }

        return true;
    }

    public function getKey ($key, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        $userArray = array();
        try {
            $result = $this->_db->command([
                "usersInfo" => $key
            ]);
            foreach ($result as $userInfo) {
                array_push($userArray,$userInfo);
            }
        }
        catch (\Throwable $ex) {
            //Most likely error here is that the collection already exists
            throw $ex;
        }

        return $userArray;
    }

    public function getAllKeys ($auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        $userArray = array();
        try {
            $result = $this->_db->command([
                "usersInfo" => 1
            ]);
            foreach ($result as $userInfo) {
                array_push($userArray,$userInfo);
            }
        }
        catch (\Throwable $ex) {
            //Most likely error here is that the collection already exists
            throw $ex;
        }

        return $userArray;
    }

    /*
      * **********************
      * FILE HANDLING METADATA
      * **********************
    */

    public function writeFileMetadata($metaItem, $datasetID){
        $metadataCollection = $this->_config['metadata']['dataset'];
        $md = $this->_db->$metadataCollection;
        $this->_connectDB($this->_config['mongodb']['adminUser'],$this->_config['mongodb']['adminPwd']);

        //Check dataset exists
        $data = $this->_db->listCollections([
            'filter' => [
                'name' => $datasetID,
            ],
        ]);
        if (iterator_count($data) == 0) {
            throw new \Exception("No such dataset: ".$datasetID);
        }

        //file metadata entry id:
        $mdID = $metaItem['filenameOriginal']."-".$metaItem['filename'];
        $mdID = str_replace(".","",$mdID);
        //Retrieve dataset metadata (if exists), first
        $result = $md->findOne(['_id' => $datasetID], []);
        if (is_null($result)){
            $result = [
                '_id' => $datasetID,
                'files' => [
                    $mdID => $metaItem
                ]
            ];
            //Write record back to dataset
            $insertOneResult = $md->replaceOne(['_id' => $datasetID], $result, ['upsert' => true]);
            $response = 201;
        }
        else {
            //Add entry to file list, if not already there
            if (array_key_exists($mdID, $result['schemas']['files'])) {
                //it's already there, overwrite it
                $result['schemas']['files'][$mdID] = $metaItem;
                $insertOneResult = $md->replaceOne(['_id' => $datasetID], $result, ['upsert' => true]);
                $response = 204;
            }
            else {
                $result['schemas']['files'][$mdID] = $metaItem;
                //Write record back to dataset
                $insertOneResult = $md->replaceOne(['_id' => $datasetID], $result, ['upsert' => true]);
                $response = 201;
            }
        }
        return $response;

    }

}