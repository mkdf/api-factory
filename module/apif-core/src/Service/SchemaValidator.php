<?php


namespace APIF\Core\Service;


use Swaggest\JsonSchema\Schema;

class SchemaValidator implements SchemaValidatorInterface
{
    private $_config;

    public function __construct($config)
    {
        $this->_config = $config;
    }

    public function validate($object, $schemaList) {
        $validationMatch = false;
        $validationErrors = [];
        $objJson = json_encode($object);
        foreach ($schemaList as $currentSchema) {
            //$urlPrefix = ($_SERVER['HTTPS']) ? "https://" : "http://";
            //$localURI = $urlPrefix . $_SERVER['SERVER_NAME'] . "/schemas/" . $currentSchema['schema']['$id'] . ".json";
            //$currentSchema['schema']['$id'] = $localURI;
            $schemaJson = json_encode($currentSchema['schema']);
            $schema = Schema::import(json_decode($schemaJson));

            try {
                if ($schema->in(json_decode($objJson))) {
                    $validationMatch = true;
                }
            }
            catch (\Throwable $ex) {
                $error = [
                    'schemaId' => $currentSchema['schema']['$id'],
                    'error' => $ex->getMessage()
                ];
                $validationErrors[] = $error;
                //throw ($ex);
            }
        }//end foreach
        if ($validationMatch) {
            return true;
        }
        else {
            throw new \Exception(json_encode($validationErrors));
        }
    }

}