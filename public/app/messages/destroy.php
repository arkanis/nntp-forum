<?php

/**
 * This backend script provides an interface to delete a message of a newsgroup by sending an
 * NNTP cancel control message.
 * 
 * Response status codes:
 * 
 * - 204 No Content: The message was deleted successfully. No response body send (therefore
 *   204 instead of 200.
 * - 404 Not Found: In case the newsgroup is invalid or the message number could not be found
 *   within the newsgroup.
 * - 422 Unprocessable Entity: Something went wrong during the NNTP conversation (e.g. the
 *   cancel message was not accepted by the server). Details are returned in the response body.
 **/

define('ROOT_DIR', '../../..');
require(ROOT_DIR . '/include/header.php');

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();
if( !isset($_GET['number']) )
	exit_with_not_found_error();

try {
	$group = sanitize_newsgroup_name($_GET['newsgroup']);
	$message_number = intval($_GET['number']);
	
	// Connect to the newsgroup and get the (possibly cached) message tree and information.
	$nntp = nntp_connect_and_authenticate($CONFIG);
	list($message_tree, $message_infos) = get_message_tree($nntp, $group);
	
	// If the newsgroup does not exists show the "not found" page.
	if ( $message_tree == null )
		exit_with_not_found_error();
	
	// Now select the group and query the headers by using the message number. This way we
	// get the subject and newsgroups headers as well as the message ID (in the status response).
	// If the message could not be found it already is deleted. Show a not found error in that case.
	$nntp->command('group ' . $group, 211);
	list($status, $status_response) = $nntp->command('head ' . $message_number, array(221, 423));
	if ($status == 423)
		exit_with_not_found_error();
	
	list($number, $message_id, $rest) = explode(' ', $status_response, 3);
	$subject = null;
	$newsgroups = null;
	$message_parser = new MessageParser(array(
		'message-header' => function($headers) use(&$subject, &$newsgroups){
			$subject = $headers['subject'];
			$newsgroups = $headers['newsgroups'];
		}
	));
	$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
	// This empty line triggers the content part of the message and fires the `message-header`
	// event of the parser.
	$message_parser->parse_line('');
	
	// Now we got all we need to construct the cancel message
	$from = $CONFIG['sender_address']($CONFIG['nntp']['user'], $CONFIG['nntp']['user']);
	$content = l('messages', 'deleted_moderator_message');
	$cancel_message = <<<EOD
Control: cancel $message_id
From: $from
Subject: $subject
Newsgroups: $newsgroups
Content-Type: text/plain; charset=utf-8

$content
EOD;
	
	// Post the cancel message
	$nntp->command('post', 340);
	$nntp->send_text($cancel_message, 240);
	
	// Rebuit the message tree to update the data for the next requests and close the
	// NNTP connection.
	rebuilt_message_tree($nntp, $group);
	$nntp->close();
} catch(NntpException $exception) {
	header('HTTP/1.1 422 Unprocessable Entity');
	echo($exception->getMessage());
	exit();
}

header('HTTP/1.1 204 No Content');
?>