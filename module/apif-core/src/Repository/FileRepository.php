<?php


namespace APIF\Core\Repository;


class FileRepository implements FileRepositoryInterface
{
    private $_config;
    private $_uploadDestination;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_uploadDestination = $this->_config['mkdf-file']['destination'];
    }

}