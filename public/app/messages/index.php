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
	global $nntp, $message_infos, $group, $posting_allowed;
	
	// Variables for the message parser event handlers to store their information in. These variables
	// have to be reset after a message is parsed.
	$content = null;
	$content_encoding = null;
	$attachments = array();
	
	// Setup the message parser events to record the first text/plain part and record attachment
	// information if present.
	$message_parser = new MessageParser(array(
		'part-header' => function($headers, $content_type, $content_type_params) use(&$content, &$content_encoding, &$attachments){
			if ($content == null and $content_type == 'text/plain') {
				$content_encoding = isset($content_type_params['charset']) ? $content_type_params['charset'] : 'ISO-8859-1';
				$content = '';
				return 'append-content-line';
			} elseif ( isset($content_type_params['name']) ) {
				$name = $content_type_params['name'];
				$attachments[] = array('name' => $name, 'type' => $content_type, 'params' => $content_type_params, 'size' => null);
				return 'record-attachment-size';
			}
		},
		'append-content-line' => function($line) use(&$content){
			$content .= $line;
		},
		'record-attachment-size' => function($line) use(&$attachments){
			$current_attachment_index = count($attachments) - 1;
			$attachments[$current_attachment_index]['size'] += strlen($line);
		}
	));
	
	echo("<ul>\n");
	foreach($tree_level as $id => $replies){
		$overview = $message_infos[$id];
		
		list($status,) = $nntp->command('article ' . $id, array(220, 430));
		if ($status == 220){
			$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
			// $content, $content_encoding and $attachments are set by the event handlers of the parser
			$content = Markdown( iconv($content_encoding, 'UTF-8', $content) );
		} else {
			$content = '<p class="empty">Dieser Beitrag wurde vom Autor gelöscht.</p>';
			$attachments = array();
		}
		
		echo("<li>\n");
		echo('<article data-number="' . ha($overview['number']) . '">' . "\n");
		echo("	<header>\n");
		echo('		<p><a href="mailto:' . ha($overview['author_mail']) . '" title="' . ha($overview['author_mail']) . '">' . h($overview['author_name']) . '</a>, ' . date('j.m.Y G:i', $overview['date']) . ' Uhr</p>' . "\n");
		echo("	</header>\n");
		echo('	' . $content . "\n");
		
		if ( ! empty($attachments) ){
			echo('	<ul class="attachments">' . "\n");
			foreach($attachments as $attachment)
				echo('		<li><a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '">' . h($attachment['name']) . '</a> (' . intval($attachment['size'] / 1024) . ' KiByte)</li>' . "\n");
			echo("	</ul>\n");
		}
		
		echo('		<ul class="actions">' . "\n");
		if($posting_allowed)
			echo('			<li class="new message"><a href="#">Antworten</a></li>' . "\n");
		if($overview['author_mail'] == $_SERVER['PHP_AUTH_USER'] . '@hdm-stuttgart.de')
			echo('			<li class="destroy message"><a href="#">Nachricht löschen</a></li>' . "\n");
		echo('		</ul>' . "\n");
		
		echo("</article>\n");
		
		// Reset message variables to make a clean start for the next message
		$content = null;
		$content_encoding = null;
		$attachments = array();
		$message_parser->reset();
		
		if ( count($replies) > 0 )
			traverse_tree($replies);
		
		echo("</li>\n");
	}
	
	echo("</ul>\n");
}

traverse_tree($thread_tree);
$nntp->close();

?>

<form action="/<?= urlencode($group) ?>/<?= urlencode($topic_number) ?>" method="post" enctype="multipart/form-data" class="message">
	
	<ul class="error">
		<li id="message_body_error">Du hast noch keinen Text für die Nachricht eingeben.</li>
	</ul>
	
	<section class="help">
		<h3>Kurze Format-Übersicht</h3>
		
		<dl>
			<dt>Absätze</dt>
				<dd>
<pre>
Absätze werden durch eine
Leerzeile getrennt.

Nächster Absatz.
</pre>
				</dd>
			<dt>Listen</dt>
				<dd>
<pre>
Listen können mit `*` oder `-`
erstellt werden:

- Erster Eintrag
- Zweiter
* Letzter
</pre>
				</dd>
			<dt>Links</dt>
				<dd>
<pre>
Übersichtlicher [Link][1] im
Fließtext.

[1]: http://www.hdm-stuttgart.de/

Oder ein [direkter
Link](http://www.hdm-stuttgart.de/).
</pre>
				</dd>
		</dl>
	</section>
	
	<section class="fields">
		<p>
			<textarea name="body" required id="message_body"></textarea>
		</p>
		<dl>
			<dt>Anhänge</dt>
				<dd><input type="file" /> <a href="#" class="destroy attachment">löschen</a></dd>
		</dl>
		<p class="buttons">
			<button class="preview recommended">Vorschau ansehen</button> oder
			<button class="create">Antwort absenden</button> oder
			<button class="cancel">Abbrechen</button>
		</p>
	</section>
	
	<article id="post-preview">
		<header>
			<p>Vorschau</p>
		</header>
		
		<div></div>
	</article>
</form>

<? require(ROOT_DIR . '/include/footer.php') ?>