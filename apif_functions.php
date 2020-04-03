<?php

function annotateObject($input, $uuid){
    /*
     * Decode JSON, add extra metadata, re-encode and return new annotated JSON
     * Also add an _id field if one hasn't been submitted
     */
    $object = json_decode($input, true);

    $timestamp = time();
    //echo date("d/m/Y H:i:s",$timestamp);

    //if no _id supplied, generate a string version of a Mongo ObjectID
    if (!array_key_exists('_id',$object)){
        $OID = new MongoDB\BSON\ObjectId();
        $idString = (string)$OID;
        $object['_id'] = $idString;
    }
    //convert _id to string if necessary
    $object['_id'] = (string)$object['_id'];

    $object['_datasetid'] = $uuid;
    $object['_timestamp'] = $timestamp;

    #explode timestamp and add additional attributes for year, month, dat, hour, second.
    $object['_timestamp_year'] = (int)date("Y",$timestamp);
    $object['_timestamp_month'] = (int)date("m",$timestamp);
    $object['_timestamp_day'] = (int)date("d",$timestamp);
    $object['_timestamp_hour'] = (int)date("H",$timestamp);
    $object['_timestamp_minute'] = (int)date("i",$timestamp);
    $object['_timestamp_second'] = (int)date("s",$timestamp);

    return json_encode($object);
}


?>