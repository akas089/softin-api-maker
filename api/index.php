<?php
include_once __DIR__ . '/app/fn.public.php';
require_once __DIR__ . '/app/router.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");
header("Content-type: application/json");


$router = new router("/project-api/api");

$router->get('/', function () {
    //return json_encode(getallheaders());
    return '{"stats":"OK"}';
});

$router->get('/test/$id/$t', 'good/test');

$router->get('/select/$id?/$limit?/$offset?', function ($request, $payload) {
    global $db;
    $query = "SELECT * FROM acc_voucherdetail ";
    $query .= ($request->id == 'all' ? "" : "WHERE _voucher_id = '$request->id'");
    return $db->getData($query, $request->limit, $request->offset);
});

$router->group("/admin", function ($groupRouter) {
    $groupRouter->post('/login', function ($request, $payload) {
        //global $db;
        //echo $payload->email;
        //return $db->getData($query);

        return str_decrypt($payload->password);

    });
    $groupRouter->post('/signup', function ($request, $payload) {
        $data = [
            'name' => 'Johnddd',
            'email' => null,
            'age' => '30',
            'height' => 1.75,
            'is_active' => true,
            'tags' => array(1, 2, 3),
            'birthdate' => '2023-07-25',
            'website' => 'https://account-care.com',
            'ip_address' => '10.16.100.244',
            'profile' => new stdClass(),
            'status' => 'inactive',
        ];

        $rules = [
            'name' => 'required|string|min:3|max:50',
            'email' => 'email',
            'age' => 'required|integer',
            'height' => 'required|float',
            'is_active' => 'required|boolean',
            'tags' => 'required|array',
            'birthdate' => 'required|date',
            'website' => 'required|url',
            'ip_address' => 'required|ip',
            'profile' => 'required|object',
            'status' => 'required|enum:active,inactive,pending',
        ];

        echo json_encode(dataValidation($data, $rules));

        //return str_encrypt("mina bazar");
    });
});

$router->any('/404', function () {
    return '{"error":"Invlid url!"}';
});
