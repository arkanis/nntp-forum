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

/*
$posts = array();

$nntp->command('article', 220);
$posts[] = new Message( $nntp->get_text_response() );
list($status,) = $nntp->command('next', array(223, 421));

while($status == 223){
	$nntp->command('head', 221);
	$post = new Message($nntp->get_text_response());
	if ( $post->references ) {
		$referenced_ids = explode(' ', $post->references);
		if (in_array($topic_id, $referenced_ids)){
			$nntp->command('body', 222);
			$post->store_body( $nntp->get_text_response() );
			$posts[] = $post;
		}
	}
	list($status,) = $nntp->command('next', array(223, 421));
}
*/

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
	global $nntp, $message_infos;
	
	echo("<ul>\n");
	foreach($tree_level as $id => $replies){
		list($status,) = $nntp->command('article ' . $id, array(220, 430));
		if ($status == 430)
			continue;
		
		$overview = $message_infos[$id];
		$message_parser = new Message();
		$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
		$message_parser->finish();
		
		echo("<li>\n");
		echo("<article>\n");
		echo("	<header>\n");
		echo('		<p><abbr title="' . ha($overview['author_mail']) . '">' . h($overview['author_name']) . '</abbr>, ' . date('j.m.Y G:i', $overview['date']) . ' Uhr</p>' . "\n");
		echo("	</header>\n");
		echo('	' . Markdown($message_parser->content) . "\n");
		
		if ( ! empty($message_parser->attachments) ){
			echo('	<ul class="attachments">' . "\n");
			foreach($message_parser->attachments as $attachment)
				echo('		<li>' . h($attachment['name']) . ' (' . intval($attachment['size'] / 1024) . ' KiByte)</li>' . "\n");
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