<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

if ( !( isset($_GET['name']) and array_key_exists($_GET['name'], $CONFIG['newsfeeds']) ) )
	exit_with_not_found_error();

$feed_config = $CONFIG['newsfeeds'][$_GET['name']];

$messages = cached('feed-' . $_GET['name'], function() use($feed_config, $CONFIG){
	// Connect to the newsgroup
	$nntp = nntp_connect_and_authenticate($CONFIG);
	
	// The feed configuration can contain a "wildmat" (newsgroup name with wildcards). With that alone
	// we can query the message IDs of all new messages (`newnews` command). Since the message IDs might
	// be unorderd (happend after the move to a new NNTP server) we first query the date of each message,
	// sort them do dertermine the messsages we really want in the newsfeed. If we want links back
	// to the messages on the website or links to attachments we need the message _number_ within a
	// newsgroup. Therefore we query the overview information of the message tree a message was posted
	// in later on.
	
	// Query the servers date and calculate the newsfeed start date based on it (avoids confusion between
	// NNTP server time and the server time the frontend runs on).
	list(, $current_server_date_str) = $nntp->command('date', 111);
	$current_server_date = date_create_from_format('YmdHis', $current_server_date_str, new DateTimeZone('UTC'));
	$start_date = $current_server_date->sub(new DateInterval('PT' . $feed_config['history_duration'] . 'S'));
	
	// Query the newest messages in all newsgroups matching the "wildmat" (newsgroup name with
	// wildcards) in the config.
	$nntp->command('newnews ' . $feed_config['newsgroups'] . ' ' . $start_date->format('Ymd His'), 230);
	$new_message_list = trim($nntp->get_text_response());
	$new_message_ids = empty($new_message_list) ? array() : explode("\n", $new_message_list);
	
	// Query the dates of all new messages
	$message_dates = array();
	foreach($new_message_ids as $id){
		$nntp->command('hdr date ' . $id, 225);
		list(,$date) = explode(' ', $nntp->get_text_response(), 2);
		$message_dates[$id] = MessageParser::parse_date_and_zone($date);
	}
	
	// Sort message ids by date and limit the number to the configured feed limit
	uasort($message_dates, function($a, $b){
		if ($a == $b)
			return 0;
		return ($a > $b) ? -1 : 1;
	});
	$message_dates = array_slice($message_dates, 0, $feed_config['limit']);
	
	// Default storage area for each message. This array is used to reset the storage area for the event
	// handlers after a message is parsed.
	$empty_message_data = array(
		'id' => null,
		'newsgroup' => null,
		'newsgroups' => null,
		'content' => null,
		'attachments' => array()
	);
	// Storage area for message parser event handlers
	$message_data = $empty_message_data;
	
	// Setup the parser. We need a newsgroup the message is posted in, the first text/plain part found and
	// all attachments. The subject and author information is extracted from the overview information of the
	// message tree later one.
	$message_parser = MessageParser::for_text_and_attachments($message_data);
	
	// Message tree cache
	$message_trees = array();
	
	$messages = array();
	foreach($message_dates as $message_id => $date){
		// Fetch the article source
		$nntp->command('article ' . $message_id, 220);
		// Parse it. The parser event handlers store the message information in $message_data.
		$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
		$message_parser->end_of_message();
		// Convert the message content to HTML
		$message_data['content'] = Markdown($message_data['content']);
		
		// Fetch the message tree of the newsgroup this article was posed in (or the first one of those
		// if it was posted in many). We need this to get the message number for the links.
		$newsgroup = $message_data['newsgroup'];
		if ( ! isset($message_trees[$newsgroup]) )
			$message_trees[$newsgroup] = get_message_tree($nntp, $newsgroup);
		list(, $message_infos) = $message_trees[$newsgroup];
		
		// Skip messages that are not yet in the message tree of this newsgroup
		if ( isset($message_infos[$message_id]) ){
			// Add the overview information of the message tree to this message.
			$message_data = array_merge($message_infos[$message_id], $message_data);
			// Append the message data to the message list
			$messages[$message_id] = $message_data;
		}
		
		// Reset the storage variable to make it ready for the next iteration. The parser is automatically reset
		// by the `end_of_message()` function.
		$message_data = $empty_message_data;
	}
	
	$nntp->close();
	
	return $messages;
});


// Setup layout variables
$title = lt($feed_config['title']);
$layout = 'atom-feed';
$feed_url = url_for('/' . urlencode($_GET['name']) . '.xml');
$updated = ( count($messages) > 0 ) ? $messages[reset(array_keys($messages))]['date'] : date_create();
?>
<? foreach ($messages as $message_id => $message): ?>
	<entry>
		<id>nntp://<?= ha(trim($message_id, '<>')) ?>/</id>
		<title><?= h($message['subject']) ?></title>
		<updated><?= $message['date']->format('c'); ?></updated>
		<author>
			<name><?= h($message['author_name']) ?></name>
			<email><?= h($message['author_mail']) ?></email>
		</author>
<?		$message_url = url_for('/' . urlencode($message['newsgroup']) . '/' . urlencode($message['number'])) ?>
		<link rel="alternate" href="<?= $message_url ?>" />
<?		foreach($message['attachments'] as $attachment): ?>
		<link rel="enclosure" href="<?= $message_url . '/' . urlencode($attachment['name']) ?>" title="<?= ha($attachment['name']) ?>" type="<?= ha($attachment['type']) ?>" length="<?= ha($attachment['size']) ?>" />
<?		endforeach ?>
		<content type="html">
<?= h($message['content']) . "\n" ?>
		</content>
	</entry>
	
<? endforeach ?>
<? require(ROOT_DIR . '/include/footer.php') ?>