<?php

function annotateObject($input, $uuid){
    /*
     * Decode JSON, add extra metadata, re-encode and return new annotated JSON
     */
    $object = json_decode($input, true);

    $timestamp = time();
    //echo date("d/m/Y H:i:s",$timestamp);

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