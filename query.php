<?php
/**
 * MAIN PROCESSOR FOR DATA QUERIES ON MONGODB OBJECTS
 *
 * Created by: Jason Carvalho
 * Date: 2019-03-19
 * Time: 15:35
 */

require 'vendor/autoload.php'; // include Composer's autoloader
$config = include('config.php');

/**
 * GLOBALS
 */
$QUERYLIMIT = 100;
$DBCONNECTIONSTRING = 'mongodb://'.$config['mongodb']['host'].':'.$config['mongodb']['port'].'/'.$config['mongodb']['database'];

function doQuery($key, $uuid, $queryBody, $restEntity = false)
{
    global $DBCONNECTIONSTRING;
    global $QUERYLIMIT;
    $limit = $QUERYLIMIT;
    $queryObj = json_decode($queryBody);
    if ($queryObj == null) {
        http_response_code(400);
        echo 'Bad request, malformed JSON query';
        exit();
    }

    try {
        //db connection
        $client = new MongoDB\Client($DBCONNECTIONSTRING, [
            'username' => $key,
            'password' => $key,
            'db' => 'datahub'
        ]);
        $db = $client->datahub;
        $collection = $db->$uuid;

        $options = [
            'limit' => $limit,
            'sort' => [
                '_timestamp' => -1
            ],
        ];

        $result = $collection->find($queryObj, $options);
        $resultArray = $result->toArray();

        if ($restEntity and (sizeof($resultArray) == 0)) {
            http_response_code(404);
            echo 'Object not found';
            exit();
        }

        header('Content-Type: application/json');
        print(json_encode($resultArray));

    } catch (Exception $ex) {
        http_response_code(500);
        echo 'Fatal error running query: ' . $ex->getMessage();
        exit();
    }
}

/**
 *  END OF FUNCTIONS. MAIN PROCESS CODE BELOW
 */

//Check AUTH has been passed
$request_method = $_SERVER["REQUEST_METHOD"];
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Dataset key must be provided as HTTP Basic Auth username';
    exit;
} else {
    $key = $_SERVER['PHP_AUTH_USER'];
}

//See if a limit (number of points to return) has been specified...
if (isset($_GET["limit"])) {
    if (is_numeric((int)$_GET["limit"]) || $_GET["limit"] > 0) {
        $QUERYLIMIT = (int)$_GET["limit"];
    }
}

//Are we retrieving an object/doc in a RESTful way, via an entity ID in the URL?
$restEntity = false;

//Regular non-custom-api call
if (!isset($_GET["uuid"])) {
    http_response_code(400);
    print "Bad request, dataset uuid not specified";
    exit();
}
$uuid = $_GET["uuid"];
$baseQuery = "{}";

switch ($request_method) {
    case 'GET':
        doQuery($key, $uuid, $baseQuery, $restEntity);
        break;
    case 'POST':
        if (file_get_contents("php://input") == "") {
            doQuery($key, $uuid, "{}", $restEntity);
        } else {
            doQuery($key, $uuid, file_get_contents("php://input"), $restEntity);
        }
        break;
    default:
        // Invalid Request Method
        header("HTTP/1.0 405 Method Not Allowed");
        break;
}
?>