<?php
/**
 * API-FACTORY DATASET OPERATIONS
 *
 *
 * JCarvalho - created 06/03/2019
 */

require '../vendor/autoload.php'; // include Composer's autoloader
$config = include('../config.php');

function createDataset($put_vars) {
	global $config;
	$matchFound = ( isset($_GET["uuid"]) && isset($_GET["key"]) );
	if (!$matchFound) {
		http_response_code(400);
		print "Bad request, missing dataset uuid or access key";
		exit();
	}

	$datasetUUID = $_GET["uuid"];
	$key = $_GET["key"];
	$user = $_SERVER['PHP_AUTH_USER'];
	$pwd = $_SERVER['PHP_AUTH_PW'];

	//db connection
	$client = new MongoDB\Client("mongodb://${user}:${pwd}@".$config['mongodb']['host'].":".$config['mongodb']['port']."/".$config['mongodb']['database']);
	$db = $client->selectDatabase($config['mongodb']['database']);

	//create collection
	try {
		$result = $db->createCollection($datasetUUID, []);
	}
	catch (Exception $ex) {
		//Most likely error here is that the collection already exists
		http_response_code(400);
		echo 'Fatal error creating MongoDB collection: ' .$ex->getMessage();
		exit();
	}
	//Geospatial indexes can be created using:
	//db.getCollection("collection name").createIndex( { loc : "2dsphere" } )

	/**
	 * CREATE A READ ROLE FOR THIS COLLECTION
	 */
	//check if read role exists exists:
	$cursor = $db->command([
		'rolesInfo' => [
			'role' => $datasetUUID . "-R",
			'db' => 'datahub'
		]
	]);
	$cursorItem = $cursor->toArray()[0];
	if (sizeof($cursorItem['roles']) > 0) {
		//role already exists. No need to create it
	}
	else {
		//role doesn't exist, create it
		$result = $db->command([
			'createRole' => $datasetUUID . "-R",
			'privileges' => [
				[
					'resource' => [
						'db' => "datahub",
						'collection' => $datasetUUID
					],
					'actions' => [ "find" ]
				]
			],
			'roles' => []
		]);
	}

	/**
	 * CREATE A WRITE ROLE FOR THIS COLLECTION
	 */
	//check if read role exists exists:
	$cursor = $db->command([
		'rolesInfo' => [
			'role' => $datasetUUID . "-W",
			'db' => 'datahub'
		]
	]);
	$cursorItem = $cursor->toArray()[0];
	if (sizeof($cursorItem['roles']) > 0) {
		//role already exists. No need to create it
	}
	else {
		//role doesn't exist, create it
		$result = $db->command([
			'createRole' => $datasetUUID . "-W",
			'privileges' => [
				[
					'resource' => [
						'db' => "datahub",
						'collection' => $datasetUUID
					],
					'actions' => [ "update", "insert", "remove" ]
				]
			],
			'roles' => []
		]);
	}

	/**
	 * CREATE USER
	 */
	//MongoDB doesn't accept empty passwords, so username and pwd are the same
	//check if user exists:
	$cursor = $db->command([
		'usersInfo' => [
			'user' => $key,
			'db' => 'datahub'
		]
	]);

	$cursorItem = $cursor->toArray()[0];
	if (sizeof($cursorItem['users']) > 0) {
		//user(key) already exists. No need to create it, just assign it some new additional roles
		$result = $db->command([
			'grantRolesToUser' => $key,
			'roles' => [$datasetUUID . "-W", $datasetUUID . "-R"]
			//'db' => 'datahub'
		]);

	}
	else {
		//user doesn't exist, create it
		$result = $db->command([
			'createUser' => $key,
			'pwd' => $key,
			'roles' => [$datasetUUID . "-W", $datasetUUID . "-R"]
		]);
	}
}
/**
 * ============================================================================
 * END OF function createDataset()
 * ============================================================================
 */

function getDatasets($uuid = "-") {
	global $config;

	$user = $_SERVER['PHP_AUTH_USER'];
	$pwd = $_SERVER['PHP_AUTH_PW'];

	try {
		//db connection
		$client = new MongoDB\Client("mongodb://${user}:${pwd}@".$config['mongodb']['host'].":".$config['mongodb']['port']."/".$config['mongodb']['database']);
		$db = $client->selectDatabase($config['mongodb']['database']);
		if ($uuid == "-"){
			//GET ALL DATASETS
			$collectionArray = array();
			foreach ($db->listCollections() as $collectionInfo) {
				array_push($collectionArray,$collectionInfo["name"]);
			}

			header('Content-Type: application/json');
			echo json_encode($collectionArray);
		}
		else {
			//GET ONE DATASET
			//First check if it exists:
			$data = $db->listCollections([
				'filter' => [
					'name' => $uuid,
				],
			]);
			$exist = 0;
			foreach ($data as $collectionInfo) {
				$exist = 1;
			}

			if ($exist) {
				$total_docs = $db->$uuid->countDocuments();
				$summary = array();
				$summary['datasetID'] = $uuid;
				$summary['totalDocs'] = $total_docs;

				header('Content-Type: application/json');
				echo json_encode($summary);
			}
			//If dataset/collection doesn't exist, return empty object
			else {
				header('Content-Type: application/json');
				echo json_encode([],JSON_FORCE_OBJECT);
			}
		}
	}catch (Exception $ex) {
		http_response_code(500);
		echo 'Error retrieving dataset(s): ' . $ex->getMessage();
		exit();
	}




}
/**
 * ============================================================================
 * END OF function getDatasets()
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
			// Retrive datasets
			if(!empty($_GET["uuid"]))
			{
				getDatasets($_GET["uuid"]);
			}
			else
			{
				getDatasets();
			}
			break;
		case 'PUT':
			parse_str(file_get_contents("php://input"),$put_vars);
			createDataset($put_vars);
			break;
		default:
			// Invalid Request Method
			header("HTTP/1.0 405 Method Not Allowed");
			break;
	}
?>
