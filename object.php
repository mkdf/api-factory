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
//require 'MKSAPIF_CustomAPI.php';
$config = include('config.php');

function deleteObject($key, $uuid, $docID)
{
    try {
        //db connection
        $client = new MongoDB\Client('mongodb://localhost:27017/datahub', [
            'username' => $key,
            'password' => $key,
            'db' => 'datahub'
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

/**
 *  END OF FUNCTIONS. MAIN CODE BELOW
 */

$request_method = $_SERVER["REQUEST_METHOD"];
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
 * PUT - THIS SHOULD BE USED FOR PUSHING DATA INTO THE SYSTEM - BOTH NEW DATA AND DATA UPDATES.
 *
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