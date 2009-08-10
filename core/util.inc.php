<?php
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Input / Output Sanitising                                                 *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * Make some data safe for printing into HTML
 *
 * @retval string
 */
function html_escape($input) {
	return htmlentities($input, ENT_QUOTES, "UTF-8");
}

/**
 * Make sure some data is safe to be used in integer context
 *
 * @retval int
 */
function int_escape($input) {
	return (int)$input;
}

/**
 * Make sure some data is safe to be used in URL context
 *
 * @retval string
 */
function url_escape($input) {
	$input = str_replace('^', '^^', $input);
	$input = str_replace('/', '^s', $input);
	$input = rawurlencode($input);
	return $input;
}

/**
 * Make sure some data is safe to be used in SQL context
 *
 * @retval string
 */
function sql_escape($input) {
	global $database;
	return $database->db->Quote($input);
}

/**
 * Turn a human readable filesize into an integer, eg 1KB -> 1024
 *
 * @retval int
 */
function parse_shorthand_int($limit) {
	if(is_numeric($limit)) {
		return (int)$limit;
	}

	if(preg_match('/^([\d\.]+)([gmk])?b?$/i', "$limit", $m)) {
		$value = $m[1];
		if (isset($m[2])) {
			switch(strtolower($m[2])) {
				case 'g': $value *= 1024;  # fallthrough
				case 'm': $value *= 1024;  # fallthrough
				case 'k': $value *= 1024; break;
				default: $value = -1;
			}
		}
		return (int)$value;
	} else {
		return -1;
	}
}

/**
 * Turn an integer into a human readable filesize, eg 1024 -> 1KB
 *
 * @retval string
 */
function to_shorthand_int($int) {
	if($int >= pow(1024, 3)) {
		return sprintf("%.1fGB", $int / pow(1024, 3));
	}
	else if($int >= pow(1024, 2)) {
		return sprintf("%.1fMB", $int / pow(1024, 2));
	}
	else if($int >= 1024) {
		return sprintf("%.1fKB", $int / 1024);
	}
	else {
		return "$int";
	}
}


/**
 * Turn a date into a time, a date, an "X minutes ago...", etc
 *
 * @retval string
 */
function autodate($date) {
	$diff = time() - strtotime($date);
	if ($diff<60) return $diff . " second" . plural($diff) . " ago"; $diff = round($diff/60);
	if ($diff<60) return $diff . " minute" . plural($diff) . " ago"; $diff = round($diff/60);
	if ($diff<24) return $diff . " hour" . plural($diff) . " ago";   $diff = round($diff/24);
	if ($diff<7)  return $diff . " day" . plural($diff) . " ago";    $diff = round($diff/7);
	if ($diff<4)  return $diff . " week" . plural($diff) . " ago";
	return "on " . date("F j, Y", strtotime($date));
}


/**
 * Return a pluraliser if necessary
 *
 * @retval string
 */
