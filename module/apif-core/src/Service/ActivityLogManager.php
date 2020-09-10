<?php


namespace APIF\Core\Service;


use MongoDB\Client;

class ActivityLogManager implements ActivityLogManagerInterface
{
    private $_config;
    private $_db;
    private $_client;
    private $_logDataset;
    private $_logKey;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_logDataset = $this->_config['activityLog']['dataset'];
        $this->_logKey = $this->_config['activityLog']['key'];
    }

    private function _connectDB() {
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
                'username' => $this->_logKey,
                'password' => $this->_logKey,
                'db' => $DBNAME
            ]);
            $this->_db = $this->_client->$DBNAME;
        } catch (\Throwable $ex) {
            http_response_code(500);
            echo 'Fatal error connecting to MongoDB: ' . $ex->getMessage();
            exit();
        }
    }

    private function _loggingInitialised() {
        return true;
    }

    private function _initialiseLogging() {
        return true;
    }

    public function logActivity($data) {
        if ($this->_config['activityLog']['enabled']) {
            if (!$this->_loggingInitialised()) {
                $this->_initialiseLogging();
            }
            $this->_connectDB();
            $activityLog = $this->_logDataset;
            $collection = $this->_db->$activityLog;
            try {
                $insertOneResult = $collection->insertOne($data);
            }
            catch (\Throwable $ex) {
                http_response_code(500);
                echo 'Fatal error inserting document: ' . $ex->getMessage();
                exit();
            }
            return true;
        }
        else {
            //logging not enabled
            return false;
        }
    }

}