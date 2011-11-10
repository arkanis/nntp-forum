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

// Load existing unread tracking information and mark new topics as unread
if ( $CONFIG['unread_tracker']['file'] ) {
	$tracker = new UnreadTracker($CONFIG['unread_tracker']['file']);
	$tracker->update_and_save($group, $message_tree, $message_infos, $CONFIG['unread_tracker']['topic_limit']);
} else {
	$tracker = null;
}

if ( isset($_GET['all-read']) and $tracker ){
	// If the `all-read` parameter is set mark all topics in this group as read and
	// reload the page with a redirect (so the parameter is no longer in the URL).
	$tracker->mark_all_topics_read($group);
	header('Location: ' . url_for('/' . $group));
	exit();
}

// Gather all information for the topics so we can do easy sorting and don't have to do so much in the template
$topics = array();
foreach($message_tree as $message_id => $replies){
	// Find the last message of this thread by walking the replys recursivly to
	// find the highest (newest) date. array_walk_recursive() only works with
	// leaves, therefore we have to use PHPs interesting iterators.
	$last_message_id = $message_id;
	$reply_iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($replies),  RecursiveIteratorIterator::SELF_FIRST );
	foreach($reply_iterator as $id => $children){
		if ( $message_infos[$id]['date'] > $message_infos[$last_message_id]['date'] )
			$last_message_id = $id;
	}
	
	$latest_message = $message_infos[$last_message_id];
	$topic_message = $message_infos[$message_id];
	$reply_count = 1 + count($message_tree[$message_id], COUNT_RECURSIVE);
	
	$topics[] = array(
		'message' => $topic_message,
		'latest_message' => $latest_message,
		'reply_count' => $reply_count,
		'unread' => $tracker ? $tracker->is_topic_unread($group, $topic_message['number']) : false
	);
}

// Sort the topics. If one of the two topics is unread the unread topic will always be shown first.
// If both are read or unread compare by date (newest is shown first).
usort($topics, function($a, $b){
	if ($a['unread'] and !$b['unread']) {
		return -1;
	} elseif (!$a['unread'] and $b['unread']) {
		return 1;
	} else {
		return ($a['latest_message']['date'] > $b['latest_message']['date']) ? -1 : 1;
	}
});

// Setup layout variables
$title = l('topics', 'title', $group);
$breadcrumbs[$group] = '/' . $group;
$scripts[] = 'topics.js';
$body_class = 'topics';
?>

<h2><?= h($title) ?></h2>

<ul class="actions above">
<? if($posting_allowed): ?>
	<li class="new topic"><a href="#"><?= lh('topics', 'new_topic') ?></a></li>
<? endif ?>
	<li class="all read"><a href="/<?= urlencode($group) ?>?all-read"><?= lh('topics', 'all_read') ?></a></li>
</ul>

<form action="/<?= urlencode($group) ?>" method="post" enctype="multipart/form-data" class="message">
	
	<ul class="error">
		<li id="message_subject_error"><?= lh('message_form', 'errors', 'missing_subject') ?></li>
		<li id="message_body_error"><?= lh('message_form', 'errors', 'missing_body') ?></li>
	</ul>
	
	<section class="help">
		<?= l('message_form', 'format_help') ?> 
	</section>
	
	<section class="fields">
		<p>
			<label for="message_subject"><?= lh('message_form', 'topic_label') ?></label>
			<input name="subject" required id="message_subject" type="text" value="" />
		</p>
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
			<button class="create"><?= lh('message_form', 'create_topic_button') ?></button>
			<?= lh('message_form', 'button_separator') ?> 
			<button class="cancel"><?= lh('message_form', 'cancel_button') ?></button>
		</p>
	</section>
	
	<article id="post-preview">
		<header>
			<p><?= lh('message_form', 'preview_heading_prefix') ?> <span></span></p>
		</header>
		
		<div></div>
	</article>
</form>

<table>
	<thead>
		<tr>
			<th><?= lh('topics', 'topic_header') ?></th>
			<th><?= lh('topics', 'post_count_header') ?></th>
			<th><?= lh('topics', 'last_post_header') ?></th>
		</tr>
	</thead>
	<tbody>
<? if ( empty($message_tree) ): ?>
		<tr>
			<td colspan="3" class="empty">
				<?= lh('topics', 'no_topics') ?> 
			</td>
		</tr>
<? else: ?>
<?	foreach($topics as $topic): ?>
<?		if ( $tracker and $tracker->is_topic_unread($group, $topic['message']['number']) ): ?>
		<tr class="unread">
<?		else: ?>
		<tr>
<?		endif ?>
			<td><a href="/<?= urlencode($group) ?>/<?= urlencode($topic['message']['number']) ?>?<?= $topic['reply_count'] ?>"><?= h($topic['message']['subject']) ?></a></td>
			<td><?= $topic['reply_count'] ?></td>
			<td>
				<?= l('topics', 'last_post_info',
					sprintf('<abbr title="%s">%s</abbr>', ha($topic['latest_message']['author_mail']), h($topic['latest_message']['author_name'])),
					date(l('topics', 'last_post_info_date_format'), $topic['latest_message']['date'])
				) ?> 
			</td>
		</tr>
<?	endforeach ?>
<? endif ?>
	</tbody>
</table>

<ul class="actions below">
<? if($posting_allowed): ?>
	<li class="new topic"><a href="#"><?= lh('topics', 'new_topic') ?></a></li>
<? endif ?>
	<li class="all read"><a href="/<?= urlencode($group) ?>?all-read"><?= lh('topics', 'all_read') ?></a></li>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>