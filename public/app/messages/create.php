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

/**
 * A small lookup function that connects to the LDAP server configured in `$CONFIG` and tries
 * to translate the user name into a full display name. This name is then used to post the message.
 */
function ldap_name_lookup($user_id){
	global $CONFIG;
	$ldap_config = $CONFIG['ldap'];
	
	if ( empty($ldap_config['host']) )
		return $user_id;
	
	$con = ldap_connect($ldap_config['host']);
	if ($con){
		if ( @ldap_bind($con, $ldap_config['user'], $ldap_config['pass']) ){
			$match_resource = ldap_search($con, $ldap_config['directory'], 'uid=' . $user_id, array('cn'));
			if ($match_resource){
				$match_data = ldap_get_entries($con, $match_resource);
				return $match_data[0]['cn'][0];
			}
			ldap_unbind($con);
		}
	}
	
	return $user_id;
}

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
	// message to determine the subject and the references header.
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
	
	// We got everything we need, assemble the headers for the message
	$headers = array(
		'Subject: ' . $subject,
		'From: ' . $CONFIG['sender_address']($CONFIG['nntp']['user'], ldap_name_lookup($CONFIG['nntp']['user'])),
		'Newsgroups: ' . $group
	);
	if ( !empty($references) )
		$headers[] = 'References: ' . join(' ', $references);
	
	// Add our little imprint to the world (so other NNTP client programmers know who is
	// responsible for the code of that message).
	$headers[] = 'User-Agent: ' . $CONFIG['user_agent'];
	
	if ( empty($_FILES) or empty($_FILES['attachments']['name'][0]) ) {
		// If we have no attachments build a normal message just with headers and text body
		$headers[] = 'Content-Type: text/plain; charset=utf-8';
		$message = join("\n", $headers) . "\n\n" . $_POST['body'];
		
		$nntp->command('post', 340);
		list($status, $confirmation) = $nntp->send_text($message, 240);
	} else {
		// We have attachments for the message. Build a MIME message with the text body and
		// the attachments.
		$boundary = md5($subject) . '=_' . md5(time());
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-Type: multipart/mixed; boundary=' . $boundary;
		
		$nntp->command('post', 340);
		list($status, $confirmation) = $nntp->send_text_per_chunk(240, function($send) use($headers, $boundary){
			// Send the message headers
			$send(join("\n", $headers) . "\n\n");
			$boundary_line = '--' . $boundary . "\n";
			
			// Send the text part
			$send($boundary_line);
			$send('Content-Type: text/plain; charset=utf-8' . "\n\n");
			$send($_POST['body'] . "\n");
			
			// Send the attachments
			foreach($_FILES['attachments']['name'] as $index => $name){
				$file_path = $_FILES['attachments']['tmp_name'][$index];
				if ( !empty($file_path) and is_uploaded_file($file_path) ) {
					$mime_type = $_FILES['attachments']['type'][$index];
					// Remove all double quotes from the file name so no one can escape
					$name = str_replace('"', '', $name);
					$part_headers = 'Content-Type: ' . $mime_type . '; name="' . $name . '"' . "\n" .
						'Content-Transfer-Encoding: base64' . "\n" .
						'Content-Disposition: attachment; filename="' . $name . '"';
					
					$send($boundary_line);
					$send($part_headers . "\n\n");
					$send( chunk_split(base64_encode(file_get_contents($file_path))) );
				}
			}
			
			// Send MIME message terminator
			$send('--' . $boundary . '--');
		});
		
	}
	
	// Get the new message id
	preg_match('/<[^>]+>/', $confirmation, $match);
	$new_message_id = $match[0];
	
	// Rebuit the message tree to get the number of the new message
	list($message_tree, $message_infos) = rebuilt_message_tree($nntp, $group);
	
	$nntp->close();
} catch(NntpException $exception) {
	// If something exploded send a 422 response and show an error page
	header('HTTP/1.1 422 Unprocessable Entity');
	$title = l('error_pages', 'send_failed', 'title');
?>

<h2><?= h($title) ?></h2>

<p><?= lh('error_pages', 'send_failed', 'description') ?></p>
<p><?= lh('error_pages', 'send_failed', 'error_reporting_hint') ?></p>
<p><?= h($exception->getMessage()) ?></p>

<? if ( count(l('error_pages', 'send_failed', 'suggestions')) ): ?>
<ul>
<? foreach(l('error_pages', 'send_failed', 'suggestions') as $suggestion): ?>
	<li><?= $suggestion ?></li>
<? endforeach ?>
</ul>
<? endif ?>

<?
	require(ROOT_DIR . '/include/footer.php');
	exit();
}

if ( array_key_exists($new_message_id, $message_infos) ) {
	// The new message was confirmed. Now redirect the user to the topic this message was
	// posted in. For that we need the topic number… therefore search the message tree for
	// a topic that contains our new message.
	$target_topic_id = $new_message_id;
	foreach($message_tree as $topic_id => $topic_tree){
		$reply_iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($topic_tree),  RecursiveIteratorIterator::SELF_FIRST );
		foreach($reply_iterator as $message_id => $children){
			if ($message_id == $new_message_id){
				$target_topic_id = $topic_id;
				break 2;
			}
		}
	}
	
	// 303 See Other is send for a confirmed post, along with its new location
	header('Location: ' . url_for(sprintf( '/%s/%d#message-%d', urlencode($group), $message_infos[$target_topic_id]['number'], $message_infos[$new_message_id]['number'] )));
	header('HTTP/1.1 303 See Other');
	exit();
}

// Post was send but could not be confirmed. Send a 202 Accepted response code and output
// an information page.
header('HTTP/1.1 202 Accepted');
$title = l('error_pages', 'not_yet_online', 'title');
?>

<h2><?= h($title) ?></h2>

<p><?= lh('error_pages', 'not_yet_online', 'description') ?></p>

<? if ( count(l('error_pages', 'not_yet_online', 'suggestions')) ): ?>
<ul>
<? foreach(l('error_pages', 'not_yet_online', 'suggestions') as $suggestion): ?>
	<li><?= sprintf($suggestion, '/' . urlencode($group)) ?></li>
<? endforeach ?>
</ul>
<? endif ?>

<? require(ROOT_DIR . '/include/footer.php') ?>