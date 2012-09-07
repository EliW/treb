<?php
/**
* Roughly inspired by code from http://wildphp.com / Super3boy <admin@wildphp.com>
* Used under Creative Commons Attribution 3.0 License
*
* Almost remade from scratch for this dedicated purpose, by Eli
*
* Edit the 'config' section below to work properly for your setup.  The 'relay' needs to be a bot
*  that accepts/understands the 'say' command, such as Phergie: http://phergie.org/
**/

// So this bot walked into a bar ...
set_time_limit(0);

class IRCBot {
    private $socket;
    private $message;
    private $config = array(
        'server' => 'ssl://irc.example.com', # URL to IRC Server you want to communicate with
        'host'   => 'irc.example.com',       # Hostname you want to claim to be connecting from
        'port'   => 1337,                    # Port the IRC server is on
        'nick'   => 'trebbot',               # Nick for this script to use when it connects
        'chan'   => '##example',             # Channel that you want the bot/phergie to announce in
        'relay'  => 'phergie'                # Nick of the bot that will relay the message
        );

    /* Construct item, opens the server connection, logs the bot in */
    function __construct($message) {
        $this->message = $message;
        $this->socket = fsockopen($this->config['server'], $this->config['port']);
        $this->login();
        $this->message();
    }

    /* Logs the bot in on the server */
    function login() {
        $nick = $this->config['nick'];
        $host = $this->config['host'];
        $this->send("PASS NOPASS");
        $this->send("NICK {$nick}");
        $this->send("USER {$nick} {$host} {$host} :{$nick}");
    }

    /* Loops to send the message */
    function message() {
        while (1) {
            $data = fgets($this->socket, 1024);
            //echo $data;
            $this->parts = explode(' ', $data);

            // Response to PING/PONG as needed, but mostly just wait for the notice that we are in.
            if ($this->parts[0] == 'PING') {
                $this->send("PONG {$this->parts[1]}");
            } elseif (($this->parts[1] == 'NOTICE') && ($this->parts[2] == $this->config['nick'])) {
                $this->send("PRIVMSG {$this->config['relay']} :say {$this->config['chan']} {$this->message}");
                exit;
            }
        }
    }

    /* Just fires off the data to the socket */
    function send($cmd) {
        fputs($this->socket, $cmd."\r\n");
    }
}

// Start the bot
if (!($message = $argv[1])) { die("You need to provide a message!"); }
$bot = new IRCBot($message);
