<?php
// Set time limit to indefinite execution
set_time_limit (0);
declare(ticks = 1);

pcntl_signal(SIGINT, "shutdown");
pcntl_signal(SIGTERM, "shutdown");
pcntl_signal(SIGHUP,  "shutdown");
pcntl_signal(SIGUSR1, "shutdown");

register_shutdown_function('shutdown');
// Set the ip and port we will listen on
$address = 'let.fudgie.undo.it';
$port = $argv[1];
$max_clients = 10;
$config = array();
$config['netname'] = "FudgieIRC";
$config['netaddr'] = "let.fudgie.undo.it";
$config['srvid'] = "1";
$config['srvdesc'] = "An IRCD in PHP";
$config['open'] = true; // Is the server open to connections? Toggle with: /
$config['cloak'] = "htnruy54byuhbgdsghvdbl4HRGHRTt4";

$clients = array();
$socket = socket_create(AF_INET,SOCK_STREAM,0);
socket_set_nonblock($socket);

if (!$socket) {
	die('Error creating socket.');
}
@socket_bind($socket,$address,$port) or die("Cannot Bind\n");
socket_listen($socket) or die("Cannot Listen\n");


echo "{$socket} is the master socket on port {$port}\n";

// Supported modes
$user_modes = "x";
$chan_modes = "nt";
$ulist_modes = "qaohv";

$users = array();
$users[0] = array("connected"=>false);
$channels = array();
$opers = array();

// Add an oper.
$opr = array();
$opr['user'] = "tmfksoft";
$opr['pass'] = "";
$opr['flags'] = array("restart","rehash","shutdown","kill");
$opers[] = $opr;
unset($opr);

