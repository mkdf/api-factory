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
$config = include('config.php');

$DBCONNECTIONSTRING = 'mongodb://'.$config['mongodb']['host'].':'.$config['mongodb']['port'].'/'.$config['mongodb']['database'];
$DBNAME = $config['mongodb']['database'];

function deleteObject($key, $uuid, $docID)
{
    global $DBCONNECTIONSTRING,$DBNAME;
    try {
        //db connection
        $client = new MongoDB\Client($DBCONNECTIONSTRING, [
            'username' => $key,
            'password' => $key,
            'db' => $DBNAME
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        $deleteResult = $collection->deleteOne(['_id' => $docID]);

        if ($deleteResult->getDeletedCount() > 0) {
            http_response_code(204);
        } else {
            http_response_code(200);
            print "No items to delete";
        }

    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error deleting object: ' . $ex->getMessage();
        exit();
    }
}

function updateObject($key, $uuid, $docID, $body)
{
    global $DBCONNECTIONSTRING,$DBNAME;
    if (!json_decode($body)) {
        http_response_code(400);
        print "Bad request, malformed JSON";
        exit();
    }
    $annotated = annotateObject($body, $uuid);
    $newObj = json_decode($annotated, true);
    $newObj['_updated'] = true;

    try {
        //db connection
        $client = new MongoDB\Client($DBCONNECTIONSTRING, [
            'username' => $key,
            'password' => $key,
            'db' => $DBNAME
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        //Any _id supplied in the JSON is ignored/overwritten with the one passed in the URL path
        $newObj['_id'] = $docID;
        $replaceOneResult = $collection->replaceOne(['_id' => $docID], $newObj, ['upsert' => true]);

        if ($replaceOneResult->getModifiedCount() > 0) {
            http_response_code(204);
            print "Object updated";
        } else {
            http_response_code(201);
            print "Object created";
        }
    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error creating or updating object: ' . $ex->getMessage();
        exit();
    }
}


function createObject($key, $uuid, $body)
{
    global $DBCONNECTIONSTRING,$DBNAME;
    if (!json_decode($body)) {
        http_response_code(400);
        print "Bad request, malformed JSON";
        exit();
    }
    $annotated = annotateObject($body, $uuid);

    try {
        //db connection
        $client = new MongoDB\Client($DBCONNECTIONSTRING, [
            'username' => $key,
            'password' => $key,
            'db' => $DBNAME
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

/**
 *  END OF FUNCTIONS. MAIN PROCESS CODE BELOW
 */

$request_method = $_SERVER["REQUEST_METHOD"];
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Dataset key must be provided as HTTP Basic Auth username';
    exit;
} else {
    $key = $_SERVER['PHP_AUTH_USER'];
}

//get PUT/POST data
$payload = file_get_contents("php://input");

if (!isset($_GET["uuid"])) {
    http_response_code(400);
    print "Bad request, dataset uuid not specified";
    exit();
}
$uuid = $_GET["uuid"];

if (isset($_GET["id"])) {
    $mongoID = $_GET["id"];
}

/**
 *
 * API USAGE:
 * PUT - USED FOR PUSHING NEW DATA INTO THE SYSTEM
 * POST - USED FOR DATA UPDATES WHERE THE ID IS SUPPLIED IN THE PATH
 * This is the encouraged usage of the API, however, both PUT and POST can be used interchangeably
 * and actually operate in the same way.
 *
 */

switch ($request_method) {
    case 'PUT':
        if (isset($_GET["id"])) {
            updateObject($key, $uuid, $mongoID, $payload);
        } else {
            createObject($key, $uuid, $payload);
        }
        break;
    case 'POST':
        if (isset($_GET["id"])) {
            updateObject($key, $uuid, $mongoID, $payload);
        } else {
            createObject($key, $uuid, $payload);
        }
        break;
    case 'DELETE':
        if (isset($_GET["id"])) {
            deleteObject($key, $uuid, $mongoID);
        } else {
            http_response_code(400);
            print "Bad request, document id missing";
            exit();
        }
        break;
    default:
        // Invalid Request Method
        header("HTTP/1.0 405 Method Not Allowed");
        break;
}
?>