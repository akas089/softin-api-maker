<?php

/**
 * Class Router
 * 
 * A simple router class to handle HTTP methods, route parameters, middleware, and base URL.
 */
class Router
{
    private $routes = [];
    private $middleware = [];
    private $prefix = '';
    private $baseUrl = '';
    private $request = [];
    private $payload = [];
    private $parameters = [];

    /**
     * Set the base URL for the router.
     * 
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Group routes with a common prefix and middleware.
     * 
     * @param array $attributes
     * @param callable $callback
     */
    public function group($attributes, $callback)
    {
        $parentPrefix = $this->prefix;
        $parentMiddleware = $this->middleware;

        if (isset($attributes['prefix'])) {
            $this->prefix .= $attributes['prefix'];
        }

        if (isset($attributes['middleware'])) {
            $this->middleware = array_merge($this->middleware, (array) $attributes['middleware']);
        }

        call_user_func($callback, $this);

        $this->prefix = $parentPrefix;
        $this->middleware = $parentMiddleware;
    }

    /**
     * Add a route to the router.
     * 
     * @param string $method
     * @param string $uri
     * @param callable|string $action
     */
    public function addRoute($method, $uri, $action)
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $this->baseUrl . $this->prefix . $uri,
            'action' => $action,
            'middleware' => $this->middleware
        ];
    }

    /**
     * Add a GET route.
     * 
     * @param string $uri
     * @param callable|string $action
     */
    public function get($uri, $action)
    {
        $this->addRoute('GET', $uri, $action);
    }

    /**
     * Add a POST route.
     * 
     * @param string $uri
     * @param callable|string $action
     */
    public function post($uri, $action)
    {
        $this->addRoute('POST', $uri, $action);
    }

    /**
     * Add a PUT route.
     * 
     * @param string $uri
     * @param callable|string $action
     */
    public function put($uri, $action)
    {
        $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Add a PATCH route.
     * 
     * @param string $uri
     * @param callable|string $action
     */
    public function patch($uri, $action)
    {
        $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Add a DELETE route.
     * 
     * @param string $uri
     * @param callable|string $action
     */
    public function delete($uri, $action)
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Get request data.
     * 
     * @return object
     */
    public function getRequestData()
    {
        $request = (object) [
            'method' => $_SERVER['REQUEST_METHOD'],
            'url' => $_SERVER['REQUEST_URI'],
            'headers' => "",
        ];
        $this->request->headers = getallheaders();

        return $request;
    }

    /**
     * Get payload data from JSON body.
     * 
     * @return array
     */
    private function getPayloadData()
    {
        $postData = json_decode(file_get_contents('php://input'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $postData = (object) $_POST;
        }
        return $postData;
    }

    /**
     * Resolve the current request URI and method.
     * 
     * @param string $method
     * @param string $uri
     */
    public function resolve()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            $matches = $this->checkRoutes($route['uri'], $uri);
            if ($route['method'] === $method && $matches) {
                array_shift($matches); // Remove the full match

                // Extract named parameters
                $routeParams = [];
                $keys = $this->getParameterNames($route['uri']);
                foreach ($keys as $index => $key) {
                    if (isset($matches[$index])) {
                        $routeParams[$key] = $matches[$index];
                    } else {
                        $routeParams[$key] = null;
                    }
                }

                $this->parameters = (object) $routeParams; // Convert parameters to an object
                $this->payload = $this->getPayloadData();
                $this->request = $this->getRequestData();

                $this->runMiddleware($route['middleware']);
                return $this->invokeAction($route['action']);
            }
        }

        http_response_code(404);
        header("Content-type: application/json");
        exit(json_encode(["error" => "404 Not Found"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    }

    /**
     * Check route Match.
     * 
     * @param string $route
     * @param string $uri
     * @return array|bool
     */
    private function routeMatch($route, $uri)
    {
        $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_]+)', $route);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            return $matches;
        }
        return false;
    }

    /**
     * Check route with parameters.
     * 
     * @param string $route
     * @param string $uri
     * @return array
     */
    private function checkRoutes($route, $uri)
    {
        preg_match_all("/\{([a-zA-Z0-9_]+)\?\}/", $route, $optionalParameters);

        if ($this->baseUrl . "/" != $route)
            $route = rtrim(preg_replace('/\{([a-zA-Z0-9_]+)\?\}/', '', $route), '/');

        $matches = $this->routeMatch($route, $uri);

        if (is_array($matches)) {
            return $matches;
        } else {
            foreach ($optionalParameters[0] as $param) {
                $route = $route . '/' . str_replace('?', '', $param);
                $matches = $this->routeMatch($route, $uri);
                if (is_array($matches)) {
                    return $matches;
                }
            }
        }

        return [];
    }

    /**
     * Extract parameter names from a URI.
     * 
     * @param string $uri
     * @return array
     */
    private function getParameterNames($uri)
    {
        //preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches);
        preg_match_all('/\{([a-zA-Z0-9_]+)\??\}/', $uri, $matches);
        return $matches[1];
    }

    /**
     * Run middleware before executing a route action.
     * 
     * @param array $middleware
     */
    private function runMiddleware($middleware)
    {
        foreach ($middleware as $m) {
            call_user_func($m);
        }
    }

    /**
     * Invoke the action associated with a route.
     * 
     * @param callable|string $action
     * 
     */
    private function invokeAction($action)
    {
        if (is_callable($action)) {
            exit(call_user_func($action, $this->parameters, $this->payload, $this->request));
        } elseif (is_string($action)) {
            if (strpos($action, '@') !== false) {
                foreach (["\\", "/", "."] as $separator) {
                    $control = explode($separator, $action);
                    if (count($control) > 1) {
                        break;
                    }
                }
                $file = current(explode('@', $action));
                list($controller, $function) = explode('@', end($control));
                $this->includeControllerFile($file);
                $controller = new $controller();
                exit(call_user_func_array([$controller, $function], [$this->parameters, $this->payload, $this->request]));
            } else {
                exit(call_user_func_array($action, [$this->parameters, $this->payload, $this->request]));
            }
        }

        throw new Exception('Invalid route action');
    }

    /**
     * Include the controller file if it exists.
     * 
     * @param string $controller
     */
    private function includeControllerFile($controller)
    {
        $filePath = 'controllers/' . str_replace(["\\", "."], '/', $controller) . '.php';
        if (file_exists($filePath)) {
            include_once $filePath;
        }
    }
}

