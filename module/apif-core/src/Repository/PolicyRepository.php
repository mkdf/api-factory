<?php

namespace APIF\Core\Repository;

class PolicyRepository implements PolicyRepositoryInterface
{
    private $_config;
    private $_dataset;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_dataset = $this->_config['policy']['dataset'];
    }

    public function getLicenses($datasetUuid, $key) {
        $licenses = [
            'dataset' => [],
            'json' => [],
            'file' => []
        ];
        // - Get single doc - dataset metadata
        // - Get policy attribute, if exists
        // - get license assigned to this key from ['policy']['keys'] association table, if exists
        // - get json-doc licenses if exist
        // - get file licenses if exist

        return $licenses;
    }

    public function getJSONLicenses($datasetUuid, $key, $docId) {
        $licenses = [
            'dataset' => [],
            'json' => [],
            'file' => []
        ];
        // - Get single doc - dataset metadata
        // - Get policy attribute, if exists
        // - get license assigned to this key from ['policy']['keys'] association table, if exists
        // - get json-doc licenses if exist
        // - get file licenses if exist

        return $licenses;
    }

    public function getFileLicenses($datasetUuid, $key, $fileId) {
        $licenses = [
            'dataset' => [],
            'json' => [],
            'file' => []
        ];
        // - Get single doc - dataset metadata
        // - Get policy attribute, if exists
        // - get license assigned to this key from ['policy']['keys'] association table, if exists
        // - get json-doc licenses if exist
        // - get file licenses if exist

        return $licenses;
    }

}