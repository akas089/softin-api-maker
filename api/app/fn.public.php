<?php
include_once ("config.php");
include_once ("db.class.php");

function dbConn()
{
	return new MeekroDB(EW_CONN_HOST, EW_CONN_USER, EW_CONN_PASS, EW_CONN_DB, EW_CONN_PORT);
}

/**
 * Class for TEA encryption/decryption
 */
class cTEA
{

	function long2str($v, $w)
	{
		$len = count($v);
		$s = array();
		for ($i = 0; $i < $len; $i++) {
			$s[$i] = pack("V", $v[$i]);
		}
		if ($w) {
			return substr(join('', $s), 0, $v[$len - 1]);
		} else {
			return join('', $s);
		}
	}

	function str2long($s, $w)
	{
		$v = unpack("V*", $s . str_repeat("\0", (4 - strlen($s) % 4) & 3));
		$v = array_values($v);
		if ($w) {
			$v[count($v)] = strlen($s);
		}
		return $v;
	}

	// Encrypt
	public function Encrypt($str, $key = EW_PASS_KEY)
	{
		if ($str == "") {
			return "";
		}
		$v = $this->str2long($str, true);
		$k = $this->str2long($key, false);
		$cntk = count($k);
		if ($cntk < 4) {
			for ($i = $cntk; $i < 4; $i++) {
				$k[$i] = 0;
			}
		}
		$n = count($v) - 1;
		$z = $v[$n];
		$y = $v[0];
		$delta = 0x9E3779B9;
		$q = floor(6 + 52 / ($n + 1));
		$sum = 0;
		while (0 < $q--) {
			$sum = $this->int32($sum + $delta);
			$e = $sum >> 2 & 3;
			for ($p = 0; $p < $n; $p++) {
				$y = $v[$p + 1];
				$mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
				$z = $v[$p] = $this->int32($v[$p] + $mx);
			}
			$y = $v[0];
			$mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
			$z = $v[$n] = $this->int32($v[$n] + $mx);
		}
		return $this->UrlEncode($this->long2str($v, false));
	}

	// Decrypt
	public function Decrypt($str, $key = EW_PASS_KEY)
	{
		$str = $this->UrlDecode($str);
		if ($str == "") {
			return "";
		}
		$v = $this->str2long($str, false);
		$k = $this->str2long($key, false);
		$cntk = count($k);
		if ($cntk < 4) {
			for ($i = $cntk; $i < 4; $i++) {
				$k[$i] = 0;
			}
		}
		$n = count($v) - 1;
		$z = $v[$n];
		$y = $v[0];
		$delta = 0x9E3779B9;
		$q = floor(6 + 52 / ($n + 1));
		$sum = $this->int32($q * $delta);
		while ($sum != 0) {
			$e = $sum >> 2 & 3;
			for ($p = $n; $p > 0; $p--) {
				$z = $v[$p - 1];
				$mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
				$y = $v[$p] = $this->int32($v[$p] - $mx);
			}
			$z = $v[$n];
			$mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
			$y = $v[0] = $this->int32($v[0] - $mx);
			$sum = $this->int32($sum - $delta);
		}
		return $this->long2str($v, true);
	}

	function int32($n)
	{
		while ($n >= 2147483648)
			$n -= 4294967296;
		while ($n <= -2147483649)
			$n += 4294967296;
		return (int) $n;
	}

	function UrlEncode($string)
	{
		$data = base64_encode($string);
		return str_replace(array('+', '/', '='), array('-', '_', '.'), $data);
	}

	function UrlDecode($string)
	{
		$data = str_replace(array('-', '_', '.'), array('+', '/', '='), $string);
		return base64_decode($data);
	}
}

// Encrypt
function ew_Encrypt($str, $key = EW_PASS_KEY)
{
	$tea = new cTEA;
	return $tea->Encrypt($str, $key);
}

// Decrypt
function ew_Decrypt($str, $key = EW_PASS_KEY)
{
	$tea = new cTEA;
	return $tea->Decrypt($str, $key);
}

