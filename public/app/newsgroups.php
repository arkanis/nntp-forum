<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

// Query overview information about all newsgroups. This information can not be cached
// since the list of newsgroups depends on the user priviliges. Caching would allow
// information of restricted newsgroups to be read by normal users.
$nntp = nntp_connect_and_authenticate($CONFIG);

$nntp->command('list', 215);
$newsgroup_list = $nntp->get_text_response();

$newsgroups = array();
foreach(explode("\n", $newsgroup_list) as $newsgroup){
	list($group, $last_article_number, $first_article_number, $posting) = explode(' ', $newsgroup);
	
	// Select this newsgroup and use the fresh information (not really necessary but a
	// good idea to use the most up to date message numbers)
	list(, $group_info) = $nntp->command('group ' . $group, 211);
	list($estimated_post_count, $first_article_number, $last_article_number,) = explode(' ', $group_info);
	
	// Loop until we got the latest post or are below the min article number
	$latest_post_number = $last_article_number;
	do {
		list($status, ) = $nntp->command('over ' . $latest_post_number, array(224, 423));
		$latest_post_number--;
	} while($status == 423 and $latest_post_number >= $first_article_number);
	
	if ($status == 224) {
		// Query and decode information if there is a last post
		$post_overview = $nntp->get_text_response();
		list($number, $subject, $from, $date, $message_id, $references, $bytes, $lines) = explode("\t", $post_overview, 8);
		list($author_name, $author_mail) = MessageParser::split_from_header( MessageParser::decode_words($from) );
		$latest_post = array(
			'number' => $number,
			'subject' => MessageParser::decode_words($subject),
			'author_name' => $author_name,
			'author_mail' => $author_mail,
			'date' => MessageParser::parse_date($date)
		);
	} else {
		// Or just give up if there is none
		$latest_post = null;
	}
	
	$newsgroups[$group] = array(
		'post_count' => $estimated_post_count,
		'last_post' => $latest_post
	);
}

// Query the newsgroup description file. The order of the file is also used as display order.
$nntp->command('list newsgroups', 215);
$descriptions = $nntp->get_text_response();
$nntp->close();

$ordered_newsgroups = array();
foreach(explode("\n", $descriptions) as $group_info){
	list($name, $description) = preg_split('/\s+/', $group_info, 2);
	$ordered_newsgroups[$name] = $newsgroups[$name];
	$ordered_newsgroups[$name]['description'] = trim($description);
}

// Append the newsgroups not mentioned in the description file below the ordered newsgroups.
foreach($newsgroups as $name => $infos){
	if ( ! array_key_exists($name, $ordered_newsgroups) )
		$ordered_newsgroups[$name] = $infos;
}

// Load the unread tracking information for this user
$tracker = new UnreadTracker($CONFIG['unread_tracker_dir'] . '/' . basename($CONFIG['nntp']['user']));

// Setup layout variables
$title = null;
$body_class = 'newsgroups';
?>

<h2>Forenübersicht</h2>

<table>
	<thead>
		<tr>
			<th>Newsgroup</th>
			<th>Beiträge</th>
			<th>Letzter Beitrag</th>
		</tr>
	</thead>
	<tbody>
<?	foreach($ordered_newsgroups as $name => $newsgroup): ?>
<?		if ( $tracker->is_newsgroup_unread($name, $newsgroup['last_post']['number']) ): ?>
		<tr class="unread">
<?		else: ?>
		<tr>
<?		endif ?>
			<td>
				<a href="/<?= urlencode($name) ?>"><?= h($name) ?></a>
<?				if ( isset($newsgroup['description']) ): ?>
				<small><?= h($newsgroup['description']) ?></small>
<?				endif ?>
			</td>
			<td><?= h($newsgroup['post_count']) ?></td>
<?			if($newsgroup['last_post']): ?>
			<td>
				<?= h($newsgroup['last_post']['subject']) ?><br />
				von <abbr title="<?= ha($newsgroup['last_post']['author_mail']) ?>"><?= h($newsgroup['last_post']['author_name']) ?></abbr> am <?= date('j.m.Y G:i', $newsgroup['last_post']['date']) ?> Uhr
			</td>
<?			else: ?>
			<td>-</td>
<?			endif ?>
		</tr>
<?	endforeach ?>
	</tbody>
</table>

<? require(ROOT_DIR . '/include/footer.php') ?>