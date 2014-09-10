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

// Patch the NNTP user with the user data configured for subscriptions
$CONFIG['nntp']['user'] = $CONFIG['subscriptions']['user'];
$CONFIG['nntp']['pass'] = $CONFIG['subscriptions']['pass'];


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
$watchlist_file = $CONFIG['subscriptions']['watchlist'];
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
	echo("Processing $id... ");
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
			echo("rejected, message to old (we've already processed it)\n");
			continue;
		}
		
		// Some users are interested in this message. Fetch the details.
		$nntp->command('hdr subject ' . $id, 225);
		$subject = MessageParser::decode_words( preg_replace('/^\d+\s+/', '', $nntp->get_text_response()) );
		$nntp->command('hdr from ' . $id, 225);
		$from = MessageParser::decode_words( preg_replace('/^\d+\s+/', '', $nntp->get_text_response()) );
		
		$details = "$subject from $from at " . $date->format('r');
		//echo($id . ' to ' . join(', ', $users_to_notify) . ":\n");
		//echo("  $details\n");
		
		foreach($users_to_notify as $user) {
			$users_address = $CONFIG['sender_address']($user, $user);
			mail($users_address, 'New message: ' . $subject, $details, "From: " . $CONFIG['subscriptions']['sender_address']);
		}
		
		echo('notified ' . join(', ', $users_to_notify));
	}
	
	echo("\n");
}

// Everything worked fine, write down the last run information for the next job
file_put_contents($last_run_file, json_encode(array($current_run_date, null)));

?>