class user {
	function get($uid,$item = false) {
		if ($this->exists($uid)) {
			global $users;
			if (!$item) {
				return $users[$uid];
			}
			else {
				return $users[$uid][$item];
			}
		}
	}
	function set($uid,$item,$val = false) {
		if ($this->exists($uid)) {
			global $users;
			$users[$uid][$item] = $val;
			return true;
		}
	}
	function exists($uid) {
		global $users;
		if (isset($users[$uid])) {
			return true;
		}
		else {
			return false;
		}
	}
	function isOper($uid) {
		if ($this->exists($uid)) {
			// Get modes
			$isop = false;
			$modes = $this->get($uid,"modes");
			$modes = str_split($modes);
			echo "Checking if a user is an oper.\n";
			foreach ($modes as $mode) {
				if ($mode === "O" || $mode === "A") {
					$isop = true;
					echo "They ARE.\n";
				}
			}
			return $isop;
		}
	}
	function getNick($uid) {
		global $users;
		if ($this->exists($uid)) {
			return $this->get($uid,"nick");
		}
	}
	function getUsername($uid) {
		global $users;
		if (isset($users[$uid])) {
			return $users[$uid]['nick'];
		}
		else {
			return false;
		}
	}
	function resetidle($uid) {
		if ($this->exists($uid)) {
			$this->set($uid,"idle",time());
		}
	}
	function getUid($nick) {
		global $users;
		$user_id = false;
		foreach ($users as $id => $u) {
			if ($u['connected']) {
				// Make sure they're connected.
				if (strtolower($u['nick']) === strtolower($nick)) {
					$user_id = $id;
					echo "We found {$nick}!!\n";
				}
			}
		}
		if ($user_id) {
			return $user_id;
		}
		else {
			return false;
		}
	}
	// Mode Related.
	function addMode($id,$mode) {
		// We're assuming previous code is checking if we can add the mode.
		// Returns true or false, False signifies the mode exists.
		$c_modes = str_split($this->get($id,"modes")); // Blow it into different characters.
		$exists = array_search($mode,$c_modes); // Case sensitive too.
		if (!$exists) {
			// Mode doesnt exist add it.
			$c_modes[] = $mode;
			$c_modes = implode('',$c_modes);
			$u->set($id,"modes",$c_modes);
			return true;
		}
		else {
			return false; // Mode exists cant set whats already there.
		}
	}
	function delMode($id,$mode) {
		// Similar to addMode but we remove the mode if it exists.
		$c_modes = str_split($this->get($id,"modes"));
		$exists = array_search($mode,$c_cmodes);
		if (!$exists) {
			return false; // Cant remove what dont exist.
		}
		else {
			unset($c_modes[$exists]); // As its stored the key of the mode we can EASILY remove it!
			$c_modes = implode('',$c_modes);
			$u->set($id,"modes",$c_modes);
			return true;
		}
	}
	function hasMode($id,$mode) {
		// Like above except we dont touch them.
		$c_modes = str_split($this->get($id,"modes"));
		$exists = array_search($mode,$c_modes);
		if (!$exists) {
			return false;
		}
		else {
			return true;
		}
	}
}
class channel {
	function create($name) {
		global $channels;
		$chan = array();
		$chan['name'] = $name;
		$chan['stamp'] = time();
		$chan['topic'] = "Default Topic for {$name}";
		$chan['modes'] = "nt";
		$chan['userlist'] = array();
		$chan['bans'] = array();
		$channels[] = $chan;
		return count($channels)-1; // New ID.
	}
	function get($id,$item) {
		global $channels;
		if ($this->exists($id)) {
			return $channels[$id][$item];
		}
	}
	function exists($id) {
		global $channels;
		if (isset($channels[$id])) {
			return true;
		}
		else {
			return false;
		}
	}
	function add_user($id,$uid,$modes = "") {
		global $channels,$users;
		if ($this->exists($id)) {
			// channel exists.
			$channels[$id]['userlist'][] = array("id"=>$uid,"modes"=>$modes);
			echo " ### {$users[$uid]['nick']}({$uid}) has joined {$channels[$id]['name']} ### \n";
		}
		else {
			echo "ERROR";
		}
	}
	function getID($name) {
		global $channels;
		$chan_id = false;
		foreach ($channels as $id => $c) {
			// Make sure they're connected.
			if (strtolower($c['name']) === strtolower($name)) {
				$chan_id = $id;
			}
		}
		if ($chan_id !== FALSE) {
			return $chan_id;
		}
		else {
			return false;
		}
	}
	function hasmode($id,$mode) {
		$modes = str_split($this->get($id,"modes"));
		$has = false;
		foreach ($modes as $m) {
			if ($m === $mode) {
				$has = true;
			}
		}
		return $has;
	}
}
$c = new channel;
$u = new user;



