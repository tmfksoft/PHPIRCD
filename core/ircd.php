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
	global $core;
	$core->log('log/ircd.log','Shutting down!',true);
	if (file_exists("data/ircd.pid")) {
		unlink("data/ircd.pid");
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
$core->log("log/ircd.log","Starting IRCD..",true);

// Load our config
$core->log("log/ircd.log","Loading configuration",true);

$r=$cfg->load_config("data/ircd.conf","IRCD",true);

if (!$r) {
	$core->log("log/ircd.log","Error loading configuration!",true);
	shutdown();
}

// Make some variables.
$servers = array(); // This is a list of connected servers, similar to clients. But they access client commands.
$clients = array(); // This is our client list. Each client will have a socket, the Array ID is their ID.
$channels = array(); // This is the channel list. Each channel will haver a userlist at least!
$sockets = array(); // These are our server sockets
$modules = array(); // Module Data for the /modules command.

// Load our modules.
$core->log("log/ircd.log","Loading modules",true);
$count = 0;
foreach (scandir("modules") as $file) {
	if ($file[0] != ".") {
		if (is_file("modules/".$file)) {
			// Lets assume its a PHP file.
			include("modules/".$file);
			$count++;
		}
	}
}
$core->log("log/ircd.log","Loaded {$count} modules",true);

// Assuming we've started
file_put_contents("data/ircd.pid",getmypid());
?>