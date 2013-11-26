<?php

// Set time limit to indefinite execution

set_time_limit (0);

// Set the ip and port we will listen on

$address = '127.0.0.1';

$port = 9000;

$max_clients = 10;

// Array that will hold client information

$clients = array();

// Create a TCP Stream socket
$master = socket_create(AF_INET, SOCK_STREAM, 0);

socket_set_nonblock($socket);

// Bind the socket to an address/port
socket_bind($master, $address, $port) or die('Could not bind to address');

// Start listening for connections
socket_listen($master);

//stream_set_blocking($sock, 0);
// Loop continuously

while (true) {
    // Setup clients listen socket for reading
	$read = array();
    $read[] = $sock;
	$read = array_merge($read,$clients);
	array_values($read);

    // Set up a blocking call to socket_select()
   $ready = socket_select(array($master),array(),array(),array());

    /* if a new connection is being made add it to the client array */
    if (in_array($sock, $read)) {
        foreach ($read as $i => $ignore) {
            if ($clients[$i]['sock'] == null) {
                $clients[$i]['sock'] = socket_accept($sock);
                break;
            }
            else if ($i == $max_clients - 1) {
                echo "Hit MAX Clients";
			}
        }
        if (--$ready <= 0)
            continue;
    } // end if in_array


    // If a client is trying to write - handle it now
    for ($i = 0; $i < $max_clients; $i++) // for each client
    {
        if (in_array($client[$i]['sock'] , $read))
        {
            $input = socket_read($client[$i]['sock'] , 1024);
            if ($input == null) {
                // Zero length string meaning disconnected
                unset($client[$i]);
            }
            $n = trim($input);
            if ($input == 'exit') {
                // requested disconnect
                socket_close($client[$i]['sock']);
            } elseif ($input) {
                // strip white spaces and write back to user
                $output = ereg_replace("[ \t\n\r]","",$input).chr(0);
                socket_write($client[$i]['sock'],$output);
            }
        } else {
            // Close the socket
            socket_close($client[$i]['sock']);
            unset($client[$i]);
        }
    }
} // end while
socket_close($master);
?>