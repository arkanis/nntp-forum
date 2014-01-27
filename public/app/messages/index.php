<?php

define('ROOT_DIR', '../../..');
require(ROOT_DIR . '/include/header.php');

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();
if( !isset($_GET['number']) )
	exit_with_not_found_error();

$group = sanitize_newsgroup_name($_GET['newsgroup']);
$topic_number = intval($_GET['number']);

// Connect to the newsgroup and get the (possibly cached) message tree and information.
$nntp = nntp_connect_and_authenticate($CONFIG);
list($message_tree, $message_infos) = get_message_tree($nntp, $group);

// If the newsgroup does not exists show the "not found" page.
if ( $message_tree == null )
	exit_with_not_found_error();

// Now look up the message id for the topic number (if there is no root level message with
// a matching number show a "not found" page).
$topic_id = null;
foreach(array_keys($message_tree) as $message_id){
	if ($message_infos[$message_id]['number'] == $topic_number){
		$topic_id = $message_id;
		break;
	}
}

if ($topic_id == null)
	exit_with_not_found_error();

// Extract the subtree for this topic
$thread_tree = array( $topic_id => $message_tree[$topic_id] );

// Load existing unread tracking information and update it in case the user jumped here with
// a direct link the tracker was not updated by the topic indes before. Otherwise messages added
// since the last update (newer than the tracked watermark) will be marked as unread on the
// next update, even if the user alread viewed the message now.
if ( $CONFIG['unread_tracker']['file'] ) {
	$tracker = new UnreadTracker($CONFIG['unread_tracker']['file']);
	$tracker->update($group, $message_tree, $message_infos, $CONFIG['unread_tracker']['topic_limit']);
} else {
	$tracker = null;
}

// See if the current user is allowed to post in this newsgroup
$nntp->command('list active ' . $group, 215);
$group_info = $nntp->get_text_response();
list($name, $last_article_number, $first_article_number, $post_flag) = explode(' ', $group_info);
$posting_allowed = ($post_flag != 'n');

// Select the specified newsgroup for later content retrieval. We know it does exist (otherwise
// get_message_tree() would have failed).
$nntp->command('group ' . $group, 211);

// Setup layout variables
$title = $message_infos[$topic_id]['subject'];
$breadcrumbs[$group] = '/' . $group;
$breadcrumbs[$title] = '/' . $group . '/' . $topic_number;
$scripts[] = 'messages.js';
$body_class = 'messages';
?>

<h2><?= h($title) ?></h2>

<?

