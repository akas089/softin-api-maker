<?php

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
    /* 
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    } 
        */
}

// Example usage
$router = new Router();
$router->setBaseUrl('/project-api/api'); // Set the base URL

$router->get('/', function ($r, $p, $parm) {
    return '{"stats":"OK"}';
});

$router->get('/select/{id}/{limit?}/{offset?}', function ($param) {
    global $db;
    $query = "SELECT * FROM acc_voucherdetail ";
    $query .= ($param->id == 'all' ? "" : "WHERE _voucher_id = '$param->id'");
    $data = $db->getData($query, $param->limit, $param->offset);

    return responseJson($data, 200);
});

// Define routes
$router->group(['prefix' => '/user'], function ($router) {
    $router->post('/signup', 'user\\UserController@index');
    $router->post('/login/{id}', 'user\\UserController@show');
});

$router->group(['middleware' => 'authMiddleware'], function ($router) {
    $router->get('/user/{id}', 'UserController@update');
    $router->post('/user/{id}', 'UserController@destroy');
});

// Resolve current request
$router->resolve();
