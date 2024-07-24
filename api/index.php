<?php
include_once __DIR__ . '/app/fn.public.php';
require_once __DIR__ . '/app/router.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");
header("Content-type: application/json");

$router = new router("/project/api");

$router->get('/', function () {
    echo json_encode(getallheaders());
    //echo '{"stats":"OK"}';
});

$router->get('/test/$id/$t', 'good/test');

$router->get('/select/$id?/$limit?/$offset?', function ($request, $payload) {
    $query = "SELECT * FROM acc_voucherdetail ";
    $query .= ($request->id == 'all' ? "" : "WHERE _voucher_id = '$request->id'");
    echo getData($query, $request->limit, $request->offset);
});

$router->group("/admin", function ($groupRouter) {
    $groupRouter->post('/login', function ($request, $payload) {
        setcookie("token", "Basic xxxx", time() + 60 * 60 * 24 * 1);
        echo json_encode($payload);
    });
    $groupRouter->post('/logout', function ($request, $payload) {
        echo json_encode($payload);
    });
});

$router->any('/404', function () {
    echo '{"error":"Invlid url!"}';
});
