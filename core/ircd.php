<?php
if (file_exists("data/ircd.pid")) {
	die('Refusing to start. The IRCD is already running!');
}
// Set time limit to indefinite execution
set_time_limit (0);
declare(ticks = 1);

$ircd = array();
$ircd['name'] = "FudgieIRCD";
$ircd['flavour'] = "Vanilla";
$ircd['version'] = "0.1";

// Piggyback on the shutdown
pcntl_signal(SIGINT, "shutdown");
pcntl_signal(SIGTERM, "shutdown");
pcntl_signal(SIGHUP,  "shutdown");
pcntl_signal(SIGUSR1, "shutdown");
register_shutdown_function('shutdown');

function shutdown() {
	global $core,$clients,$sockets;
	$core->log('log/ircd.log','Shutting down!',true);
	if (file_exists("data/ircd.pid")) {
		unlink("data/ircd.pid");
	}
	if (isset($clients) && count($clients) > 0) {
		echo "Closing Client Sockets\n";
		foreach ($clients as $sk) {
			// Poll data from the socket if there is any then close it.
			socket_read($sk['socket'], 3, PHP_NORMAL_READ);
			socket_close($sk['socket']);
		}
	}
	if (isset($sockets) && count($sockets) > 0) {
		echo "Closing Server sockets";
		var_dump($sockets);
		foreach ($sockets as $sk) {
			// Poll data from the socket if there is any then close it.
			socket_read($sk['socket'], 3, PHP_NORMAL_READ);
			socket_close($sk['socket']);
		}
	}
	die();
}

// Include our files.
include("core/core.inc.php");
include("core/config.inc.php");
include("core/api.inc.php");

// Make some API variables.
$client_commands = array();
$server_commands = array();

$core->log("log/ircd.log","==========[ {$ircd['name']}({$ircd['flavour']})-{$ircd['version']} ]========",true);
$core->log("log/ircd.log","Starting IRCD...",true);

// Load our config
$core->log("log/ircd.log","Loading configuration...",true);

$r=$cfg->load_config("data/ircd.conf","IRCD",true);

if (!$r) {
	$core->log("log/ircd.log","Error loading configuration!",true);
	shutdown();
}

// Now we check we've got at least the listening blocks.
if (!isset($_CONFIG['IRCD']['LISTEN'][0])) {
	$core->log("log/ircd.log","Unable to start IRCD Missing all listening blocks!",true);
	$core->log("log/ircd.log","At least one listener is required!",true);
	shutdown();
}

// Make some variables.
$servers = array(); // This is a list of connected servers, similar to clients. But they access client commands.
$clients = array(); // This is our client list. Each client will have a socket, the Array ID is their ID.
$channels = array(); // This is the channel list. Each channel will haver a userlist at least!
$sockets = array(); // These are our server sockets
$modules = array(); // Module Data for the /modules command.

// Load our modules.
$core->log("log/ircd.log","Loading modules...",true);
$count = 0;
foreach (scandir("modules") as $file) {
	if ($file[0] != ".") {
		if (is_file("modules/".$file)) {
			// Check its a PHP File
			if (substr($file,-3) == "php") {
				include("modules/".$file);
				$count++;
			}
		}
	}
}
$core->handle_errors();
$core->log("log/ircd.log","Loaded {$count} modules",true);

// Assuming we've started
file_put_contents("data/ircd.pid",getmypid());

// Now we listen on our ports!
foreach ($_CONFIG['IRCD']['LISTEN'] as $id => $lbk) {
	$id++;
	if (isset($lbk['HOST']) && isset($lbk['PORT'])) {
		// Listen.
		$ip = $lbk['HOST'];
		if ($ip == "*" || $ip === "0" || $ip === "127.0.0.1") {
			$ip = "0.0.0.0";
		}
		
		$sock = socket_create(AF_INET,SOCK_STREAM,0);
		socket_set_nonblock($sock);
		if (!$sock) { die('Error creating socket.'); }
		@socket_bind($sock,$ip,$lbk['PORT']) or die($core->log("log/ircd.log","FATAL: Cannot Bind to {$ip}:{$lbk['PORT']} :".socket_strerror(socket_last_error())."\n",true));
		@socket_listen($sock) or die($core->log("log/ircd.log","FATAL: Cannot Listen :".socket_strerror(socket_last_error())."\n",true));
		$sockets[] = $sock;
		$core->log("log/ircd.log","Listening on {$ip}:{$lbk['PORT']}",true);
	} else {
		if (!isset($lbk['HOST'])) {
			$core->error("Listen block #{$id} is missing HOST!");
		} else {
			$core->error("Listen block #{$id} is missing PORT!");
		}
	}
}
if (count($sockets) < 1) {
	$core->log("log/ircd.log","The IRCD was unable to listen on any IPs or Ports!",true);
	$core->error("No listening ports could be opened!","FATAL");
}
$core->handle_errors();
$core->log("log/ircd.log","Opened ".count($sockets)." listener sockets.",true);

// Tell the user we're happy!
$core->log("log/ircd.log","{$ircd['name']}({$ircd['flavour']})-{$ircd['version']} has booted successfully!",true);

$running = true;
while($running) {
	$to_read = array();
	foreach ($sockets as $sk) {
		$to_read[] = $sk;
	}
	$c_stack = array();
	foreach ($clients as $c) {
		$c_stack[] = $c['socket'];
	}
	$to_read = array_merge($to_read,$c_stack);
	array_values($to_read);
	
	$changes = @socket_select($to_read,$a = NULL,$b = NULL,0);

	if ($changes > 0) {
		foreach ($sockets as $sk) {
			$newc = @socket_accept($sk);
			if ($newc !== false) {
				echo "Client has connected with UID ".(count($users)-1)."\n\r";
				socket_set_nonblock($newc);
				$clients[] = array("socket"=>$newc,"ready"=>true);
			}
		}
		foreach ($clients as $id => $dat) {
			if ($dat['ready']) {
				$input = @socket_read($dat['socket'],1024);
				$result = socket_write($dat['socket'],"\n",0);
				if ($result === FALSE) {
					echo "User {$id} has closed their socket.\n";
					unset($clients[$id]);
				}
					
				$data = trim($input);
				// Deal with the data now.
			}
		}
	}
}
?>