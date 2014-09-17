#!/usr/bin/php
<?php

/**
 * The file is intended to be used as a cron job to send users mail notifications for new
 * messages in their subscribed topics.
 * 
 * To make mail subscriptions work you have to add this cron job to a crontab. This example
 * config line will send out new mail notifications every 5 minutes:
 */
//   */5 *    * * *   root    /path/to/send-mail-notifications.php

define('ROOT_DIR', dirname(__FILE__) . '/..');

// Load first because the `autodetect_lang()` function can be used in the configuration file.
require(ROOT_DIR . '/include/action_helpers.php');
require(ROOT_DIR . '/include/view_helpers.php');
require(ROOT_DIR . '/include/nntp_connection.php');
require(ROOT_DIR . '/include/message_parser.php');

// Set the stuff used in the config file… otherwise we will get some warnings.
$_SERVER['PHP_AUTH_USER'] = null;
$_SERVER['PHP_AUTH_PW'] = null;

// If we are run in an environment load the matching config file. Otherwise just load the
// defaul config.
if ($_CONFIG_ENV = getenv('ENVIRONMENT'))
	$CONFIG = require( ROOT_DIR . '/include/' . basename("config.$env.php") );
else
	$CONFIG = require( ROOT_DIR . '/include/config.php' );

$_LOCALE = require(ROOT_DIR . '/locales/' . $CONFIG['lang'] . '.php');

$watchlist_file = $CONFIG['subscriptions']['watchlist'];
if (!$watchlist_file)
	exit();

// Patch the NNTP user with the user data configured for subscriptions
$CONFIG['nntp']['user'] = $CONFIG['subscriptions']['nntp']['user'];
$CONFIG['nntp']['pass'] = $CONFIG['subscriptions']['nntp']['pass'];


// Connect to the newsgroup
$nntp = nntp_connect_and_authenticate($CONFIG);

// Read the information from the last run
$last_run_file = ROOT_DIR . '/subscriptions/last_run';
$last_run_data = json_decode(@file_get_contents($last_run_file), true);
if (is_array($last_run_data)) {
	list($last_run_date, $last_message_id) = $last_run_data;
} else {
	// No previous run, so we have no idea since when we're supposed to fetch new messages. Therefore
	// just write down the current server date so the next run can process all messages since now.
	list(, $server_date) = $nntp->command('date', 111);
	$nntp->close();
	file_put_contents($last_run_file, json_encode(array($server_date, null)));
	exit(0);
}

$nntp->command('mode reader', array(200, 201));

// Record the date just before we started the message list. Everything older than this date
// will have to be examined on the next run. We'll safe that date later on if everything else
// went fine.
list(, $current_run_date) = $nntp->command('date', 111);

// Fetch all new messages since the last run date
$start_date = date_create_from_format('YmdHis', $last_run_date, new DateTimeZone('UTC'));
//$start_date = date_create('6 month ago', new DateTimeZone('UTC'));
//echo('newnews * ' . $start_date->format('Ymd His'));
$nntp->command('newnews * ' . $start_date->format('Ymd His'), 230);
$new_message_list = trim($nntp->get_text_response());
$new_message_ids = empty($new_message_list) ? array() : explode("\n", $new_message_list);


// Load the watchlist
$fd = fopen($watchlist_file, 'r');
flock($fd, LOCK_SH);
$watchlist = json_decode(stream_get_contents($fd), true);
flock($fd, LOCK_UN);
fclose($fd);

// Fetch some meta data of the messages. Fetching overview data by message-id _can_ be supported but
// NNTP doesn't require it. So we take the safe way and do it with the hdr command.
// TODO: Would be a great usecase for pipelining...
$messages = array();
foreach($new_message_ids as $id){
	//echo("Processing $id... ");
	$nntp->command('hdr references ' . $id, 225);
	$references = preg_replace('/^\d+\s+/', '', $nntp->get_text_response());
	$referenced_ids = preg_split('/\s+/', $references, 0, PREG_SPLIT_NO_EMPTY);
	
	$users_to_notify = array();
	foreach($referenced_ids as $ref_id) {
		if (array_key_exists($ref_id, $watchlist))
			$users_to_notify = array_merge($users_to_notify, array_keys($watchlist[$ref_id]));
	}
	
	if (!empty($users_to_notify)) {
		// Someone is interested in this message. But fetch the date first and check that
		// it's actually after our start date.
		$nntp->command('hdr date ' . $id, 225);
		$date = MessageParser::parse_date_and_zone( preg_replace('/^\d+\s+/', '', $nntp->get_text_response()) );
		if ($date < $start_date) {
			//echo("rejected, message to old (we've already processed it)\n");
			continue;
		}
		
		// Some users are interested in this message. Fetch the details.
		$nntp->command('hdr subject ' . $id, 225);
		$subject = MessageParser::decode_words( preg_replace('/^\d+\s+/', '', $nntp->get_text_response()) );
		$nntp->command('hdr from ' . $id, 225);
		$from = MessageParser::decode_words( preg_replace('/^\d+\s+/', '', $nntp->get_text_response()) );
		list($from_name, $from_addr) = MessageParser::split_from_header($from);
		
		foreach($users_to_notify as $user) {
			$receiver_address = $CONFIG['sender_address']($user, $user);
			$message  = "Subject: $subject\n";
			$message .= "From: $from_name <" . $CONFIG['subscriptions']['sender_address'] . ">\n";
			$message .= "To: " . $receiver_address . "\n";
			$message .= "Date: " . $date->format('r') . "\n";
			$message .= "\n";
			$message .= l('subscriptions', 'mail', $from_name,
				$CONFIG['subscriptions']['link_base'] . urlencode(substr($id, 1, -1)),
				$CONFIG['subscriptions']['link_base'] . 'your/subscriptions'
			);
			
			$delivered = smtp_send($CONFIG['subscriptions']['sender_address'], $receiver_address, $message, $CONFIG['subscriptions']['smtp']);
			if ( ! $delivered )
				echo("Failed to notify $receiver_address about message $id\n");
		}
		
		//echo('notified ' . join(', ', $users_to_notify));
	}
	
	//echo("\n");
}

