<?php
/**
 * API-FACTORY DATASET OPERATIONS
 *
 * CALLING WITH GET:
 * 1. NO UUID PASSED: GET ALL DATASETS
 * 2. UUID PASSED: RETURN DATASET DETAILS FOR ONE DATASET
 *
 * CALLING WITH PUT:
 * - CREATE A NEW DATASET(MONGODB COLLECTION)
 * - ALSO CREATE 'R' AND 'W' ROLES ON THAT DATASET
 * - ALSO CREATE DEFAULT KEY(USER), ASSIGNED THE 'R' AND 'W' ROLES ABOVE
 *
 * CALLING WITH POST:
 * NOT YET IMPLEMENTED - MODIFY EXISTING DATASETS
 *
 * JCarvalho - created 06/03/2019
 */

require 'vendor/autoload.php'; // include Composer's autoloader
$config = include('config.php');

function createDataset($put_vars) {
	$matchFound = ( isset($put_vars["uuid"]) && isset($put_vars["key"]) && isset($put_vars["user"]) && isset($put_vars["pwd"]) );
	if (!$matchFound) {
		http_response_code(400);
		print "Bad request";
		exit();
	}

	$datasetUUID = $put_vars["uuid"];
	$key = $put_vars["key"];
	$user = $put_vars["user"];
	$pwd = $put_vars["pwd"];


	//db connection
	$client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
	$db = $client->datahub;

	// FIXME: Catch errors on all of these operations, eg collection might already exist (which currently breaks things)

	//create collection
	try {
		$result = $db->createCollection($datasetUUID, []);
		//var_dump($result);
	}
	catch (Exception $ex) {
		//Most likely error here is that the collection already exists
		http_response_code(400);
		echo 'Fatal error creating MongoDB collection: ' .$ex->getMessage();
		exit();
	}
	//FIXME - NEED TO CREATE A GEOSPATIAL INDEX ON THE LOC FIELD, USING:
	//db.getCollection("3788468c-280b-4548-8880-6c880dce4017").createIndex( { loc : "2dsphere" } )

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
	$matchFound = ( isset($_GET["user"]) && isset($_GET["pwd"]) );

	if (!$matchFound) {
		http_response_code(400);
		print "Bad request, user/pwd not specified";
		exit();
	}

	$user = $_GET["user"];
	$pwd = $_GET["pwd"];

	//db connection
	$client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
	$db = $client->datahub;

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

}
/**
 * ============================================================================
 * END OF function getDatasets()
 * ============================================================================
 */


/**
 *  END OF FUNCTIONS. MAIN CODE BELOW
 */


$request_method=$_SERVER["REQUEST_METHOD"];
switch($request_method)
	{
		case 'GET':
			//var_dump($_GET);
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
