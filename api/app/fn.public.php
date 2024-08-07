<?php
if (!session_id())
	session_start();

include_once ("config.php");
include_once ("db.class.php");
include_once ("st.class.php");

$db = new dbConn();

// Encrypt
function strEncrypt($str, $key = EW_PASS_KEY)
{
	$encryptor = new Encryptor($key);
	return $encryptor->encrypt($str);
}

// Decrypt
function strDecrypt($str, $key = EW_PASS_KEY)
{
	$encryptor = new Encryptor($key);
	return $encryptor->decrypt($str);
}

// Data Validation
function dataValidation($data, $rules)
{
	$validator = new Validator($data, $rules);

	if ($validator->validate()) {
		return true;
	} else {
		return $validator->errors();
	}
}

/**
 * Remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
 * This prevents some character re-spacing such as <java\0script> 
 * Note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs 
 *
 * @param  string $val
 * @return string
 */
function xssRemove($value)
{
	$val = preg_replace('/([\x00-\x08][\x0b-\x0c][\x0e-\x20])/', '', $value);

	// Straight replacements, the user should never need these since they're normal characters 
	// This prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29> 

	$search = 'abcdefghijklmnopqrstuvwxyz';
	$search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$search .= '1234567890!@#$%^&*()';
	$search .= '~`";:?+/={}[]-_|\'\\';
	for ($i = 0; $i < strlen($search); $i++) {

		// ;? matches the ;, which is optional 
		// 0{0,7} matches any padded zeros, which are optional and go up to 8 chars 
		// &#x0040 @ search for the hex values 

		$val = preg_replace('/(&#[x|X]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // With a ; 

		// &#00064 @ 0{0,7} matches '0' zero to seven times 
		$val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // With a ; 
	}

	// Now the only remaining whitespace attacks are \t, \n, and \r 
	$ra = XSS_REMOVE_TAG; // Note: Customize XSS_ARRAY in config.php
	$found = true; // Keep replacing as long as the previous round replaced something 
	while ($found == true) {
		$val_before = $val;
		for ($i = 0; $i < sizeof($ra); $i++) {
			$pattern = '/';
			for ($j = 0; $j < strlen($ra[$i]); $j++) {
				if ($j > 0) {
					$pattern .= '(';
					$pattern .= '(&#[x|X]0{0,8}([9][a][b]);?)?';
					$pattern .= '|(&#0{0,8}([9][10][13]);?)?';
					$pattern .= ')?';
				}
				$pattern .= $ra[$i][$j];
			}
			$pattern .= '/i';
			$replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // Add in <> to nerf the tag 
			$val = preg_replace($pattern, $replacement, $val); // Filter out the hex tags 
			if ($val_before == $val) {

				// No replacements were made, so exit the loop 
				$found = false;
			}
		}
	}
	return addslashes($val);
}

/**
 * FileWrite
 *
 * @param  string|array $txt
 * @param  string $file
 */
function fileWrite($txt, $file = "")
{
	file_put_contents(($file ? $file : 'log/test.txt'), (is_array($txt) || is_object($txt) ? print_r($txt, true) : $txt) . "\r\n", FILE_APPEND);
}

function customError($errno, $errstr, $errfile, $errline)
{
	fileWrite("Error[$errno]: " . $errstr . ", file: " . $errfile . ", line: " . $errline, "log/error.txt");
}

/**
 * Check token
 *
 * @param  string $token
 * @return array
 */
function checkToken($token)
{
	try {
		$data = explode('.', $token);
		$payload = json_decode(base64_decode($data[0]));
		$signature = strDecrypt($data[1], EW_PASS_KEY);
		if ((time() - intval($signature)) < (EW_TOKEN_TIME * 60 * 60)) {
			return $payload;
		} else {
			return [];
		}
	} catch (Exception $e) {
		return [];
	}
}

/**
 * Create token
 *
 * @param  array $payload
 * @return string
 */
function createToken($payload)
{
	$token = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '.' . strEncrypt(time(), EW_PASS_KEY);
	$_SESSION['token'] = $token;
	return $token;
}


/**
 * Return a new response from the application.
 *
 * @param  array $data
 * @param  numeric $status
 * @param  array $headers
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

/**
 * Return a new response from the application.
 *
 * @param  array $data
 * @param  numeric  $status
 * @param  array  $headers
 * @return string
 */
function responseJson($data, $status = 200, array $headers = [])
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
 * fn_ErrorLog
 *
 * @param  mixed $error
 */
function fn_ErrorLog($error)
{
	$errno = $error->getCode();
	$errline = $error->getLine();
	$errfile = $error->getFile();
	$errstr = $error->getMessage();
	FileWrite(date("Y-m-d H:i:s") . " - [Error: $errno] $errstr ($errfile: $errline) ", "log/error.txt");
}

/**
 * PHP Error Handler
 *
 * @param  mixed $errno
 * @param  mixed $errstr
 * @param  mixed $errfile
 * @param  mixed $errline
 * @return bool
 */
function fn_ErrorHandler($errno, $errstr, $errfile, $errline)
{
	FileWrite(date("Y-m-d H:i:s") . " - [Error: $errno] $errstr ($errfile: $errline) ", "log/error.txt");
	return true;
}

set_error_handler("fn_ErrorHandler");
DB::$logfile = 'log/sql.txt';