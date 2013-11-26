<?php
if (!isset($core)) {
	die("Do not run this file directly!");
}
$_CONFIG = array();
$cfg = new config();

class config {
	public function load_config($filename,$internal,$strict = true) {
		global $core,$_CONFIG;
		// Loads a config file.
		if (!file_exists($filename)) {
			$core->log("log/ircd.log","Configuration file {$filename} does not exist!",true);
		}
		$tmp = array();
		$error = array();
		$inblock = false;
		$blockname = "";
		$stack = array(); // We write our data to this while inside a block. Then we add it to the main data,
		// Config parsing system by Thomas Edwards
		$cfg = explode("\n",file_get_contents($filename));
		foreach ($cfg as $line => $ln) {
			if ($ln != "") {
				$ln = trim($ln);
				$line++;
				$end_char = $ln[strlen($ln)-1];
				$ex = explode(chr(32),$ln);
				// Not a blank line. Aww yeah!
				if ($ln[0] == ";") {
					// Its a comment. Ignore it.
				} else if ($end_char == "{") {
					// Block Start!
					if (!$inblock) {
						$blockname = trim($ex[0]);
						$inblock = true;
					} else {
						// Wehehrheh2e1?Eer3 er.... why are we in a block in a block?
						$error[] = "Error on line {$line} in {$filename}: Unexpected start of block!";
					}
				} else if ($ex[0] == "}") {
					// end of the Block!
					if ($inblock) {
						$inblock = false;
						$bn = strtoupper($blockname);
						if (!isset($tmp[$bn])) {
							$tmp[$bn] = array();
						}
						$tmp[$bn][] = $stack;
						$stack = array(); // Reset it.
						$blockname = "";
					} else {
						$error[] = "Error on line {$line} in {$filename}: Unexpected end of block!";
					}
				} else {
					// Must be an item.
					if ($end_char == ";") {
						// Certainly is. Lets check its syntax.
						if ($ex[1] == "=") {
							// All is fine
							$stack[strtoupper($ex[0])] = substr(implode(chr(32),array_slice($ex,2)),0,-1);
						} else {
							$error[] = "Error on line {$line} in {$filename}: Expecting EQUALTO character '='!";
						}
					} else {
						// Missing the ; SYNTAX ERROR!
						$error[] = "Error on line {$line} in {$filename}: Expecting end line character ';'!";
					}
				}
			}
		}
		if (count($error) > 0) {
			// We entcountered errors.
			$core->log("log/ircd.log","Encountered ".count($error)." errors when parsing {$filename}",true);
			foreach ($error as $e) {
				$core->log("log/ircd.log","Config Error: {$e}",true);
			}
			return false;
		} else {
			// Merge the temp config to the real one.
			$_CONFIG[$internal] = $tmp;
			return true;
		}
	}
}
?>