function plural($num, $single_form="", $plural_form="s") {
	return ($num == 1) ? $single_form : $plural_form;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* HTML Generation                                                           *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * Figure out the correct way to link to a page, taking into account
 * things like the nice URLs setting
 *
 * @retval string
 */
function make_link($page=null, $query=null) {
	global $config;

	if(is_null($page)) $page = $config->get_string('main_page');

	if($config->get_bool('nice_urls', false)) {
		#$full = "http://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
		$full = $_SERVER["PHP_SELF"];
		$base = str_replace("/index.php", "", $full);
	}
	else {
		$base = "./index.php?q=";
	}

	if(is_null($query)) {
		return str_replace("//", "/", "$base/$page");
	}
	else {
		if(strpos($base, "?")) {
			return "$base/$page&$query";
		}
		else {
			return "$base/$page?$query";
		}
	}
}

/**
 * Turn a relative link into an absolute one, including hostname
 *
 * @retval string
 */
function make_http($link) {
	if(strpos($link, "ttp://") > 0) return $link;
	if($link[0] != '/') $link = get_base_href().'/'.$link;
	$link = "http://".$_SERVER["HTTP_HOST"].$link;
	$link = str_replace("/./", "/", $link);
	return $link;
}

/**
 * Make a link to a static file in the current theme's
 * directory
 */
function theme_file($filepath) {
	global $config;
	$theme = $config->get_string("theme","default");
	return make_link("themes/$theme/$filepath");
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Misc                                                                      *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * @private
 */
function _version_check() {
	if(version_compare(PHP_VERSION, "5.0.0") == -1) {
		print "
Currently SCore Engine doesn't support versions of PHP lower than 5.0.0 --
PHP4 and earlier are officially dead according to their creators,
please tell your host to upgrade.
";
		exit;
	}
}

/**
 * @private
 */
function check_cli() {
	if(isset($_SERVER['REMOTE_ADDR'])) {
		print "This script is to be run from the command line only.";
		exit;
	}
	$_SERVER['REMOTE_ADDR'] = "127.0.0.1";
}

/**
 * $db is the connection object
 *
 * @private
 */
function _count_execs($db, $sql, $inputarray) {
	global $_execs;
	if(DEBUG) {
		$fp = fopen("sql.log", "a");
		if(is_array($inputarray)) {
			fwrite($fp, preg_replace('/\s+/msi', ' ', $sql)." -- ".join(", ", $inputarray)."\n");
		}
		else {
			fwrite($fp, preg_replace('/\s+/msi', ' ', $sql)."\n");
		}
		fclose($fp);
	}
	if (!is_array($inputarray)) $_execs++;
	# handle 2-dimensional input arrays
	else if (is_array(reset($inputarray))) $_execs += sizeof($inputarray);
	else $_execs++;
	# in PHP4.4 and PHP5, we need to return a value by reference
	$null = null; return $null;
}

/**
 * Find the theme object for a given extension
 */
function get_theme_object(Extension $class, $fatal=true) {
	$base = get_class($class);
	if(class_exists("Custom{$base}Theme")) {
		$class = "Custom{$base}Theme";
		return new $class();
	}
	elseif ($fatal || class_exists("{$base}Theme")) {
		$class = "{$base}Theme";
		return new $class();
	} else {
		return false;
	}
}

/**
 * Compare two Block objects, used to sort them before being displayed
 *
 * @retval int
 */
function blockcmp(Block $a, Block $b) {
	if($a->position == $b->position) {
		return 0;
	}
	else {
		return ($a->position > $b->position);
	}
}

/**
 * Figure out PHP's internal memory limit
 *
 * @retval int
 */
function get_memory_limit() {
	global $config;

	// thumbnail generation requires lots of memory
	$default_limit = 8*1024*1024;
	$shimmie_limit = parse_shorthand_int($config->get_int("thumb_mem_limit"));
	if($shimmie_limit < 3*1024*1024) {
		// we aren't going to fit, override
		$shimmie_limit = $default_limit;
	}

	ini_set("memory_limit", $shimmie_limit);
	$memory = parse_shorthand_int(ini_get("memory_limit"));

	// changing of memory limit is disabled / failed
	if($memory == -1) {
		$memory = $default_limit;
	}

	assert($memory > 0);

	return $memory;
}

/**
 * Get the currently active IP, masked to make it not change when the last
 * octet or two change, for use in session cookies and such
 *
 * @retval string
 */
function get_session_ip($config) {
    $mask = $config->get_string("session_hash_mask", "255.255.0.0");
    $addr = $_SERVER['REMOTE_ADDR'];
    $addr = inet_ntop(inet_pton($addr) & inet_pton($mask));
    return $addr;
}

/**
 * Figure out the path to the shimmie install root.
 *
 * PHP really, really sucks.
 *
 * @retval string
 */
function get_base_href() {
	$possible_vars = array('SCRIPT_NAME', 'PHP_SELF', 'PATH_INFO', 'ORIG_PATH_INFO');
	$ok_var = null;
	foreach($possible_vars as $var) {
		if(substr($_SERVER[$var], -4) == '.php') {
			$ok_var = $_SERVER[$var];
			break;
		}
	}
	assert(!empty($ok_var));
	$dir = dirname($ok_var);
	if($dir == "/" || $dir == "\\") $dir = "";
	return $dir;
}

/**
 * A shorthand way to send a TextFormattingEvent and get the
 * results
 *
 * @retval string
 */
function format_text($string) {
	$tfe = new TextFormattingEvent($string);
	send_event($tfe);
	return $tfe->formatted;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Logging convenience                                                       *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

if(!defined("LOG_CRITICAL")) define("LOG_CRITICAL", 50);
if(!defined("LOG_ERROR"))    define("LOG_ERROR", 40);
if(!defined("LOG_WARNING"))  define("LOG_WARNING", 30);
if(!defined("LOG_INFO"))     define("LOG_INFO", 20);
if(!defined("LOG_DEBUG"))    define("LOG_DEBUG", 10);
if(!defined("LOG_NOTSET"))   define("LOG_NOTSET", 0);

/**
 * A shorthand way to send a LogEvent
 */
function log_msg($section, $priority, $message) {
	send_event(new LogEvent($section, $priority, $message));
}

/**
 * A shorthand way to send a LogEvent
 */
function log_info($section, $message) {
	log_msg($section, LOG_INFO, $message);
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Things which should be in the core API                                    *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * Remove an item from an array
 *
 * @retval array
 */
function array_remove($array, $to_remove) {
	$array = array_unique($array);
	$a2 = array();
	foreach($array as $existing) {
		if($existing != $to_remove) {
			$a2[] = $existing;
		}
	}
	return $a2;
}

/**
 * Add an item to an array
 *
 * @retval array
 */
function array_add($array, $element) {
	$array[] = $element;
	$array = array_unique($array);
	return $array;
}

/**
 * Return the unique elements of an array, case insensitively
 *
 * @retval array
 */
function array_iunique($array) {
	$ok = array();
	foreach($array as $element) {
		$found = false;
		foreach($ok as $existing) {
			if(strtolower($element) == strtolower($existing)) {
				$found = true; break;
			}
		}
		if(!$found) {
			$ok[] = $element;
		}
	}
	return $ok;
}

/**
 * Figure out if an IP is in a specified range
 *
 * from http://uk.php.net/network
 *
 * @retval bool
 */
function ip_in_range($IP, $CIDR) {
	list ($net, $mask) = split ("/", $CIDR);

	$ip_net = ip2long ($net);
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	$ip_ip = ip2long ($IP);

	$ip_ip_net = $ip_ip & $ip_mask;

	return ($ip_ip_net == $ip_net);
}

/**
 * Delete an entire file heirachy
 *
 * from a patch by Christian Walde; only intended for use in the
 * "extension manager" extension, but it seems to fit better here
 */
function deltree($f) {
	if (is_link($f)) {
		unlink($f);
	}
	else if(is_dir($f)) {
		foreach(glob($f.'/*') as $sf) {
			if (is_dir($sf) && !is_link($sf)) {
				deltree($sf);
			} else {
				unlink($sf);
			}
		}
		rmdir($f);
	}
}

/**
 * Copy an entire file heirachy
 *
 * from a comment on http://uk.php.net/copy
 */
function full_copy($source, $target) {
	if(is_dir($source)) {
		@mkdir($target);

		$d = dir($source);

		while(FALSE !== ($entry = $d->read())) {
			if($entry == '.' || $entry == '..') {
				continue;
			}

			$Entry = $source . '/' . $entry;
			if(is_dir($Entry)) {
				full_copy($Entry, $target . '/' . $entry);
				continue;
			}
			copy($Entry, $target . '/' . $entry);
		}
		$d->close();
	}
	else {
		copy($source, $target);
	}
}

/**
 * @private
 */
function weighted_random($weights) {
	$total = 0;
	foreach($weights as $k => $w) {
		$total += $w;
	}

	$r = mt_rand(0, $total);
	foreach($weights as $k => $w) {
		$r -= $w;
		if($r <= 0) {
			return $k;
		}
	}
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Event API                                                                 *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/** @private */
$_event_listeners = array();

/**
 * Register an Extension
 */
function add_event_listener(Extension $extension, $pos=50) {
	global $_event_listeners;
	while(isset($_event_listeners[$pos])) {
		$pos++;
	}
	$_event_listeners[$pos] = $extension;
}

/** @private */
$_event_count = 0;

/**
 * Send an event to all registered Extensions
 */
function send_event(Event $event) {
	global $_event_listeners, $_event_count;
	$my_event_listeners = $_event_listeners; // http://bugs.php.net/bug.php?id=35106
	ksort($my_event_listeners);
	foreach($my_event_listeners as $listener) {
		$listener->receive_event($event);
	}
	$_event_count++;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Debugging functions                                                       *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function get_debug_info() {
	global $config, $_event_count;

	if(function_exists('memory_get_usage')) {
		$i_mem = sprintf("%5.2f", ((memory_get_usage()+512)/1024)/1024);
	}
	else {
		$i_mem = "???";
	}
	if(function_exists('getrusage')) {
		$ru = getrusage();
		$i_utime = sprintf("%5.2f", ($ru["ru_utime.tv_sec"]*1e6+$ru["ru_utime.tv_usec"])/1000000);
		$i_stime = sprintf("%5.2f", ($ru["ru_stime.tv_sec"]*1e6+$ru["ru_stime.tv_usec"])/1000000);
	}
	else {
		$i_utime = "???";
		$i_stime = "???";
	}
	$i_files = count(get_included_files());
	global $_execs;
	global $database;
	$hits = $database->cache->get_hits();
	$miss = $database->cache->get_misses();
	$debug = "<br>Took $i_utime + $i_stime seconds and {$i_mem}MB of RAM";
	$debug .= "; Used $i_files files and $_execs queries";
	$debug .= "; Sent $_event_count events";
	$debug .= "; $hits cache hits and $miss misses";

	return $debug;
}

// print_obj ($object, $title, $return)
function print_obj($object,$title="Object Information", $return=false) {
	global $user;
	if(DEBUG && isset($_GET['debug']) && $user->is_admin()) {
		$pr = print_r($object,true);
		$count = substr_count($pr,"\n")<=25?substr_count($pr,"\n"):25;
		$pr = "<textarea rows='".$count."' cols='80'>$pr</textarea>";

		if($return) {
			return $pr;
		} else {
			global $page;
			$page->add_block(new Block($title,$pr,"main",1000));
			return true;
		}
	}
}

// preset tests.

// Prints the contents of $event->args, even though they are clearly visible in
// the URL bar.
function print_url_args() {
	global $event;
	print_obj($event->args,"URL Arguments");
}

// Prints all the POST data.
function print_POST() {
	print_obj($_POST,"\$_POST");
}

// Prints GET, though this is also visible in the url ( url?var&var&var)
function print_GET() {
	print_obj($_GET,"\$_GET");
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Request initialisation stuff                                              *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/** @privatesection */

function _stripslashes_r($arr) {
	return is_array($arr) ? array_map('_stripslashes_r', $arr) : stripslashes($arr);
}

function _sanitise_environment() {
	if(DEBUG) {
		error_reporting(E_ALL);
		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_BAIL, 1);
	}

	ob_start();

	if(get_magic_quotes_gpc()) {
		$_GET = _stripslashes_r($_GET);
		$_POST = _stripslashes_r($_POST);
		$_COOKIE = _stripslashes_r($_COOKIE);
	}
}

/**
 * Turn ^^ into ^ and ^s into /
 *
 * Necessary because various servers and various clients
 * think that / is special...
 */
function _decaret($str) {
	$out = "";
	for($i=0; $i<strlen($str); $i++) {
		if($str[$i] == "^") {
			$i++;
			if($str[$i] == "^") $out .= "^";
			if($str[$i] == "s") $out .= "/";
		}
		else {
			$out .= $str[$i];
		}
	}
	return $out;
}

function _get_query_parts() {
	if(isset($_GET["q"])) {
		$path = $_GET["q"];
	}
	else if(isset($_SERVER["PATH_INFO"])) {
		$path = $_SERVER["PATH_INFO"];
	}
	else {
		$path = "";
	}

	while(strlen($path) > 0 && $path[0] == '/') {
		$path = substr($path, 1);
	}

	$parts = split('/', $path);

	if(strpos($path, "^") === FALSE) {
		return $parts;
	}
	else {
		$unescaped = array();
		foreach($parts as $part) {
			$unescaped[] = _decaret($part);
		}
		return $unescaped;
	}
}

function _get_page_request() {
	global $config;
	$args = _get_query_parts();

	if(count($args) == 0 || strlen($args[0]) == 0) {
		$args = split('/', $config->get_string('front_page'));
	}

	return new PageRequestEvent($args);
}

function _get_user() {
	global $config, $database;
	$user = null;
	if(isset($_COOKIE["shm_user"]) && isset($_COOKIE["shm_session"])) {
	    $tmp_user = User::by_session($_COOKIE["shm_user"], $_COOKIE["shm_session"]);
		if(!is_null($tmp_user)) {
			$user = $tmp_user;
		}
	}
	if(is_null($user)) {
		$user = User::by_id($config->get_int("anon_id", 0));
	}
	assert(!is_null($user));
	return $user;
}


$_cache_hash = null;
$_cache_memcache = false;
$_cache_filename = null;

function _cache_active() {
	return ((CACHE_MEMCACHE || CACHE_DIR) && !isset($_COOKIE["shm_session"]) && !isset($_COOKIE["shm_nocache"]));
}

function _start_cache() {
	global $_cache_hash, $_cache_memcache, $_cache_filename;

	if(_cache_active()) {
		$_cache_hash = md5($_SERVER["QUERY_STRING"]);

		if(CACHE_MEMCACHE) {
			$_cache_memcache = new Memcache;
			$_cache_memcache->pconnect('localhost', 11211);
			$zdata = $_cache_memcache->get($hash);
			if($zdata) {
				header("Content-type: text/html");
				$data = @gzuncompress($zdata);
				if($data) {
					print $data;
					exit;
				}
			}
		}

		if(CACHE_DIR) {
			$ab = substr($_cache_hash, 0, 2);
			$cd = substr($_cache_hash, 2, 2);
			$_cache_filename = "data/$ab/$cd/$_cache_hash";

			if(!file_exists(dirname($_cache_filename))) {
				mkdir(dirname($_cache_filename), 0750, true);
			}
			if(file_exists($_cache_filename) && (filemtime($cachename) > time() - 3600)) {
				$gmdate_mod = gmdate('D, d M Y H:i:s', filemtime($_cache_filename)) . ' GMT';

				if(isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
					$if_modified_since = preg_replace('/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"]);

					if($if_modified_since == $gmdate_mod) {
						header("HTTP/1.0 304 Not Modified");
						header("Content-type: text/html");
						exit;
					}
				}
				else {
					header("Content-type: text/html");
					header("Last-Modified: $gmdate_mod");
					$zdata = @file_get_contents($_cache_filename);
					if(CACHE_MEMCACHE) {
						$_cache_memcache->set($_cache_hash, $zdata, 0, 600);
					}
					$data = @gzuncompress($zdata);
					if($data) {
						print $data;
						exit;
					}
				}
			}
			ob_start();
		}
	}
}

function _end_cache() {
	global $_cache_hash, $_cache_memcache, $_cache_filename;

	if(_cache_active()) {
		$data = gzcompress(ob_get_contents(), 9);
		if(CACHE_MEMCACHE) {
			$_cache_memcache->set($_cache_hash, $data, 0, 600);
		}
		if(CACHE_DIR) {
			file_put_contents($_cache_filename, $data);
		}
	}
}
?>
