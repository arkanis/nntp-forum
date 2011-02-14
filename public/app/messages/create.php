<?php

/**
 * This page is the backend used to post new messages to a newsgroup. It also provides a text
 * to Markdown converter for the message preview.
 * 
 * Response status codes:
 * 
 * - 201 Created: The new message was sent to the server and could be verified to be online.
 * - 202 Accepted: The new message was send but could not be found on the server. Might
 *   come online later or gets rejected (e.g. by a moderator).
 * - 404 Not Found: No `newsgroup` GET parameter specified or the newsgroup could not be selected
 *   (does not exist for this user).
 * - 422 Unprocessable Entity: Message data like the subject or post test was missing or the
 *   message was not accepted by the newsgroup server.
 */

define('ROOT_DIR', '../../..');
require(ROOT_DIR . '/include/header.php');

// Preview requests are send here with the post text in the 'preview_text' field of the
// POST data. Just convert it to markdown and exit.
if( isset($_POST['preview_text']) ){
	echo( Markdown($_POST['preview_text']) );
	exit();
}

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();

try {
	$nntp = nntp_connect_and_authenticate($CONFIG);

	// Select the newsgroup we are supposed to post to
	$group = sanitize_newsgroup_name($_GET['newsgroup']);
	list($status,) = $nntp->command('group ' . $group, array(211, 411));
	if ($status == 411)
		exit_with_not_found_error();
	
	// If we got a message number parameter the new post is a reply. In that case fetch the original
	// to determine the subject and the references header.
	if ( isset($_GET['number']) ) {
		$parent_number = intval($_GET['number']);
		list($status, $parent_infos) = $nntp->command('head ' . $parent_number, array(221, 423));
		if ($status == 423)
			exit_with_not_found_error();
		
		list(, $parent_id,) = explode(' ', $parent_infos, 3);
		$parent_subject = null;
		$references = null;
		$message_parser = new MessageParser(array(
			'message-header' => function($headers) use(&$parent_subject, &$references){
				$parent_subject = $headers['subject'];
				$references = isset($headers['references']) ? preg_split('/\s+/', $headers['references'], PREG_SPLIT_NO_EMPTY) : array();
			}
		));
		$parent_headers = $nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
		$message_parser->parse_line('');
		
		$references[] = $parent_id;
		$subject = preg_match('/^Re:/i', $parent_subject) ? $parent_subject : 'Re: ' . $parent_subject;
	} else {
		$subject = $_POST['subject'];
		$references = array();
	}
	
	$headers = array(
		'Subject: ' . $subject,
		'From: ' . $_SERVER['PHP_AUTH_USER'] . ' <' . $_SERVER['PHP_AUTH_USER'] . '@hdm-stuttgart.de>',
		'Newsgroups: ' . $group
	);
	if ( !empty($references) )
		$headers[] = 'References: ' . join(' ', $references);
	$headers[] = 'Content-Type: text/plain; charset=utf-8';
	$message = join("\n", $headers) . "\n\n" . $_POST['body'];
	
	$nntp->command('post', 340);
	list($status, $confirmation) = $nntp->send_text($message, 240);
	
	// Get the new message id
	preg_match('/<[^>]+>/', $confirmation, $match);
	$new_message_id = $match[0];
	
	// Rebuit the message tree to get the number of the new message
	list(, $message_infos) = rebuilt_message_tree($nntp, $group);
	
	$nntp->close();
} catch(NntpException $exception) {
	header('HTTP/1.1 422 Unprocessable Entity');
	echo($exception->getMessage());
	exit();
}

if ( array_key_exists($new_message_id, $message_infos) ) {
	// 201 Created is send for a confirmed post, including its new location
	header('Location: ' . url_for('/' . $group . '/' . $message_infos[$new_message_id]['number']));
	header('HTTP/1.1 201 Created');
} else {
	// A 202 Accepted response code is send if the new post could not be confirmed yet
	header('HTTP/1.1 202 Accepted');
}

?>