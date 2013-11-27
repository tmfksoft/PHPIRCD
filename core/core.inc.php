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
$VAR['ERROR'] = array();
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
	function log($filename,$text,$echo = false) {
		// Loads a logfile and appends data.
		if (file_exists($filename)) {
			$log = explode("\n",file_get_contents($filename));
		} else {
			$log = array();
		}
		$ts = date("h:ia");
		$log[] = "[ ".$ts." ] ".$text;
		if ($echo) echo $text."\n";
		file_put_contents($filename,implode("\n",$log));
		return $text;
	}
	function error($string = false,$type = "WARNING") {
		global $VAR;
		if (!$string) {
			return $VAR['ERROR'];
		} else {
			$VAR['ERROR'][] = array("type"=>$type,"msg"=>$string);
			$this->log("log/ircd.log","[{$type}] {$string}",true);
			return true;
		}
	}
	function handle_errors() {
		global $VAR;
		$fc = 0;
		foreach ($VAR['ERROR'] as $ERR) {
			if ($ERR['type'] == "FATAL") {
				$fc++;
			}
		}
		if ($fc > 0) {
			$this->log("log/ircd.log","Encountered {$fc} FATAL Errors. Aborting.",true);
			shutdown();
		}
	}
}
$core = new core;
?>