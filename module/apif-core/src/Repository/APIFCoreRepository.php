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

    private function _connectDB($accessKey) {
        $DBCONNECTIONSTRING = 'mongodb://'.$this->_config['mongodb']['host'].':'.$this->_config['mongodb']['port'].'/'.$this->_config['mongodb']['database'];
        $DBNAME = $this->_config['mongodb']['database'];
        $this->_queryLimit = $this->_config['mongodb']['queryLimit'];
        $this->_queryOptions = [
            'limit' => $this->_queryLimit,
            'sort' => [
                '_timestamp' => -1
            ],
        ];

        try {
            //db connection
            $this->_client = new Client($DBCONNECTIONSTRING, [
                'username' => $accessKey,
                'password' => $accessKey,
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

}