// Everything worked fine, write down the last run information for the next job
file_put_contents($last_run_file, json_encode(array($current_run_date, null)));


//
// Additional functions only used in this file
//

/**
 * A small function to send mails directly via SMTP. Usage:
 * 
 *  $message = <<<EOD
 *  From: "Mr. Sender" <sender@example.com>
 *  To: "Mr. Receiver" <receiver@example.com>
 *  Subject: SMTP Test
 *  
 *  Hello there. Just a small test.
 *  End of message.
 *  EOD;
 *  smtp_send('sender@example.com', 'receiver@example.com', $message, array(
 *  	'server' => 'mail.example.com',
 *  	'port' => 587,
 *  	'user' => 'sender',
 *  	'pass' => 'secret'
 *  ));
 * 
 * Line breaks in the message are automatically converted to \r\n (CRLF) so you can just
 * use normal linebreaks when constructing it.
 * 
 * For multiple receivers $to takes an array of mail addresses.
 * 
 * The SMTP settings can also contain a 'timeout' for the initial connection (float, in
 * seconds, see http://php.net/fsockopen). You can also specify an SMTP 'client_domain',
 * which is the value send with the EHLO command. It defaults to the value returned by
 * gethostname().
 */
function smtp_send($from, $to, $message, $smtp_settings) {
	// Sanitize parameters and set default values
	if ( ! is_array($to) )
		$to = array($to);
	
	if ( ! isset($smtp_settings['timeout']) )
		$smtp_settings['timeout'] = ini_get("default_socket_timeout");
	if ( ! isset($smtp_settings['client_domain']) )
		$smtp_settings['client_domain'] = gethostname();
	
	// Connect to SMTP server
	$con = fsockopen(@$smtp_settings['server'], @$smtp_settings['port'], $errno, $errstr, $smtp_settings['timeout']);
	if ($con === false)
		return false;
	// Consume the greeting line
	fgets($con);
	
	// Small helper function to send SMTP commands and receive their responses
	// See http://tools.ietf.org/html/rfc5321#section-4.1.1
	$command = function($command_line) use(&$con) {
		fwrite($con, "$command_line\r\n");
		
		$text = array();
		while( $line = fgets($con) ) {
			$status = substr($line, 0, 3);
			$text[] = trim(substr($line, 4));
			if (substr($line, 3, 1) === ' ')
				break;
		}
		
		return array($status, $text);
	};
	
	
	// Say hello to the server
	// See http://tools.ietf.org/html/rfc5321#section-4.1.1.1
	list($status, $capabilities) = $command('EHLO ' . $smtp_settings['client_domain']);
	if ($status != 250) {
		fclose($con);
		return false;
	}
	
	// Try TLS if available
	// See http://tools.ietf.org/html/rfc3207
	if (in_array('STARTTLS', $capabilities)) {
		list($status, ) = $command('STARTTLS');
		if ($status == 220) {
			if ( ! stream_socket_enable_crypto($con, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) ) {
				$command('QUIT');
				fclose($con);
				return false;
			}
			
			list($status, $capabilities) = $command('EHLO ' . $smtp_settings['client_domain']);
			if ($status != 250) {
				$command('QUIT');
				fclose($con);
				return false;
			}
		}
	}
	
	// Authenticate using PLAIN method if we have credentials
	// See http://tools.ietf.org/html/rfc4954#section-4
	if ( isset($smtp_settings['user']) ) {
		list($status, ) = $command('AUTH PLAIN ' . base64_encode("\0" . @$smtp_settings['user'] . "\0" . @$smtp_settings['pass']));
		if ($status != 235) {
			$command('QUIT');
			fclose($con);
			return false;
		}
	}
	
	// Send the mail. We do no individual error checking here because errors will
	// propagate and cause the last command 
	$command('MAIL FROM:<' . $from . '>');
	foreach($to as $recipient)
		$command('RCPT TO:<' . $recipient . '>');
	$command('DATA');
	
	// Convert all line breaks in the message to \r\n and escape leading dots (data
	// end signal, see http://tools.ietf.org/html/rfc5321#section-4.5.2)
	$message = preg_replace('/\r?\n/', "\r\n", $message);
	$message = preg_replace('/^\./m', '..', $message);
	// Make sure the message has a trailing line break. Otherwise the data end
	// command (.) would not work properly.
	if (substr($message, -2) !== "\r\n")
		$message .= "\r\n";
	
	fwrite($con, $message);
	list($status, ) = $command(".");
	$submission_successful = 250;
	
	$command('QUIT');
	fclose($con);
	return $submission_successful;
}

?>