while(true)
{
	$to_read = array();
	$to_read[] = $socket;
	$to_read = array_merge($to_read,$clients);
	array_values($to_read);
	
	$changes = @socket_select($to_read,$a = NULL,$b = NULL,0);

	if ($changes > 0) {
		$newc = @socket_accept($socket);
		if ($newc !== false) {
			$users[] = array("connected"=>false,"socket"=>$newc);
			echo "Client $newc has connected with UID ".count($users)."\n\r";
			socket_set_nonblock($newc);
			$clients[] = $newc;
		}
		foreach ($clients as $id => $sock) {
			$input = socket_read($sock,1024);
			if ($input == null) {
				//echo "{$sock} is closed.\n\r";
				//unset($clients[$id]);
			}
			$data = trim($input);
			$buffer = array();
			if ($input && $input != "") {
				$proc = explode("\n",$input);
				echo "The Client sent ".count($proc)." lines of text\n";
				foreach ($proc as $text) {
					$text = trim($text);
					if (strlen($text) > 0) {
						echo "START <{$text}> END\n";
						$res = handle_data($text,$sock,$id+1);
						$buffer = array_merge($buffer,$res);
						array_values($buffer);
					}
				}
			}
			if (count($buffer) > 0) {
				$buf = "";
				foreach ($buffer as $text) {
					$buf .= $text."\n\r";
				}
				echo "We send ".count($buffer)." lines of text to the user.\n";
				socket_write($sock,$buf,strlen($buf));
			}
		}
	}
}
function handle_data($string,$sock,$user) {
	global $users,$channels,$config,$u,$c;
	$return = array();
	$ex = explode(" ",$string);
	if (isset($ex[0])) {
		$ex[0] = strtoupper($ex[0]);
	}
	if ($ex[0] == "USER") {
		$users[$user]['connected'] = true;
		socket_getpeername($sock,$addr);
		$user_host = gethostbyaddr($addr);
		$users[$user]['uncloaked'] = $user_host;
		$users[$user]['ip'] = $addr;
		$users[$user]['cloaked'] = "Cloaked-".substr(md5($user_host.$config['cloak']),0,15).".host";
		$users[$user]['realname'] = substr(implode(chr(32),array_slice($ex,4)),1);
		$users[$user]['username'] = $ex[1];
		$users[$user]['modes'] = "iRx";
		$users[$user]['shunned'] = false;
		$users[$user]['idle'] = time();
		$users[$user]['signon'] = time();
		$users[$user]['server'] = $config['srvid'];
		$return[] = ":{$config['netaddr']} 001 {$users[$user]['nick']} :Welcome to the {$config['netname']} IRC Network ".$users[$user]['nick']."!{$ex[1]}@".$users[$user]['cloaked'];
		echo "USER {$user} is now connected.\n";
		// Drop them a MOTD.
		$motd = explode("\n",file_get_contents("motd.txt"));
		$return[] = ":{$config['netaddr']} 375 {$users[$user]['nick']} :- {$config['netaddr']} Message of the Day -";
		foreach ($motd as $line) {
			$return[] = ":{$config['netaddr']} 372 {$users[$user]['nick']} :- ".$line;
		}
		$return[] = ":{$config['netaddr']} 376 {$users[$user]['nick']} :End of /MOTD command.";
	}
	else if ($ex[0] == "NICK") {
		$users[$user]['nick'] = $ex[1];
		echo "{$user} set their nickname to {$ex[1]}\n";
	}
	else if ($ex[0] == "MOTD") {
	// Drop them a MOTD.
		$motd = explode("\n",file_get_contents("motd.txt"));
		$return[] = ":{$config['netaddr']} 375 {$users[$user]['nick']} :- {$config['netaddr']} Message of the Day -";
		foreach ($motd as $line) {
			$return[] = ":{$config['netaddr']} 372 {$users[$user]['nick']} :- ".$line;
		}
		$return[] = ":{$config['netaddr']} 376 {$users[$user]['nick']} :End of /MOTD command.";
	}
	else if ($ex[0] == "PING") {
		$return[] = "PONG ".$ex[1];
	}
	else if ($ex[0] == "DING") {
		$return[] = ":{$config['netaddr']} NOTICE :DONG!";
		echo "DING DONG! @ {$u->getNick($user)}\n";
	}
	else if ($ex[0] == "JOIN") {
		if ($u->hasMode($user,"x")) {
			// Is cloaked.
			$hostmask = $u->get($user,"nick")."!".$u->get($user,"username")."@".$u->get($user,"cloaked");
		}
		else {
			$hostmask = $u->get($user,"nick")."!".$u->get($user,"username")."@".$u->get($user,"uncloaked");
		}
		$tojoin = explode(",",$ex[1]);
		foreach ($tojoin as $thechan) {
			$chan_id = $c->getID($thechan);
			$made = false;
			if ($chan_id !== FALSE) {
				// Channel DOES exist
				echo "{$ex[1]} Does EXIST!\n";
				$c->add_user($chan_id,$user);
			}
			else {
				echo "{$ex[1]} Doesnt exist. Creating it.\n";
				$made = true;
				$chan_id = $c->create($thechan);
				$c->add_user($chan_id,$user,"o");
			}
			
			// Stuff the end user sees regarless.
			$return[] = ":{$hostmask} JOIN :".$thechan;
			// Topic
			if (!$made) {	
				// Topic exists
				$return[] = ":{$config['netaddr']} 332 {$u->get($user,"nick")} {$thechan} :".$c->get($chan_id,"topic");
			}
			
			// UserList
			$names = array();
			var_dump($channels[$chan_id]['userlist']);
			
			foreach ($channels[$chan_id]['userlist'] as $lid => $ulist) {
				$list_id = $ulist['id'];
				echo "USERLIST. ADDING USER {$list_id} FOR {$thechan}\n";
				
				if (!$made) {
					$u_modes = mode_to_sym($ulist['modes']);
				}
				else {
					if ($list_id == $user) {
						$u_modes = "@";
						$channels[$chan_id]['userlist'][$lid]['modes'] = "o";
					}
				}
				
				if ($u->hasMode($list_id,"x")) {
					$str = $u_modes.$u->get($list_id,"nick")."!".$u->get($list_id,"username")."@".$u->get($list_id,"cloaked");
				}
				else {
					$str = $u_modes.$u->get($list_id,"nick")."!".$u->get($list_id,"username")."@".$u->get($list_id,"uncloaked");
				}
				echo $str."\n";
				$names[] = $str;
			}
			$return[] = ":{$config['netaddr']} 353 {$u->get($user,"nick")} = {$thechan} :".implode(chr(32),$names);
			$return[] = ":{$config['netaddr']} 366 {$u->get($user,"nick")} {$thechan} :End of /NAMES list.";
			// Modes.
			$return[] = ":{$config['netaddr']} 324 {$u->get($user,"nick")} {$thechan} +".$c->get($chan_id,"modes");
			
			foreach ($channels[$chan_id]['userlist'] as $ulist) {
				// Cycles the userlist
				if ($ulist['id'] !== $user) {
					$victim = $ulist['id'];
					$vic_sock = $users[$victim]['socket'];
					$str = ":{$hostmask} JOIN :".$thechan."\n\r";
					socket_write($vic_sock,$str,strlen($str));
				}
			}
		}
	}
	else if ($ex[0] == "MODE") {
		// We're changing a mode.
		if (isset($ex[1])) {
			$subject = $ex[1];
			if ($subject[0] == "#") {
				// Channel. We'll deal with this another time
			}
			else {
				// User.
				$target = $u->getUid($subject);
				if ($target && $target == $user) {
					// User is editing their modes.
					if (!isset($ex[2]) || $ex[2] == "") {
						// Tell the user their modes.
						$return[] = ":{$config['netaddr']} 221 {$u->getNick($user)} +".$u->get($user,"modes");
					}
					else {
						// We're actually dealing with modes :c
					}
				}
				// If its not me. Give no shits.
			}
		}
		else {
			$return[] = ":{$config['netaddr']} 461 {$u->getNick($user)} MODE :not enough parameters";
		}
	}
	else if ($ex[0] == "WHOIS") {
		// Get their UID
		$victim = $u->getUid($ex[1]);
		echo "{$user} ASKED FOR INFO FOR USER {$victim}\n";
		if ($victim != FALSE) {
			$authority = false; // Has authority to see IP and Modes?
			if ($user == $victim) {
				$authority = true;
			}
			if ($u->hasMode($victim,"x")) {
				$return[] = ":{$config['netaddr']} 311 {$u->getNick($user)} {$u->getNick($victim)} {$u->get($victim,"username")} {$u->get($victim,"cloaked")} * :".$u->get($victim,"realname");
			}
			else {
				// They're not running cloaked.
				$return[] = ":{$config['netaddr']} 311 {$u->getNick($user)} {$u->getNick($victim)} {$u->get($victim,"username")} {$u->get($victim,"uncloaked")} * :".$u->get($victim,"realname");
			}
			if ($authority) {
				$return[] = ":{$config['netaddr']} 378 {$u->getNick($user)} {$u->getNick($victim)} :is connecting from *@{$u->get($victim,"uncloaked")} {$u->get($victim,"ip")}";
			}
			// channels
			$chans_in = array();
			foreach ($channels as $chan) {
				$ulist = $chan['userlist'];
				foreach ($ulist as $list) {
					if ($list['id'] == $victim) {
						$chans_in[] = $chan['name'];
					}
				}
			}
			if (count($chans_in) > 0) {
				$return[] = ":{$config['netaddr']} 319 {$u->getNick($user)} {$u->getNick($victim)} :".implode(chr(32),$chans_in);
			}
			
			
			// Server - To expanded upon.
			$return[] = ":{$config['netaddr']} 312 {$u->getNick($user)} {$u->getNick($victim)} {$config['netaddr']} :{$config['srvdesc']}";
			
			// Idle information
			$idletime = time() - $u->get($victim,"idle");
			$return[] = ":{$config['netaddr']} 317 {$u->getNick($user)} {$u->getNick($victim)} {$idletime} {$u->get($victim,"signon")}:seconds idle, signon time";
			
			$return[] = ":{$config['netaddr']} 318 {$u->getNick($user)} {$u->getNick($victim)} :End of /WHOIS list.";
		}
		else {
			// Unknown user.
			echo "We couldnt find a user with the nickname {$ex[1]}\n";
			$return[] = ":{$config['netaddr']} 401 {$u->getNick($user)} {$ex[1]} :No such nick/channel";
			$return[] = ":{$config['netaddr']} 318 {$u->getNick($user)} {$ex[1]} :End of /WHOIS list.";
		}
	}
	else if ($ex[0] == "QUIT") {
		// User disconnected.
		$users[$user]['connected'] = FALSE;
		@socket_close($sock);
	}
	else if ($ex[0] == "PRIVMSG" || $ex[0] == "NOTICE") {
		$hostmask = $u->get($user,"nick")."!".$u->get($user,"username")."@".$u->get($user,"cloaked");
		$u->resetidle($user);
		$action = strtoupper($ex[0]);
		if ($ex[1][0] != "#") {
			// User.
			$target = $u->getUid($ex[1]);
			if ($target) {
				// Forward the message to them.
				$message = substr(implode(" ",array_slice($ex,2)),1);
				$sawk = $u->get($target,"socket");
				$str = ":{$hostmask} {$action} {$u->getNick($target)} :{$message}\n\r";
				socket_write($sawk,$str,strlen($str));
			}
			else {
				$return[] = ":{$config['netaddr']} 401 {$u->getNick($user)} {$ex[1]} :No such nick/channel";
			}
		}
		else if ($ex[1][0] == "#") {
			// Channel
			$chan = $ex[1];
			$chan_id = $c->getID($chan);
			if ($chan_id !== FALSE) {
				// Exists
				$user_list = $channels[$chan_id]['userlist'];
				foreach ($user_list as $ulist) {
					if ($ulist['id'] != $user) {
						$vic_socket = $u->get($ulist['id'],"socket");
						$message = substr(implode(" ",array_slice($ex,2)),1);
						$str = ":{$hostmask} {$action} {$chan} :{$message}\n\r";
						socket_write($vic_socket,$str,strlen($str));
					}
				}
			}
			else {
				$return[] = ":{$config['netaddr']} 401 {$u->getNick($user)} {$ex[1]} :No such nick/channel";
			}
		}
		else {
			$return[] = ":{$config['netaddr']} 401 {$u->getNick($user)} {$ex[1]} :No such nick/channel";
		}
	}
	else {
		$return[] = $ex[0]." :{$ex[0]} Unknown Command";
	}
	return $return;
}
function mode_to_sym($modes) {
	$ret = "";
	$modes = str_split($modes);
	foreach ($modes as $m) {
		if ($m === "q") {
			$ret .= "~";
		}
		else if ($m === "a") {
			$ret .= "&";
		}
		else if ($m === "o") {
			$ret .= "@";
		}
		else if ($m === "h") {
			$ret .= "%";
		}
		else if ($m === "v") {
			$ret .= "+";
		}
	}
	return $ret;
}
function shutdown($fucks = 0) {
	global $clients,$socket,$config;
	foreach ($clients as $sock) {
		echo "CLOSING SOCKET {$sock}\n";
		@socket_write($sock,":{$config['netaddr']} NOTICE AUTH :Server is closing.\n\r");
		@socket_close($sock); // Closes any existing.
	}
	echo "CLOSED ".count($clients)." CLIENTS\n";
	echo "CLOSING MASTER\n";
	@socket_close($socket);
	die();
}
?>