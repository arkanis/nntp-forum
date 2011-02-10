<?php

define('ROOT_DIR', '../../');
require(ROOT_DIR . 'include/header.php');

if ( !( isset($_GET['name']) and array_key_exists($_GET['name'], $CONFIG['newsfeeds']) ) )
	exit_with_not_found_error();

$feed_config = $CONFIG['newsfeeds'][$_GET['name']];

// Connect to the newsgroup
$nntp = nntp_connect_and_authenticate($CONFIG);

// The feed configuration can contain a "wildmat" (newsgroup name with wildcards). With that alone
// we can query the message IDs of all new messages (`newnews` command). But if we want links back
// to the messages on the website or links to attachments we need the message _number_ within a
// newsgroup. We can get this out of the message trees of the corresponding newsgroups and for that
// we need to get a list of newsgroup names matching the given wildmat in the configuration.


// Query the newest messages in all newsgroups matching the "wildmat" (newsgroup name with
// wildcards) in the config.
$start_date = date('Ymd His', time() - $feed_config['history_duration']);
$nntp->command('newnews ' . $feed_config['newsgroups'] . ' ' . $start_date, 230);
$new_message_ids = $nntp->get_text_response();


// Default storage area for each message. This array is used to reset the storage area for the event
// handlers after a message is parsed.
$empty_message_data = array(
	'newsgroup' => null,
	'content' => null,
	'content_encoding' => null,
	'attachments' => array()
);
// Storage area for message parser event handlers
$message_data = $empty_message_data;

// Setup the parser. We need a newsgroup the message is posted in, the first text/plain part found and
// all attachments. The subject and author information is extracted from the overview information of the
// message tree later one.
$message_parser = new MessageParser(array(
	'message-header' => function($headers) use(&$message_data){
		$message_data['newsgroup'] = trim(reset(explode(',', $headers['newsgroups'])));
	},
	'part-header' => function($headers, $content_type, $content_type_params) use(&$message_data){
		if ($message_data['content'] == null and $content_type == 'text/plain') {
			$message_data['content_encoding'] = isset($content_type_params['charset']) ? $content_type_params['charset'] : 'ISO-8859-1';
			return 'append-content-line';
		} elseif ( isset($content_type_params['name']) ) {
			$name = $content_type_params['name'];
			$message_data['attachments'][] = array('name' => $name, 'type' => $content_type, 'params' => $content_type_params, 'size' => null);
			return 'record-attachment-size';
		}
	},
	'append-content-line' => function($line) use(&$message_data){
		$message_data['content'] .= $line;
	},
	'record-attachment-size' => function($line) use(&$message_data){
		$current_attachment_index = count($message_data['attachments']) - 1;
		$message_data['attachments'][$current_attachment_index]['size'] += strlen($line);
	}
));

// Message tree cache
$message_trees = array();

$messages = array();
foreach(explode("\n", $new_message_ids) as $message_id){
	// Fetch the article source
	$nntp->command('article ' . $message_id, 220);
	// Parse it. The parser event handlers store the message information in $message_data.
	$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
	// Decode the message content to UTF-8 and convert it to HTML
	$message_data['content'] = Markdown( iconv($message_data['content_encoding'], 'UTF-8', $message_data['content']) );
	// Remove the encoding, no need to keep this in a nice and cachable array
	unset($message_data['content_encoding']);
	
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
		
		// Append the message data to the list and stop if we already have enought messages for the feed
		$messages[$message_id] = $message_data;
		if ( count($messages) >= $feed_config['limit'] )
			break;
	}
	
	// Reset the parser and the storage variable
	$message_parser->reset();
	$message_data = $empty_message_data;
}

$nntp->close();

// Setup layout variables
$title = $feed_config['title'];
$layout = 'atom-feed';
$feed_url = 'http://' . urlencode($_SERVER['SERVER_NAME']) . '/' . urlencode($_GET['name']) . '.xml';
$updated = ( count($messages) > 0 ) ? $messages[reset(array_keys($messages))]['date'] : time();
?>
<? foreach ($messages as $message_id => $message): ?>
	<entry>
		<id>nntp://<?= urlencode(trim($message_id, '<>')) ?>/</id>
		<title><?= h($message['subject']) ?></title>
		<updated><?= date('c', $message['date']); ?></updated>
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
<? require(ROOT_DIR . 'include/footer.php') ?>