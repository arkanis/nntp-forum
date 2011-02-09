<?php

define('ROOT_DIR', '../../../');
require(ROOT_DIR . 'include/header.php');

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

// Select the specified newsgroup for later content retrieval. We know it does exist (otherwise
// get_message_tree() would have failed).
$nntp->command('group ' . $group, 211);

// Setup layout variables
$title = $message_infos[$topic_id]['subject'];
$breadcrumbs[$group] = '/' . $group;
$breadcrumbs[$title] = '/' . $group . '/' . $topic_number;
$body_class = 'messages';
?>

<h2><?= h($title) ?></h2>

<?

// A recursive tree walker function. Unfortunately necessary because we start the recursion
// within the function (otherwise we could use an iterator).
function traverse_tree($tree_level){
	global $nntp, $message_infos, $group;
	
	echo("<ul>\n");
	foreach($tree_level as $id => $replies){
		list($status,) = $nntp->command('article ' . $id, array(220, 430));
		if ($status == 430)
			continue;
		
		$overview = $message_infos[$id];
		$content = null;
		$content_encoding = null;
		$attachments = array();
		
		$message_parser = new MessageParser(array(
			'part-header' => function($headers, $content_type, $content_type_params) use(&$content, &$content_encoding, &$attachments){
				if ($content == null and $content_type == 'text/plain') {
					$content_encoding = isset($content_type_params['charset']) ? $content_type_params['charset'] : 'ISO-8859-1';
					return 'append_content_line';
				} elseif ( isset($content_type_params['name']) ) {
					$name = MessageParser::decode($content_type_params['name']);
					$attachments[] = array('name' => $name, 'type' => $content_type, 'params' => $content_type_params, 'size' => null);
					return 'record_attachment_size';
				}
			},
			'append_content_line' => function($line) use(&$content){
				$content .= $line;
			},
			'record_attachment_size' => function($line) use(&$attachments){
				$current_attachment_index = count($attachments) - 1;
				$attachments[$current_attachment_index]['size'] += strlen($line) / 1.37;
			}
		));
		$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
		$content = iconv($content_encoding, 'UTF-8', $content);
		
		echo("<li>\n");
		echo("<article>\n");
		echo("	<header>\n");
		echo('		<p><abbr title="' . ha($overview['author_mail']) . '">' . h($overview['author_name']) . '</abbr>, ' . date('j.m.Y G:i', $overview['date']) . ' Uhr</p>' . "\n");
		echo("	</header>\n");
		echo('	' . Markdown($content) . "\n");
		
		if ( ! empty($attachments) ){
			echo('	<ul class="attachments">' . "\n");
			foreach($attachments as $attachment)
				echo('		<li><a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '">' . h($attachment['name']) . '</a> (' . intval($attachment['size'] / 1024) . ' KiByte)</li>' . "\n");
			echo("	</ul>\n");
		}
		
		echo("</article>\n");
		
		if ( count($replies) > 0 )
			traverse_tree($replies);
		
		echo("</li>\n");
	}
	
	echo("</ul>\n");
}

traverse_tree($thread_tree);
$nntp->close();

require(ROOT_DIR . 'include/footer.php');
?>