// A recursive tree walker function. Unfortunately necessary because we start the recursion
// within the function (otherwise we could use an iterator).
function traverse_tree($tree_level){
	global $nntp, $message_infos, $group, $posting_allowed, $tracker, $topic_number, $CONFIG;
	
	// Default storage area for each message. This array is used to reset the storage area for the event
	// handlers after a message is parsed.
	$empty_message_data = array(
		'newsgroup' => null,
		'content' => null,
		'attachments' => array()
	);
	// Storage area for message parser event handlers
	$message_data = $empty_message_data;
	
	// Setup the message parser events to record the first text/plain part and record attachment
	// information if present.
	$message_parser = MessageParser::for_text_and_attachments($message_data);
	
	// The following scary bit of code extends the message parser to generate image previews while
	// the message is parsed. It is event driven code like the rest of the parser. We wrap new anonymous
	// functions around the events thar are already there.
	// This wrapper pattern (inspired by Lisp and JavaScript) should be moved to the `for_text_and_attachments()`
	// function as "extended events". But for now it works.
	if ($CONFIG['thumbnails']['enabled']){
		// Original event handlers. Remember them here to call them later on.
		$old_message_header = $message_parser->events['message-header'];
		$old_part_header = $message_parser->events['part-header'];
		$old_record_attachment_size = $message_parser->events['record-attachment-size'];
		$old_part_end = $message_parser->events['part-end'];
		
		// State variables used across our event handlers
		$message_id;
		$raw_data = null;
		
		// Only record the message ID from the message headers. We need it to build a unique hash.
		$message_parser->events['message-header'] = function($headers) use($old_message_header, &$message_id){
			$message_id = $headers['message-id'];
			return $old_message_header($headers);
		};
		
		// If we got an image and it's not already in the thumbnail cache set `$raw_data` so the other
		// events will take action.
		$message_parser->events['part-header'] = function($headers, $content_type, $content_type_params) use($old_part_header, &$raw_data, &$message_id, &$message_data){
			$content_event = $old_part_header($headers, $content_type, $content_type_params);
			if ( $content_event == 'record-attachment-size' and preg_match('#image/.*#', $content_type) ){
				$last_index = count($message_data['attachments']) - 1;
				$display_name = $message_data['attachments'][$last_index]['name'];
				$cache_name = md5($message_id . $display_name);
				$message_data['attachments'][$last_index]['preview'] = $cache_name;
				
				// If there is no cached version available kick of the data recording and preview generation
				if ( ! file_exists(ROOT_DIR . '/public/thumbnails/' . $cache_name) )
					$raw_data = array();
			}
			return $content_event;
		};
		
		// Record raw image data if requested. Append each data chunk to the `$raw_data` array to avoid
		// to many concatinations.
		$message_parser->events['record-attachment-size'] = function($line) use($old_record_attachment_size, &$raw_data){
			if ( $raw_data !== null )  // is_array() makes trouble in this spot, seems to hand in an endless loop
				$raw_data[] = $line;
			return $old_record_attachment_size($line);
		};
		
		// We're at the end of an MIME part. If we got raw data to process load the actual image from them.
		// Create a thumbnail version and put it into the cache.
		$message_parser->events['part-end'] = function() use($old_part_end, $CONFIG, &$raw_data, &$message_data){
			if ( $raw_data !== null ){
				$data = join('', $raw_data);
				$image = @imagecreatefromstring($data);
				$preview_created = false;
				
				if ($image) {
					$width = imagesx($image);
					$height = imagesy($image);
					
					if ($width > $height) {
						// Landscape format
						$preview_width = $CONFIG['thumbnails']['width'];
						$preview_height = $height / ($width / $CONFIG['thumbnails']['width']);
					} else {
						// Portrait format
						$preview_height = $CONFIG['thumbnails']['height'];
						$preview_width = $width / ($height / $CONFIG['thumbnails']['height']);
					}
					
					$preview_image = imagecreatetruecolor($preview_width, $preview_height);
					imagecopyresampled($preview_image, $image, 0, 0, 0, 0, $preview_width, $preview_height, $width, $height);
					imagedestroy($image);
					
					$last_index = count($message_data['attachments']) - 1;
					$cache_name = $message_data['attachments'][$last_index]['preview'];
					
					$preview_created = @imagejpeg($preview_image, ROOT_DIR . '/public/thumbnails/' . $cache_name, $CONFIG['thumbnails']['quality']);
					imagedestroy($preview_image);
				}
				
				if (!$preview_created) {
					// If we could not create the preview kill the preview name from the message data
					$last_index = count($message_data['attachments']) - 1;
					unset($message_data['attachments'][$last_index]['preview']);
				}
				
				$raw_data = null;
			}
			return $old_part_end();
		};
	}
	
	echo("<ul>\n");
	foreach($tree_level as $id => $replies){
		$overview = $message_infos[$id];
		
		list($status,) = $nntp->command('article ' . $id, array(220, 430));
		if ($status == 220){
			$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
			$message_parser->end_of_message();
			// All the stuff in `$message_data` is set by the event handlers of the parser
			$content = Markdown($message_data['content']);
		} else {
			$content = '<p class="empty">' . l('messages', 'deleted') . '</p>';
			$message_data['attachments'] = array();
		}
		
		echo("<li>\n");
		$unread_class = ( $tracker and $tracker->is_message_unread($group, $topic_number, $overview['number']) ) ? ' class="unread"' : '';
		printf('<article id="message-%d" data-number="%d"%s>' . "\n", $overview['number'], $overview['number'], $unread_class);
		echo("	<header>\n");
		echo("		<p>");
		echo('			' . l('messages', 'message_header', 
			sprintf('<a href="mailto:%1$s" title="%1$s">%2$s</a>', ha($overview['author_mail']), h($overview['author_name'])),
			date( l('messages', 'message_header_date_format'), $overview['date'] )
		) . "\n");
		printf('			<a class="permalink" href="/%s/%d#message-%d">%s</a>' . "\n", urlencode($group), $topic_number, $overview['number'], l('messages', 'permalink'));
		echo("		</p>\n");
		echo("	</header>\n");
		echo('	' . $content . "\n");
		
		if ( ! empty($message_data['attachments']) ){
			echo('	<ul class="attachments">' . "\n");
			echo('		<li>' . lh('messages', 'attachments') . '</li>' . "\n");
			foreach($message_data['attachments'] as $attachment){
				if ( isset($attachment['preview']) ) {
					echo('		<li class="thumbnail" style="background-image: url(/thumbnails/' . $attachment['preview'] . ');">' . "\n");
				} else {
					echo('		<li>' . "\n");
				}
				echo('			<a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '">' . h($attachment['name']) . '</a>' . "\n");
				echo('			(' . number_to_human_size($attachment['size']) . ')' . "\n");
				echo('		</li>' . "\n");
			}
			echo("	</ul>\n");
		}
		
		echo('		<ul class="actions">' . "\n");
		if($posting_allowed)
			echo('			<li class="new message"><a href="#">' . l('messages', 'answer') . '</a></li>' . "\n");
		if($CONFIG['sender_is_self']($overview['author_mail'], $CONFIG['nntp']['user']))
			echo('			<li class="destroy message"><a href="#">' . l('messages', 'delete') . '</a></li>' . "\n");
		echo('		</ul>' . "\n");
		
		echo("</article>\n");
		
		// Reset message variables to make a clean start for the next message
		$message_parser->reset();
		$message_data = $empty_message_data;
		
		if ( count($replies) > 0 )
			traverse_tree($replies);
		
		echo("</li>\n");
	}
	
	echo("</ul>\n");
}

traverse_tree($thread_tree);
$nntp->close();
if ($tracker)
	$tracker->mark_topic_read($group, $topic_number);

?>

<form action="/<?= urlencode($group) ?>/<?= urlencode($topic_number) ?>" method="post" enctype="multipart/form-data" class="message">
	
	<ul class="error">
		<li id="message_body_error"><?= lh('message_form', 'errors', 'missing_body') ?></li>
	</ul>
	
	<section class="help">
		<?= l('message_form', 'format_help') ?> 
	</section>
	
	<section class="fields">
		<p>
			<textarea name="body" required id="message_body"></textarea>
		</p>
		<dl>
			<dt><?= lh('message_form', 'attachments_label') ?></dt>
				<dd><input name="attachments[]" type="file" /> <a href="#" class="destroy attachment"><?= l('message_form', 'delete_attachment') ?></a></dd>
		</dl>
		<p class="buttons">
			<button class="preview recommended"><?= lh('message_form', 'preview_button') ?></button>
			<?= lh('message_form', 'button_separator') ?> 
			<button class="create"><?= lh('message_form', 'create_answer_button') ?></button>
			<?= lh('message_form', 'button_separator') ?> 
			<button class="cancel"><?= lh('message_form', 'cancel_button') ?></button>
		</p>
	</section>
	
	<article id="post-preview">
		<header>
			<p><?= lh('message_form', 'preview_heading') ?></p>
		</header>
		
		<div></div>
	</article>
</form>

<script>
	// Locale sensitive strings for the scripts
	var locale = {
		delete_dialog: {
			question: '<?= lha('messages', 'delete_dialog', 'question') ?>',
			yes: '<?= lha('messages', 'delete_dialog', 'yes') ?>',
			no: '<?= lha('messages', 'delete_dialog', 'no') ?>'
		},
		
		show_quote: '<?= lha('messages', 'show_quote') ?>',
		hide_quote: '<?= lha('messages', 'hide_quote') ?>',
		
		show_replies: '<?= lha('messages', 'show_replies') ?>',
		hide_replies: '<?= lha('messages', 'hide_replies') ?>'
	};
</script>

<? require(ROOT_DIR . '/include/footer.php') ?>