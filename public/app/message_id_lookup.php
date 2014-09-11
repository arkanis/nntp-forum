<?php

/**
 * This script takes a message id and redirects the user to the topic this
 * message was posted in.
 * 
 * 	/lbrbml$nph$1@news.hdm-stuttgart.de  →  /hdm.mi.allgemein/1820#message-1827
 */

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

if ( ! isset($_GET['id']) )
	exit_with_not_found_error();
$id = '<' . $_GET['id'] . '>';

// Connect to the newsgroup
$nntp = nntp_connect_and_authenticate($CONFIG);

// Lookup the first newsgroup the message was posted in
list($status, ) = $nntp->command('head ' . $id, array(221, 430));
if ($status == 430)
	exit_with_not_found_error();


// Setup the parser. We need a newsgroup the message is posted in.
$newsgroup = null;
$root_message_id = null;
$message_parser = new MessageParser(array(
	'message-header' => function($headers) use(&$newsgroup, &$root_message_id) {
		$newsgroups = array_map('trim', explode(',', $headers['newsgroups']));
		$newsgroup = reset($newsgroups);
		if (isset($headers['references'])) {
			$referenced_ids = preg_split('/\s+/', $headers['references'], PREG_SPLIT_NO_EMPTY);
			$root_message_id = reset($referenced_ids);
		}
	}
));

$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
// We need this empty line to signal the end of the headers so the message-header
// event is fired and $message_data is filled.
$message_parser->parse_line('');

// Load the message tree of the newsgroups the message was posted in
list($message_tree, $message_infos) = get_message_tree($nntp, $newsgroup);
$nntp->close();

// Sorry, message not yet in cache...
if ( ! isset($message_infos[$id]) )
	exit_with_not_found_error();

$number = $message_infos[$id]['number'];

if ($root_message_id) {
	// Root message no longer in the cache... was probably deleted. Sorry.
	// TODO: Restructure message tree so we can recover from that...
	if ( ! isset($message_infos[$root_message_id]) )
		exit_with_not_found_error();
	
	$topic_number = $message_infos[$root_message_id]['number'];
	$path = '/' . urlencode($newsgroup) . '/' . urlencode($topic_number) . '#message-' . urlencode($number);
} else {
	$path = '/' . urlencode($newsgroup) . '/' . urlencode($number);
}

header('Location: ' . url_for($path));
header('HTTP/1.1 303 See Other');
exit();

?>