/**
 * Class Controller
 * 
 * A base controller class to provide common functionalities like rendering templates.
 */
class Controller
{
    /**
     * Render a template with the provided data.
     * 
     * @param string $templatePath
     * @param array $data
     */
    protected function viewRender($viewPath, $data = [])
    {
        $template = new Template($viewPath);
        extract($data);
        $template->render();
    }

    /**
     * Render a template with the provided data.
     * 
     * @param string $templatePath
     * @param array $data
     */
    protected function templateRender($templatePath, $data = [])
    {
        $template = new Template($templatePath);
        $compiledTemplate = $template->compile();
        extract($data);
        eval ("?>" . $compiledTemplate);
    }

    /**
     * Return a new response from the application.
     *
     * @param  array $data
     * @param  int  $status
     * @param  array  $headers
     * @return string
     */
    protected function responseJson($data, $status = 200, array $headers = [])
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            header("Content-type: application/json");
        }

        foreach ($headers as $key => $value) {
            header($key . ": " . $value);
        }

        http_response_code($status);
        return $data;
    }

    /**
     * Return a new response from the application.
     *
     * @param  array $data
     * @param  int  $status
     * @param  array  $headers
     * @return array
     */
    function response($data, $status = 200, array $headers = [])
    {
        foreach ($headers as $key => $value) {
            header($key . ": " . $value);
        }

        http_response_code($status);
        return $data;
    }
}

/**
 * Class Template
 * 
 * A simple template engine to compile and render templates with Blade-like syntax.
 */
class Template
{
    protected $templatePath;

    /**
     * Template constructor.
     * 
     * @param string $templatePath
     */
    public function __construct($templatePath)
    {
        $this->templatePath = $templatePath;
    }

    /**
     * Include the view file if it exists.
     * 
     * @param string $view
     */
    private function includeViewFile($view)
    {
        $filePath = 'views/' . str_replace(["\\", "."], '/', $view) . '.php';
        if (file_exists($filePath)) {
            include_once $filePath;
        }
    }

    /**
     * Render a template with the provided data.
     * 
     */
    public function render()
    {
        include_once $this->includeViewFile($this->templatePath); // Include the template file
    }

