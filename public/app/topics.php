<?php

define('ROOT_DIR', '../../');
require(ROOT_DIR . 'include/header.php');

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();
$group = sanitize_newsgroup_name($_GET['newsgroup']);

// Connecto to the newsgroups and get the message tree for this newsgroup. All root level messages
// in the tree are displayed as topics.
$nntp = nntp_connect_and_authenticate($CONFIG);
list($message_tree, $message_infos) = get_message_tree($nntp, $group);

if ( $message_tree == null )
	exit_with_not_found_error();

/*
// Query information abou the group we are supposed to display
$group = $_GET['newsgroup'];
list($status, $group_info) = $nntp->command('group ' . $group, array(211, 411));
if ($status == 411)
	exit_with_not_found_error();
list($estimated_post_count, $first_article_number, $last_article_number,) = explode(' ', $group_info);

// Build a message tree to get the name of all available topics
list($message_tree, $message_infos) = get_message_tree($nntp, $first_article_number, $last_article_number);
*/

// See if the current user is allowed to post in this newsgroup
$nntp->command('list active ' . $group, 215);
$group_info = $nntp->get_text_response();
list($name, $last_article_number, $first_article_number, $post_flag) = explode(' ', $group_info);
$posting_allowed = ($post_flag != 'n');

$nntp->close();

/*

// Extract the topic list
$topic_limit = 20;
$overview_range_size = 50;

$topics = array();
$range_end_number = $last_article_number;

$range_start_number = $range_end_number - $overview_range_size;
if ($range_start_number < $first_article_number)
	$range_start_number = $first_article_number;

list($status,) = $nntp->command('over ' . $range_start_number . '-' . $range_end_number, array(224, 423));
if ($status == 423)
	die('exploded');

print('<table>');
$overview_info = $nntp->get_text_response();
foreach(explode("\n", $overview_info) as $overview_line){
	list($number, $subject, $from, $date, $message_id, $references, $bytes, $lines, $rest) = explode("\t", $overview_line, 9);
	print('<tr><td>' . $number . '</td><td>' . h(Message::decode($subject)) . '</td><td>' . h($references) . '</td></tr>');
}
print('</table>');

$nntp->close();

exit();
*/


/*
// 

while( count($topics) < $topic_limit ){
	$range_start_number = $range_end_number - $overview_range_size;
	if ($range_start_number < $last_article_number)
		$range_start_number = $last_article_number;
	
	list($status,) = $nntp->command('over ' . $range_start_number . '-' . $range_end_number, array(224, 423));
	// Continue with the next range if there are no articles in the current range
	if ($status == 423)
		continue;
	
	$overview_info = $nntp->get_text_response();
	foreach(explode("\r\n", $overview_info) as $overview_line){
		list($number, $subject, $from, $date, $message_id, $references, $bytes, $lines) = explode("\t", $overview_line, 8);
	}
}

$nntp->command('stat ' . $last_article_number, 223);
while( count($topics) < $topic_limit ){
	list($status, $post_info) = $nntp->command('head', 221);
	list($post_number, $post_id,) = explode(' ', $post_info);
	
	$post = new Mail($nntp->get_text_response());
	if ( is_null($post->references) )
		$topics[] = array(
			'number' => $post_number,
			'title' => $post->subject,
			'date' => $post->date_as_time,
			'author' => $post->author_name
		);
	
	list($status,) = $nntp->command('last', array(223, 422));
	if ($status == 422)
		break;
}
*/

// Setup layout variables
$title = 'Forum ' . $group;
$breadcrumbs[$group] = '/' . $group;
$scripts[] = 'topics_index.js';
$body_class = 'topics';
?>

<h2><?= h($title) ?></h2>

<ul class="actions">
<? if($posting_allowed): ?>
	<li><a href="#" class="new topic">Neues Thema eröffnen</a></li>
<? endif ?>
</ul>

<form action="/<?= urlencode($group) ?>" method="post" class="message">
	
	<ul class="error">
		<li id="message_subject_error">Du hast vergessen einen Namen für das neue Thema anzugeben.</li>
		<li id="message_body_error">Du hast noch keinen Text für die Nachricht eingeben.</li>
	</ul>
	
	<section class="error" id="message-post-error">
		<h3>Beitrag konnte nicht gesendet werden</h3>
		
		<p>Der Beitrag wurde vom Newsgroup-Server leider nicht angenommen. Wahrscheinlich
		verfügst du nicht über die nötigen Rechte um in dieser Newsgroup Beiträge zu schreiben.</p>
		
		<p><samp>bla</samp></p>
	</section>
	
	<section class="notice" id="message-accepted">
		<h3>Beitrag noch nicht online</h3>
		
		<p>Der Beitrag wurde zwar akzeptiert, scheint aber noch nicht online zu sein. Möglicher
		weise dauert es ein paar Sekunden oder er muss erst vom Moderator bestätigt werden.</p>
		
		<p>Damit im Fall aller Fälle nichts verlohren geht kannst du den Text deines Beitrags kopieren
		und falls nötig später noch einmal senden.</p>
		
		<p>Ob der Beitrag online ist siehst du wenn du die Seite neu lädst.</p>
	</section>
	
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
			<label for="message_subject">Thema</label>
			<input name="subject" required id="message_subject" type="text" value="" />
		</p>
		<p>
			<textarea name="body" required id="message_body"></textarea>
		</p>
		<p class="buttons">
			<button class="preview recommended">Vorschau ansehen</button> oder
			<button class="create">Thema erstellen</button> oder
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

<table>
	<thead>
		<tr>
			<th>Thema</th>
			<th>Beiträge</th>
			<th>Neuster Beitrag</th>
		</tr>
	</thead>
	<tbody>
<? if ( empty($message_tree) ): ?>
		<tr>
			<td colspan="3" class="empty">
				Dieses Forum ist momentan noch leer.
			</td>
		</tr>
<? else: ?>
<?	foreach(array_reverse($message_tree) as $message_id => $replies): ?>
<?		// Find the last message of this thread by walking the array recursivly to
		// find the highest (newest) date. array_walk_recursive() only works with
		// leaves, therefore we have to use PHPs interesting iterators.
		$last_message_id = $message_id;
		$reply_iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($replies),  RecursiveIteratorIterator::SELF_FIRST );
		foreach($reply_iterator as $id => $children){
			if ( $message_infos[$id]['date'] > $message_infos[$last_message_id]['date'] )
				$last_message_id = $id;
		}
		
		$topic = $message_infos[$message_id];
		$reply_count = 1 + count($message_tree[$message_id], COUNT_RECURSIVE);
		$latest_message = $message_infos[$last_message_id];
?>
		<tr>
			<td><a href="/<?= urlencode($group) ?>/<?= urlencode($topic['number']) ?>?<?= $reply_count ?>"><?= h($topic['subject']) ?></a></td>
			<td><?= $reply_count ?></td>
			<td>
				Von <abbr title="<?= ha($latest_message['author_mail']) ?>"><?= h($latest_message['author_name']) ?></abbr><br />
				am <?= date('j.m.Y G:i', $latest_message['date']) ?> Uhr
			</td>
		</tr>
<?	endforeach ?>
<? endif ?>
	</tbody>
</table>

<? require(ROOT_DIR . 'include/footer.php') ?>