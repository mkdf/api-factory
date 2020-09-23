<?php


namespace APIF\Core\Service;


class SchemaValidator implements SchemaValidatorInterface
{
    private $_config;

    public function __construct($config)
    {
        $this->_config = $config;
    }

    public function validate() {

    }

}