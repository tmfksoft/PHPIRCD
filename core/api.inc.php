<?php
$stack = array();
class api {
	public function stack($string) {
		global $stack;
		$stack[] = $string;
		return true;
	}
	public function stack_deploy($cid) {
		global $clients,$core;
		if (isset($clients[$cid])) {
			foreach ($stack as $ln) {
				// Write the data to the socket.
				fwrite($clients[$cid]['socket'],$ln."\n");
			}
		} else {
			// Error!
			$core->error("Unable to deploy write stack to client. No such client!","WARNING");
		}
	}
}
?>