// Remove XSS
function ew_RemoveXSS($val)
{
	$EW_XSS_ARRAY = array(
		'javascript',
		'vbscript',
		'expression',
		'<applet',
		'<meta',
		'<xml',
		'<blink',
		'<link',
		'<style',
		'<script',
		'<embed',
		'<object',
		'<iframe',
		'<frame',
		'<frameset',
		'<ilayer',
		'<layer',
		'<bgsound',
		'<title',
		'<base',
		'onabort',
		'onactivate',
		'onafterprint',
		'onafterupdate',
		'onbeforeactivate',
		'onbeforecopy',
		'onbeforecut',
		'onbeforedeactivate',
		'onbeforeeditfocus',
		'onbeforepaste',
		'onbeforeprint',
		'onbeforeunload',
		'onbeforeupdate',
		'onblur',
		'onbounce',
		'oncellchange',
		'onchange',
		'onclick',
		'oncontextmenu',
		'oncontrolselect',
		'oncopy',
		'oncut',
		'ondataavailable',
		'ondatasetchanged',
		'ondatasetcomplete',
		'ondblclick',
		'ondeactivate',
		'ondrag',
		'ondragend',
		'ondragenter',
		'ondragleave',
		'ondragover',
		'ondragstart',
		'ondrop',
		'onerror',
		'onerrorupdate',
		'onfilterchange',
		'onfinish',
		'onfocus',
		'onfocusin',
		'onfocusout',
		'onhelp',
		'onkeydown',
		'onkeypress',
		'onkeyup',
		'onlayoutcomplete',
		'onload',
		'onlosecapture',
		'onmousedown',
		'onmouseenter',
		'onmouseleave',
		'onmousemove',
		'onmouseout',
		'onmouseover',
		'onmouseup',
		'onmousewheel',
		'onmove',
		'onmoveend',
		'onmovestart',
		'onpaste',
		'onpropertychange',
		'onreadystatechange',
		'onreset',
		'onresize',
		'onresizeend',
		'onresizestart',
		'onrowenter',
		'onrowexit',
		'onrowsdelete',
		'onrowsinserted',
		'onscroll',
		'onselect',
		'onselectionchange',
		'onselectstart',
		'onstart',
		'onstop',
		'onsubmit',
		'onunload'
	);

	// Remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed 
	// This prevents some character re-spacing such as <java\0script> 
	// Note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs 

	$val = preg_replace('/([\x00-\x08][\x0b-\x0c][\x0e-\x20])/', '', $val);

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
	$ra = $EW_XSS_ARRAY; // Note: Customize $EW_XSS_ARRAY in ewcfg*.php
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
	return $val;
}

function FileWrite($txt, $file = "")
{
	file_put_contents(($file ? $file : 'log/test.txt'), (is_array($txt) || is_object($txt) ? print_r($txt, true) : $txt) . "\r\n", FILE_APPEND);
}

function customError($errno, $errstr, $errfile, $errline)
{
	FileWrite("Error[$errno]: " . $errstr . ", file: " . $errfile . ", line: " . $errline, "log/error.txt");
}

// Check token
function checkToken($token, $uid)
{
	$mlt = intval(ini_get("session.gc_maxlifetime"));
	//if ($mlt <= 0) $mlt = EW_SESSION_MAXLIFETIME; // PHP default

	$token = json_decode(ew_Decrypt(base64_decode($token), "Big@abc.089"));
	if ($token->uid == $uid) {
		return (time() - intval(ew_Decrypt($token->expiry))) < $mlt;
	} else {
		return false;
	}
}

// Create token
function createToken($uid)
{
	$token = array(
		"uid" => $uid,
		"expiry" => time()
	);
	return base64_encode(ew_Encrypt(json_encode($token), "Big@abc.089"));
}

function arrayToObject($array)
{
	return json_decode(json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function getData($query, $limit = "", $offset = "")
{
	$db = dbConn();
	$query .= (is_numeric($limit) ? " LIMIT " . $limit : "");
	$query .= (is_numeric($offset) ? " OFFSET " . ($offset - 1) : "");
	$data = $db->query($query);
	return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}