    /**
     * Compile a template string to PHP code.
     * 
     * @return string
     */
    public function compile()
    {
        ob_start(); // Start output buffering
        $this->includeViewFile($this->templatePath); // Include the template file
        $template = ob_get_clean(); // Get the buffered content and clean the buffer

        $template = preg_replace('/\{\{ (.*?) \}\}/', '<?php echo htmlspecialchars($1); ?>', $template); // Replace {{ }} with PHP echo
        $template = preg_replace('/\{\{(.*?)\}\}/', '<?php echo $1; ?>', $template); // Handle unescaped {{ }}
        $template = preg_replace('/@if\s*\((.*?)\)/', '<?php if($1): ?>', $template); // Handle @if
        $template = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif($1): ?>', $template); // Handle @elseif
        $template = preg_replace('/@else/', '<?php else: ?>', $template); // Handle @else
        $template = preg_replace('/@endif/', '<?php endif; ?>', $template); // Handle @endif
        $template = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach($1): ?>', $template); // Handle @foreach
        $template = preg_replace('/@endforeach/', '<?php endforeach; ?>', $template); // Handle @endforeach
        $template = preg_replace('/@for\s*\((.*?)\)/', '<?php for($1): ?>', $template); // Handle @for
        $template = preg_replace('/@endfor/', '<?php endfor; ?>', $template); // Handle @endfor
        $template = preg_replace('/@while\s*\((.*?)\)/', '<?php while($1): ?>', $template); // Handle @while
        $template = preg_replace('/@endwhile/', '<?php endwhile; ?>', $template); // Handle @endwhile
        return $template;
    }
}

/**
 * Encryptor class
 * 
 * This class provides methods to encrypt and decrypt data using a passkey.
 * It uses AES-256-CBC encryption provided by the OpenSSL extension.
 */
class Encryptor
{
    private $key;
    private $cipher = 'aes-256-cbc';

    /**
     * Constructor
     *
     * @param string $key The passkey used for encryption and decryption. 
     *               It is hashed to ensure a 32-byte length suitable for AES-256.
     */
    public function __construct($key)
    {
        // Ensure the key is exactly 32 bytes (256 bits) for AES-256
        $this->key = hash('sha256', $key, true);
    }

    /**
     * Encrypts the given data.
     *
     * @param string $data The data to be encrypted.
     * @return string The Base64-encoded string containing the IV and encrypted data.
     * @throws Exception If encryption fails.
     */
    public function encrypt($data)
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encryptedData = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);
        if ($encryptedData === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }

        // The IV needs to be stored along with the encrypted data
        return base64_encode($iv . $encryptedData);
    }

    /**
     * Decrypts the given data.
     *
     * @param string $data The Base64-encoded string containing the IV and encrypted data.
     * @return string The decrypted data.
     * @throws Exception If decryption fails.
     */
    public function decrypt($data)
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encryptedData = substr($data, $ivLength);

        $decryptedData = openssl_decrypt($encryptedData, $this->cipher, $this->key, 0, $iv);
        if ($decryptedData === false) {
            throw new Exception('Decryption failed: ' . openssl_error_string());
        }

        return $decryptedData;
    }
}

/**
 * Validator class for data validation.
 * 
 * $rules = [
 *       'name' => 'required|string|min:3|max:50',
 *       'email' => 'nullable|email',
 *       'age' => 'required|integer',
 *       'height' => 'required|float',
 *       'is_active' => 'required|boolean',
 *       'tags' => 'required|array',
 *       'birthdate' => 'required|date',
 *       'website' => 'required|url',
 *       'ip_address' => 'required|ip',
 *       'status' => 'required|enum:active,inactive,pending',
 *       'metadata' => 'required|json',
 *       'nullable_field' => 'nullable|integer',
 * ];
 */
class Validator
{
    protected $data;
    protected $rules;
    protected $errors = [];

