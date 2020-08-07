<?php


namespace APIF\Core\Repository;

use  MongoDB\Client;


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
        } catch (Exception $ex) {
            http_response_code(500);
            echo 'Fatal error connecting to MongoDB: ' . $ex->getMessage();
            exit();
        }
    }

    public function findDocs($datasetId, $key, $query) {
        $this->_connectDB($key);
        $collection = $this->_db->$datasetId;
        $data = [];
        $result = $collection->find($query, $this->_queryOptions);
        $data = $result->toArray();
        return $data;
    }

    public function insertDoc($datasetId, $object, $key) {
        $this->_connectDB($key);
        $collection = $this->_db->$datasetId;
        $insertOneResult = $collection->insertOne($object);
        return $object;
    }

    public function updateDoc($datasetId, $docID, $object, $key) {
        $this->_connectDB($key);
        $collection = $this->_db->$datasetId;
        $replaceOneResult = $collection->replaceOne(['_id' => $docID], $object, ['upsert' => true]);
        if ($replaceOneResult->getModifiedCount() > 0) {
            return ("UPDATED");
        } else {
            return ("CREATED");
        }
    }

    public function deleteDoc($datasetId, $docID, $key) {
        $this->_connectDB($key);
        $collection = $this->_db->$datasetId;
        $deleteResult = $collection->deleteOne(['_id' => $docID]);

        if ($deleteResult->getDeletedCount() > 0) {
            return ("DELETED");
        } else {
            return null;
        }
    }

    public function getDatasetList($auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
        $collectionArray = array();
        foreach ($this->_db->listCollections() as $collectionInfo) {
            array_push($collectionArray, $collectionInfo["name"]);
        }
        return $collectionArray;
    }

    public function getDataset($id,$auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);
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
        return $summary;
    }

    public function createDataset($datasetID, $auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        //Create dataset
        try {
            $result = $this->_db->createCollection($datasetID, []);
        }
        catch (Exception $ex) {
            //Most likely error here is that the collection already exists
            http_response_code(400);
            echo 'Fatal error creating MongoDB collection: ' .$ex->getMessage();
            exit();
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
        $result = $this->_db->command([
            "usersInfo" => $key
        ]);
        foreach ($result as $userInfo) {
            array_push($userArray,$userInfo);
        }

        return $userArray;
    }

    public function getAllKeys ($auth) {
        $this->_connectDB($auth['user'],$auth['pwd']);

        $userArray = array();
        $result = $this->_db->command([
            "usersInfo" => 1
        ]);
        foreach ($result as $userInfo) {
            array_push($userArray,$userInfo);
        }

        return $userArray;
    }

}