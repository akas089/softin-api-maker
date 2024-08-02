<?php
if (!session_id())
    session_start();

// Include necessary files
require_once 'app/fn.public.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");
//header("Content-type: application/json");

// Middleware example
function authMiddleware()
{
    $headers = getallheaders();
    if (isset($headers["Authorization"]) || isset($_SESSION['token'])) {
        $payload = checkToken($headers["Authorization"] ?? $_SESSION['token']);
        if (count((array) $payload) == 0) {
            exit(responseJson(["error" => "Unauthorized User"], 401));
        }
    } else {
        exit(responseJson(["error" => "Unauthorized User"], 401));
    }
}

// Example usage
$router = new Router();
$router->setBaseUrl(EW_API_BASE_URL); // Set the base URL

$router->get('/', function () {
    return responseJson(["stats" => ($_SESSION['token'] ? "User is currently logged in to this device" : "The system indicates that this device has been logged out.")], 200);
});

// Define routes
$router->group(['prefix' => '/user'], function ($router) {
    $router->post('/signup', 'users\\UserController@signup');
    $router->post('/login', 'users\\UserController@login');
});

$router->group(['middleware' => 'authMiddleware'], function ($router) {
    $router->get('/users/{id?}/{limit?}/{offset?}', 'users\\UserController@getUsers');
});

// Resolve current request
$router->resolve();
