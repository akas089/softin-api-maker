<?php

//Controller function parameters:
//parameters (object): that stores route parameters.
//payload (object): that stores the payload data from the request.
//request (object): that stores the current request data is method, url, headers(array).

class UserController extends Controller
{

    /**
     * signup
     *
     * @param  object $parameters
     * @param  object $payload
     * @param  object $request
     * @return string
     */
    public function signup($parameters, $payload, $request)
    {
        try {
            global $db;
            $data = $db->insertData("users", [
                "_name" => $payload->name,
                "_email" => $payload->email,
                "_password" => password_hash($payload->password, PASSWORD_BCRYPT),
            ]);

            if (isset($data["insertid"])) {
                $token = createToken([
                    "id" => $data["insertid"],
                    "email" => $payload->email
                ]);
                return $this->responseJson(["token" => $token], 200);
            } else {
                return $this->responseJson($data, 404);
            }
        } catch (Exception $e) {
            return $this->responseJson(["error" => $e->getMessage()], 401);
        }
    }

    /**
     * login
     *
     * @param  object $parameters
     * @param  object $payload
     * @param  object $request
     */
    public function login($parameters, $payload, $request)
    {
        try {
            $data = DB::queryFirstRow("SELECT _id, _password FROM users WHERE _email = '$payload->email'");

            if (password_verify($payload->password, $data["_password"])) {
                $token = createToken([
                    "id" => $data["_id"],
                    "email" => $payload->email
                ]);
                return $this->responseJson(["token" => $token], 200);
            } else {
                return $this->responseJson($data, 404);
            }
        } catch (Exception $e) {
            return $this->responseJson(["error" => $e->getMessage()], 401);
        }
    }


    /**
     * getUsers
     *
     * @param  object $parameters
     * @param  object $payload
     * @param  object $request
     * @return string
     */
    public function getUsers($parameters, $payload, $request)
    {
        try {
            global $db;
            $query = "SELECT * FROM users";
            $query .= ($parameters->id == null ? "" : " WHERE _id = '$parameters->id'");
            $data = $db->selectData($query, $parameters->limit, $parameters->offset);
            return $this->responseJson($data, 200);
        } catch (Exception $e) {
            return $this->responseJson(["error" => $e->getMessage()], 401);
        }
    }
}
