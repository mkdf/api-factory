<?php


namespace APIF\Core\Repository;


class FileRepository implements FileRepositoryInterface
{
    private $_config;
    private $_uploadDestination;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_uploadDestination = $this->_config['file']['destination'];
    }

    public function getFileLocation($fileData, $datasetID) {
        $destination = $this->_uploadDestination . $datasetID . "/";
        $fullPath = $destination . $fileData['filename'];
        return $fullPath;
    }

    public function writeFile($fileData, $datasetID) {
        try {
            $destination = $this->_uploadDestination . $datasetID . "/";
            if (!file_exists($destination)) {
                mkdir($destination, 0777, true);
            }
            $filename = basename($fileData['tmp_name']);
            rename ($fileData['tmp_name'],$destination.$filename);
            return true;
        }
        catch (\Throwable $ex) {
            return false;
            //throw $ex;
        }

    }

    public function deleteFile($filename, $datasetID){
        //FIXME - need a function body here
        return true;
    }

}