    /**
     * Constructor to initialize data and rules.
     *
     * @param array $data  The data to be validated.
     * @param array $rules The validation rules.
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Validates the data against the rules.
     *
     * @return bool Returns true if validation passes, false otherwise.
     */
    public function validate()
    {
        foreach ($this->rules as $field => $rules) {
            $rulesArray = explode('|', $rules);
            $isNullable = in_array('nullable', $rulesArray);

            foreach ($rulesArray as $rule) {
                if ($rule === 'nullable') {
                    continue; // Skip the nullable rule
                }

                list($ruleName, $param) = array_pad(explode(':', $rule), 2, null);
                $method = 'validate' . ucfirst($ruleName);

                if ($isNullable && $this->isEmpty($this->data[$field])) {
                    break; // Skip other validations if the field is nullable and empty
                }

                if (!method_exists($this, $method)) {
                    throw new Exception("Validation rule $ruleName doesn't exist.");
                }
                $this->$method($field, $param);
            }
        }
        return empty($this->errors);
    }

    /**
     * Returns the validation errors.
     *
     * @return array An array of validation errors.
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Checks if a value is empty (null or empty string).
     *
     * @param mixed $value The value to check.
     * @return bool Returns true if the value is empty, false otherwise.
     */
    protected function isEmpty($value)
    {
        return is_null($value) || $value === '';
    }

    /**
     * Validates that a field is required.
     *
     * @param string $field The field name.
     */
    protected function validateRequired($field)
    {
        if ($this->isEmpty($this->data[$field])) {
            $this->errors[$field][] = "$field is required.";
        }
    }

    /**
     * Validates that a field is a valid email address.
     *
     * @param string $field The field name.
     */
    protected function validateEmail($field)
    {
        if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "$field must be a valid email address.";
        }
    }

    /**
     * Validates that a field has a minimum length.
     *
     * @param string $field The field name.
     * @param int    $param The minimum length.
     */
    protected function validateMin($field, $param)
    {
        if (strlen($this->data[$field]) < $param) {
            $this->errors[$field][] = "$field must be at least $param characters.";
        }
    }

    /**
     * Validates that a field has a maximum length.
     *
     * @param string $field The field name.
     * @param int    $param The maximum length.
     */
    protected function validateMax($field, $param)
    {
        if (strlen($this->data[$field]) > $param) {
            $this->errors[$field][] = "$field must be no more than $param characters.";
        }
    }

    /**
     * Validates that a field is numeric.
     *
     * @param string $field The field name.
     */
    protected function validateNumeric($field)
    {
        if (!is_numeric($this->data[$field])) {
            $this->errors[$field][] = "$field must be a number.";
        }
    }

    /**
     * Validates that a field is a string.
     *
     * @param string $field The field name.
     */
    protected function validateString($field)
    {
        if (!is_string($this->data[$field])) {
            $this->errors[$field][] = "$field must be a string.";
        }
    }

    /**
     * Validates that a field is an integer.
     *
     * @param string $field The field name.
     */
    protected function validateInteger($field)
    {
        if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = "$field must be an integer.";
        }
    }

    /**
     * Validates that a field is a float.
     *
     * @param string $field The field name.
     */
    protected function validateFloat($field)
    {
        if (!filter_var($this->data[$field], FILTER_VALIDATE_FLOAT)) {
            $this->errors[$field][] = "$field must be a float.";
        }
    }

    /**
     * Validates that a field is a boolean.
     *
     * @param string $field The field name.
     */
    protected function validateBoolean($field)
    {
        if (!is_bool($this->data[$field])) {
            $this->errors[$field][] = "$field must be a boolean.";
        }
    }

    /**
     * Validates that a field is an array.
     *
     * @param string $field The field name.
     */
    protected function validateArray($field)
    {
        if (!is_array($this->data[$field])) {
            $this->errors[$field][] = "$field must be an array.";
        }
    }

    /**
     * Validates that a field is a date in 'Y-m-d' format.
     *
     * @param string $field The field name.
     */
    protected function validateDate($field)
    {
        $d = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
        if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
            $this->errors[$field][] = "$field must be a valid date in the format 'Y-m-d'.";
        }
    }

    /**
     * Validates that a field is a valid URL.
     *
     * @param string $field The field name.
     */
    protected function validateUrl($field)
    {
        if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "$field must be a valid URL.";
        }
    }

    /**
     * Validates that a field is a valid IP address.
     *
     * @param string $field The field name.
     */
    protected function validateIp($field)
    {
        if (!filter_var($this->data[$field], FILTER_VALIDATE_IP)) {
            $this->errors[$field][] = "$field must be a valid IP address.";
        }
    }

    /**
     * Validates that a field is a valid JSON string.
     *
     * @param string $field The field name.
     */
    protected function validateJson($field)
    {
        json_decode($this->data[$field]);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[$field][] = "$field must be a valid JSON string.";
        }
    }

    /**
     * Validates that a field's value is within a specified set of allowed values.
     *
     * @param string $field The field name.
     * @param string $param The allowed values, separated by commas.
     */
    protected function validateEnum($field, $param)
    {
        $allowedValues = explode(',', $param);
        if (!in_array($this->data[$field], $allowedValues)) {
            $this->errors[$field][] = "$field must be one of the following values: " . implode(', ', $allowedValues) . ".";
        }
    }

    /**
     * Validates that a field key exists in the data.
     *
     * @param string $field The field name.
     */
    protected function validateHasKey($field)
    {
        if (!array_key_exists($field, $this->data)) {
            $this->errors[$field][] = "$field key must be present in the data.";
        }
    }

    /**
     * Handles unknown validation methods gracefully.
     *
     * @param string $method    The method name.
     * @param array  $arguments The method arguments.
     */
    public function __call($method, $arguments)
    {
        if (strpos($method, 'validate') === 0) {
            $rule = lcfirst(str_replace('validate', '', $method));
            $field = $arguments[0];
            $param = $arguments[1] ?? null;
            $this->errors[$field][] = "$field must satisfy the $rule rule.";
        }
    }
}

