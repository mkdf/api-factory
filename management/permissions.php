<?php
/**
 *  * API-FACTORY PERMISSIONS OPERATIONS
 *
 * JCarvalho - created 07/03/2019
 */

require '../vendor/autoload.php'; // include Composer's autoloader
$config = include('../config.php');

function getPermissions($key = "-") {
    global $config;
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];

    //db connection
    $client = new MongoDB\Client("mongodb://${user}:${pwd}@".$config['mongodb']['host'].":".$config['mongodb']['port']);
    $db = $client->selectDatabase($config['mongodb']['database']);

    if ($key == "-"){
        //GET ALL USERS
        $userArray = array();
        $result = $db->command([
            "usersInfo" => 1
        ]);
        foreach ($result as $userInfo) {
            array_push($userArray,$userInfo);
        }

        header('Content-Type: application/json');
        echo json_encode($userArray);
    }
    else {
        //GET ONE USER
        $userArray = array();
        $result = $db->command([
            "usersInfo" => $key
        ]);
        foreach ($result as $userInfo) {
            array_push($userArray,$userInfo);
        }

        header('Content-Type: application/json');
        echo json_encode($userArray);
    }
}
/**
 * ============================================================================
 * END OF function  getPermissions()
 * ============================================================================
 */


function updatePermissions($key, $datasetUuid, $read, $write) {
    global $config;
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];

    $fullParamsFound = ( isset($_GET["key"]) && isset($_GET["dataset-uuid"]) && isset($_GET["read"]) && isset($_GET["write"]));
    if (!$fullParamsFound) {
        http_response_code(400);
        print "Bad request. Expecting dataset-uuid, key, read, write";
        exit();
    }

    //db connection
    $client = new MongoDB\Client("mongodb://${user}:${pwd}@".$config['mongodb']['host'].":".$config['mongodb']['port']);
	$db = $client->selectDatabase($config['mongodb']['database']);

    $readRole = $datasetUuid . "-R";
    $writeRole = $datasetUuid . "-W";


    //check if user exists:
    $cursor = $db->command([
        'usersInfo' => [
            'user' => $key,
            'db' => 'datahub'
        ]
    ]);

    $cursorItem = $cursor->toArray()[0];
    if (sizeof($cursorItem['users']) == 0) {
        //user doesn't exist, create it
        $result = $db->command([
            'createUser' => $key,
            'pwd' => $key,
            'roles' => []
        ]);
    }

    //Assign roles
    //if read access:
    if ($read) {
        $result = $db->command([
            'grantRolesToUser' => $key,
            'roles' => [$readRole]
            //s'db' => 'datahub'
        ]);
    }
    else {
        //remove read permissions
        $result = $db->command([
            'revokeRolesFromUser' => $key,
            'roles' => [$readRole]
            //'db' => 'datahub'
        ]);
    }

    //if write access:
    if ($write) {
        $result = $db->command([
            'grantRolesToUser' => $key,
            'roles' => [$writeRole]
            //'db' => 'datahub'
        ]);
    }
    else {
        //remove write permissions
        $result = $db->command([
            'revokeRolesFromUser' => $key,
            'roles' => [$writeRole]
            //'db' => 'datahub'
        ]);
    }
}

/**
 * ============================================================================
 * END OF function updatePermissions()
 * ============================================================================
 */




/**
 *  END OF FUNCTIONS. MAIN CODE BELOW
 */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'HTTP Basic authentication required';
    exit;
} else {
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];
}

$request_method=$_SERVER["REQUEST_METHOD"];
switch($request_method)
{
    case 'GET':
        // Retrieve users
        if(!empty($_GET["key"]))
        {
            getPermissions($_GET["key"]);
        }
        else
        {
            getPermissions();
        }
        break;
    case 'POST':
        $key = $_GET["key"];
        $uuid = $_GET["dataset-uuid"];
        $read = $_GET["read"];
        $write = $_GET["write"];
        updatePermissions($key, $uuid, $read, $write);
        break;
    default:
        // Invalid Request Method
        header("HTTP/1.0 405 Method Not Allowed");
        break;
}

?>