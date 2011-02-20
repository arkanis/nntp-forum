<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();
$group = sanitize_newsgroup_name($_GET['newsgroup']);

// Connecto to the newsgroups and get the message tree for this newsgroup. All root level messages
// in the tree are displayed as topics.
$nntp = nntp_connect_and_authenticate($CONFIG);
list($message_tree, $message_infos) = get_message_tree($nntp, $group);

if ( $message_tree == null )
	exit_with_not_found_error();

// See if the current user is allowed to post in this newsgroup
$nntp->command('list active ' . $group, 215);
$group_info = $nntp->get_text_response();
list($name, $last_article_number, $first_article_number, $post_flag) = explode(' ', $group_info);
$posting_allowed = ($post_flag != 'n');

$nntp->close();

// Setup layout variables
$title = 'Forum ' . $group;
$breadcrumbs[$group] = '/' . $group;
$scripts[] = 'topics.js';
$body_class = 'topics';
?>

<h2><?= h($title) ?></h2>

<ul class="actions above">
<? if($posting_allowed): ?>
	<li class="new topic"><a href="#">Neues Thema eröffnen</a></li>
<? endif ?>
</ul>

<form action="/<?= urlencode($group) ?>" method="post" enctype="multipart/form-data" class="message">
	
	<ul class="error">
		<li id="message_subject_error">Du hast vergessen einen Namen für das neue Thema anzugeben.</li>
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
			<label for="message_subject">Thema</label>
			<input name="subject" required id="message_subject" type="text" value="" />
		</p>
		<p>
			<textarea name="body" required id="message_body"></textarea>
		</p>
		<dl>
			<dt>Anhänge</dt>
				<dd><input name="attachments[]" type="file" /> <a href="#" class="destroy attachment">löschen</a></dd>
		</dl>
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

<ul class="actions below">
<? if($posting_allowed): ?>
	<li class="new topic"><a href="#">Neues Thema eröffnen</a></li>
<? endif ?>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>