<?php
/**
 * MAIN PROCESSOR FOR CRUD OPERATIONS ON MONGODB OBJECTS
 *
 * Created by: Jason Carvalho
 * Date: 2019-03-19
 * Time: 15:35
 */

use MongoDB\BSON\ObjectId;

require 'vendor/autoload.php'; // include Composer's autoloader
require 'apif_functions.php';
require 'MKSAPIF_CustomAPI.php';
$config = include('config.php');

function deleteObject($key, $uuid, $docID, $idType) {
    try {
        //db connection
        $client = new MongoDB\Client('mongodb://localhost:27017/datahub', [
            'username' => $key,
            'password' => $key,
            'db' => 'datahub'
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        switch($idType) {
            case 'id':
                //simple string ID passed
                $deleteResult = $collection->deleteOne(['_id' => $docID]);
                break;
            case 'oid':
                //oid passed, to be converted to Mongo ObjectID
                $newObj['_id'] = new MongoDB\BSON\ObjectId($docID);
                $deleteResult = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($docID)]);
                break;
            default:
                // Invalid Request Method
                http_response_code(400);
                print "Bad request, unrecognised id type (id/oid)";
                exit();
                break;
        }

        //$insertOneResult = $collection->insertOne(json_decode($annotated));
        if ($deleteResult->getDeletedCount() > 0){
            http_response_code(204);
        }
        else {
            http_response_code(200);
            print "No items to delete";
        }

    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error deleting object: ' . $ex->getMessage();
        exit();
    }
}

