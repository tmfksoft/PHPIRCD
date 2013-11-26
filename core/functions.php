<?php
function gen_str($length = 5) {
	$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
	$chars = str_split($chars);
	for ($i = 0; $i < $length; $i++ ) {
		$ret .= $chars[array_rand($chars)];
	}
	return $chars;
}
global $VAR;
class core {
	function sendMOTD($socket) {
		global $config;
		$motd = explode("\n",file_get_contents($config['motd']));
		$return[] = ":{$config['netaddr']} 375 {$users[$user]['nick']} :- {$config['netaddr']} Message of the Day -";
		$return[] = ":{$config['netaddr']} 372 {$users[$user]['nick']} :- ".date('d/n/Y')." ".date('G:i');
		foreach ($motd as $line) {
			$return[] = ":{$config['netaddr']} 372 {$users[$user]['nick']} :- ".$line;
		}
	}
	function buffer($text = false) {
		global $VAR;
		if ($text) {
			$VAR['buffer'][] = $text;
		} else {
			return $VAR['BUFFER'];
		}
	}
	function log($filename,$text,$echo = false) {
		// Loads a logfile and appends data.
		if (file_exists("../log/".$filename)) {
			$log = explode("\n",file_get_contents("../log/".$filename));
		} else {
			$log = array();
		}
		$log[] = $text;
		if ($echo) echo $text."\n";
		file_put_contents("../log/".$filename,implode("\n",$log));
		return $text;
	}
}
$core = new core;
$_CONFIG = array();
class config {
	function load_config($fname) {
		// Loads a config.
	}
	function get($section,$item) {
		global $_CONFIG;
		// Gets an item from a section
		// Pass NULL from the section to get the item from the main config.
	}
}
?>