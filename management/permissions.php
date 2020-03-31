<?php
/**
 *  * API-FACTORY PERMISSIONS OPERATIONS
 *
 * JCarvalho - created 07/03/2019
 */

require '../vendor/autoload.php'; // include Composer's autoloader
$config = include('../config.php');

function getPermissions($key = "-") {
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];

    //db connection
    $client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
    $db = $client->datahub;

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
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];

    //overwrite these for now
    //$user = "admin";
    //$pwd = 'klas228JD!';

    $fullParamsFound = ( isset($_POST["key"]) && isset($_POST["dataset-uuid"]) && isset($_POST["read"]) && isset($_POST["write"]));
    if (!$fullParamsFound) {
        http_response_code(400);
        print "Bad request. Expecting dataset-uuid, key, read, write";
        exit();
    }

    //db connection
    $client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
    $db = $client->datahub;

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
    //print (print_r($result));

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
    //print (print_r($result));
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
    //echo "<p>Hello {$_SERVER['PHP_AUTH_USER']}.</p>";
    //echo "<p>You entered {$_SERVER['PHP_AUTH_PW']} as your password.</p>";
    $user = $_SERVER['PHP_AUTH_USER'];
    $pwd = $_SERVER['PHP_AUTH_PW'];
}

$request_method=$_SERVER["REQUEST_METHOD"];
switch($request_method)
{
    case 'GET':
        //var_dump($_GET);
        // Retrieve users
        if(!empty($_GET["key"]))
        {
            //echo "passing value";
            getPermissions($_GET["key"]);
        }
        else
        {
            //echo "no passing value";
            getPermissions();
        }
        break;
    case 'POST':
        $key = $_POST["key"];
        $uuid = $_POST["dataset-uuid"];
        $read = $_POST["read"];
        $write = $_POST["write"];
        updatePermissions($key, $uuid, $read, $write);
        break;
    default:
        // Invalid Request Method
        header("HTTP/1.0 405 Method Not Allowed");
        break;
}

?>