function updateObject($key, $uuid, $docID, $idType, $body) {
    if (!json_decode($body)) {
        http_response_code(400);
        print "Bad request, malformed JSON";
        exit();
    }
    $annotated = annotateObject($body, $uuid);
    $newObj = json_decode($annotated, true);
    $newObj['_updated'] = true;
    //Check if doc ID from URL matches that supplied in updated JSON
    /*
    if (isset($newOb['_id']) AND ($docID !=  $newObj['_id'])) {
        http_response_code(400);
        print "Bad request, document id specified in URI does not match id passed in JSON body";
        exit();
    }
    */


    try {
        //db connection
        $client = new MongoDB\Client('mongodb://localhost:27017/datahub', [
            'username' => $key,
            'password' => $key,
            'db' => 'datahub'
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        switch($idType) {
            case 'id':
                //simple string ID passed
                //FIXME - is this the best way?
                //Ignore any _id field in the JSON for now
                $newObj['_id'] = $docID;
                $replaceOneResult = $collection->replaceOne(['_id' => $docID],$newObj,['upsert' => true]);
                break;
            case 'oid':
                //oid passed, to be converted to Mongo ObjectID
                //FIXME - is this the best way?
                //Ignore any _id field in the JSON for now
                $newObj['_id'] = new MongoDB\BSON\ObjectId($docID);

                $replaceOneResult = $collection->replaceOne(['_id' => new MongoDB\BSON\ObjectId($docID)],$newObj,['upsert' => true]);
                break;
            default:
                // Invalid Request Method
                http_response_code(400);
                print "Bad request, unrecognised id type (id/oid)";
                exit();
                break;
        }

        if ($replaceOneResult->getModifiedCount() > 0){
            http_response_code(204);
        }
        else {
            http_response_code(201);
            print "Object created";
        }
    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error creating updating object: ' . $ex->getMessage();
        exit();
    }
}


function createObject($key, $uuid, $body)
{
    if (!json_decode($body)) {
        http_response_code(400);
        print "Bad request, malformed JSON";
        exit();
    }
    $annotated = annotateObject($body, $uuid);

    try {
        //db connection
        $client = new MongoDB\Client('mongodb://localhost:27017/datahub', [
            'username' => $key,
            'password' => $key,
            'db' => 'datahub'
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        $insertOneResult = $collection->insertOne(json_decode($annotated));
        http_response_code(201);
        print "Object created";
    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error creating object: ' . $ex->getMessage();
        exit();
    }

}

function archiveContent ($archiveKey, $archiveDataset, $body, $apiID, $endpoint, $parentDataset) {
    //First, remove '_id' so that Mongo creates a unique entry, we want to keep all PUTs/POSTs and not
    //overwrite anything
    $annotated = annotateObject($body, $parentDataset);
    $document = json_decode($annotated, true);
    unset($document['_id']);

    //Annotate with some extra CustomAPI info
    $document['_apiID'] = $apiID;
    $document['_endpoint'] = $endpoint;

    //Now push to MongoDB
    try {
        //db connection
        $client = new MongoDB\Client('mongodb://localhost:27017/datahub', [
            'username' => $archiveKey,
            'password' => $archiveKey,
            'db' => 'datahub'
        ]);
        $db = $client->datahub;
        $collection = $db->$archiveDataset;

        $insertOneResult = $collection->insertOne($document);
        return true;
        //http_response_code(201);
        //print "Object created";
    } catch (Exception $ex) {
        return false;
        //http_response_code(500);
        //echo 'Fatal error creating object: ' . $ex->getMessage();
        //exit();
    }

}

/**
 *  END OF FUNCTIONS. MAIN CODE BELOW
 */

$request_method=$_SERVER["REQUEST_METHOD"];
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Dataset key must be provided as HTTP Basic Auth username';
    exit;
} else {
    //echo "<p>Hello {$_SERVER['PHP_AUTH_USER']}.</p>";
    //echo "<p>You entered {$_SERVER['PHP_AUTH_PW']} as your password.</p>";
    $key = $_SERVER['PHP_AUTH_USER'];
}

//get PUT/POST data
$payload = file_get_contents("php://input");

/*
 * Establish whether this is a basic APIF call, or is using a custom API
 */
if (isset($_GET["customAPI"])) {
    $apiID = $_GET["customAPI"];
    if (isset($_GET["endpoint"])) {
        $endpoint = $_GET["endpoint"];
    } else {
        http_response_code(400);
        print "Bad request, custom API endpoint not specified";
        exit();
    }
    if (isset($_GET["id"])) {
        $docID = $_GET["id"];
    } else {
        //FIXME - For now (SciRoc), we always need an ID, eventually needs and option to omit this and generate auto IDs
        //$docID = null;
        http_response_code(400);
        print "Bad request, ID not specified";
        exit();
    }

    //Create customAPI object...
    $customApi = new MKSAPIF_CustomAPI($apiID,$endpoint,$docID);

    $uuid = $customApi->getDataset();
    $mongoID = $customApi->generateID();

    if (($request_method == 'PUT') Or ($request_method == 'POST')) {
        //We only need to do JSON schema validation for PUTs and POSTs
        $validationErrors = $customApi->checkValidationErrors($payload);
        if ($validationErrors) {
            //payload schema validation FAILED
            http_response_code(400);
            print ($validationErrors);
            exit();
        }
        else {
            $payload = $customApi->processJSON($payload);
            /**
             * DUMP THE DATA TO A SHADOW DB HERE
             */
            //FIXME - This is the SciRoc archive dataset. May make this configurable in future...
            //Development
            $archiveDataset = "1b2839ff-e404-401d-9c69-b6280216744e";
            $archiveKey = "a3638075-aaf7-4701-a5b3-f5dd6c8e2e36";
            //Production
            //$archiveDataset = "6b798b30-0e31-46ac-9b9a-9d1c41d4cae6";
            //$archiveKey = "a37e5b41-1511-4162-b5b3-be43fbd9a717";
            archiveContent($archiveKey, $archiveDataset, $payload, $apiID, $endpoint, $uuid);
        }
    }



}
else {
    if (!isset($_GET["uuid"])) {
        http_response_code(400);
        print "Bad request, dataset uuid not specified";
        exit();
    }
    $uuid = $_GET["uuid"];

    if (isset($_GET["id"]) AND isset($_GET["idType"])) {
        $mongoID = $_GET["id"];
    }
}

/**
 *
 * API USAGE:
 * PUT - THIS SHOULD BE USED FOR PUSHING DATA INTO THE SYSTEM - BOTH NEW DATA AND DATA UPDATES.
 *
 *
 */

switch($request_method)
{
    case 'PUT':
        if (isset($_GET["id"]) AND isset($_GET["idType"])) {
            updateObject($key, $uuid, $mongoID, $_GET["idType"], $payload);
        }
        else {
            createObject($key, $uuid, $payload);
        }
        break;
    case 'POST':
        if (isset($_GET["id"]) AND isset($_GET["idType"])) {
            updateObject($key, $uuid, $mongoID, $_GET["idType"], $payload);
        }
        else {
            createObject($key, $uuid, $payload);
        }
        break;
    case 'DELETE':
        if (isset($_GET["id"]) AND isset($_GET["idType"])) {
            deleteObject($key, $uuid, $mongoID, $_GET["idType"]);
        }
        else {
            http_response_code(400);
            print "Bad request, document id or id type (id/oid) missing";
            exit();
        }
        break;
    default:
        // Invalid Request Method
        header("HTTP/1.0 405 Method Not Allowed");
        break;
}

?>