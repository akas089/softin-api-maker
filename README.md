# Quick Start Guide

Welcome to Softin API Maker

Thank you for choosing Softin API Maker! This guide will help you get started with setting up and using your new API framework. Follow these steps to get your API up and running quickly.

###

[](#id-1.-installation)

1\. Installation

- **Clone or Download the Repository**

  Copy

      git clone https://github.com/akas089/softin-api-maker.git

  Alternatively, download the ZIP file and extract it to your desired directory.

- **Set Up the Environment**

  Ensure you have PHP installed (version 7.4 or higher recommended).

  Set up a web server (e.g., Apache or Nginx) and point it to the root directory of the Softin API Maker.

###

[](#id-2.-configuration)

2\. Configuration

- **Set Up \`.htaccess\` (for Apache)**

  Ensure your `.htaccess` file is in the root directory. It handles URL rewriting for cleaner routes.

Copy

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule (.*) index.php [QSA,L]

- **Configure Database**

  Update the database configuration in `app/config.php` or your specific configuration file. Set your database connection details here.

###

[](#id-3.-define-routes)

3\. Define Routes

- **Update** `**index.php**`

  Define your API routes and middleware in `index.php`. Here’s a brief example:

Copy

    // Include necessary files
    require_once 'app/fn.public.php';

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 600");
    header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
    header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");

    // Example usage
    $router = new Router();
    $router->setBaseUrl(EW_API_BASE_URL); // Set the base URL

    //Controller function parameters:
    //parameters (object): that stores route parameters.
    //payload (object): that stores the payload data from the request.
    //request (object): that stores the current request data is method, url, headers(array).

    $router->get('/', function ($parameters, $payload, $request) {
        return ["stats" => "Ok"];
    });

    // Define routes
    $router->group(['prefix' => '/user'], function ($router) {
        $router->post('/signup', 'users\\UserController@signup');
        $router->post('/login', 'users\\UserController@login');
    });

    $router->group(['middleware' => 'authMiddleware'], function ($router) {
        $router->get('/users/{id?}/{limit?}/{offset?}', 'user\\UserController@getUsers');
    });

    // Resolve current request
    $router->resolve();

- **Create Controllers**

  Define your controllers (e.g., `Controllers/UserController.php`) to handle requests. Implement methods for actions like signup, login, and fetching users.

Copy

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

###

[](#id-4.-middleware)

4\. Middleware

- **Add Middleware**

  Implement middleware functions to handle authentication, authorization, or other pre-processing tasks. Example provided in `index.php`:

Copy

    // Middleware example
    function authMiddleware()
    {
        $headers = getallheaders();
        if (isset($headers["Authorization"])) {
            $payload = checkToken($headers["Authorization"]);
            if (count((array) $payload) == 0) {
                exit(responseJson(["error" => "Unauthorized User"], 401));
            }
        } else {
            exit(responseJson(["error" => "Unauthorized User"], 401));
        }
    }

###

[](#id-5.-running-your-api)

5\. Running Your API

- **Start Your Web Server**

  Make sure your web server is running and pointing to the root directory of your Softin API Maker project.

- **Access the API**

  You can now access your API endpoints. For example:

  `POST /user/signup`

  `POST /user/login`

  `GET /users/{id?}`

###

[](#id-6.-testing)

6\. Testing

- **Use Tools like Postman**

  Test your API endpoints using tools like Postman to ensure they are working correctly.

- **Check Logs**

  Monitor server logs for any errors or issues during API requests.
