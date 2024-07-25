<?php

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
     *                    It is hashed to ensure a 32-byte length suitable for AES-256.
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
            $rules = explode('|', $rules);
            foreach ($rules as $rule) {
                list($ruleName, $param) = array_pad(explode(':', $rule), 2, null);
                $method = 'validate' . ucfirst($ruleName);
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
     * Validates that a field is required.
     *
     * @param string $field The field name.
     */
    protected function validateRequired($field)
    {
        if (empty($this->data[$field])) {
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
     * Validates that a field is an object.
     *
     * @param string $field The field name.
     */
    protected function validateObject($field)
    {
        if (!is_object($this->data[$field])) {
            $this->errors[$field][] = "$field must be an object.";
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


class dbConn extends DB
{
    function __construct()
    {
        DB::$dsn = EW_CONN_DSN;
        DB::$user = EW_CONN_USER;
        DB::$password = EW_CONN_PASS;
    }

    function getData($query, $limit = "", $offset = "")
    {
        $query .= (is_numeric($limit) ? " LIMIT " . $limit : "");
        $query .= (is_numeric($offset) ? " OFFSET " . ($offset - 1) : "");
        $data = DB::query($query);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}