/**
 * dbConn
 */
class dbConn extends DB
{
    function __construct()
    {
        DB::$dsn = EW_CONN_DSN;
        DB::$user = EW_CONN_USER;
        DB::$password = EW_CONN_PASS;
    }

    /**
     * rawQuery
     *
     * @param  string $query
     * @return array
     */
    function rawQuery($query)
    {
        try {
            DB::startTransaction();
            $data = DB::query($query);
            DB::commit();
            return ["data" => $data, "insertid" => DB::insertId(), "affectedrows" => DB::affectedRows()];
        } catch (Exception $e) {
            DB::rollback();
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * Select first row or first field data from database
     *
     * @param  string $query
     * @param  bool $firstField
     * @return array|string
     */
    function selectFirstRow($query, $firstField = false)
    {
        try {
            if (!$firstField) {
                return DB::queryFirstRow($query);
            } else {
                return DB::queryFirstField($query);
            }
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * Select data from database
     *
     * @param  string $query
     * @param  numeric $limit
     * @param  numeric $offset
     * @return array
     */
    function selectData($query, $limit = "", $offset = "")
    {
        try {
            $query .= (is_numeric($limit) ? " LIMIT " . $limit : "");
            $query .= (is_numeric($offset) ? " OFFSET " . ($offset - 1) : "");
            return DB::query($query);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * insertData
     *
     * @param  string $table
     * @param  array $data
     * @param  bool $ignore
     * @return array
     */
    function insertData($table, $data, $ignore = false)
    {
        try {
            DB::query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            DB::startTransaction();
            if ($ignore) {
                DB::insertIgnore($table, $data);
            } else {
                DB::insert($table, $data);
            }

            DB::commit();
            return ["insertid" => DB::insertId()];
        } catch (Exception $e) {
            DB::rollback();
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * insertUpdateData
     *
     * @param  string $table
     * @param  array $data
     * @param  array optional $where
     * @return array
     */
    function insertUpdateData($table, $data, $where = [])
    {
        try {
            DB::query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            DB::startTransaction();
            if (count($where) > 0) {
                DB::insertUpdate($table, $data, $where);
            } else {
                DB::insertUpdate($table, $data);
            }
            DB::commit();
            return ["insertid" => DB::insertId()];
        } catch (Exception $e) {
            DB::rollback();
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * updateData
     *
     * @param  string $table
     * @param  array $data
     * @param  array $where
     * @return array
     */
    function updateData($table, $data, $where)
    {
        try {
            DB::startTransaction();
            DB::update($table, $data, $where);
            DB::commit();
            return ["affectedrows" => DB::affectedRows()];
        } catch (Exception $e) {
            DB::rollback();
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * deleteData
     *
     * @param  string $table
     * @param  array $where
     * @return array
     */
    function deleteData($table, $where)
    {
        try {
            DB::query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            DB::startTransaction();
            DB::delete($table, $where);
            DB::commit();
            return ["affectedrows" => DB::affectedRows()];
        } catch (Exception $e) {
            DB::rollback();
            return ["error" => $e->getMessage()];
        }
    }
}