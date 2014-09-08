<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

// Query overview information about all newsgroups (filtered by the configured wildmat).
// This information can not be cached since the list of newsgroups depends on the user
// priviliges. Caching would allow information of restricted newsgroups to be read by
// normal users.
$nntp = nntp_connect_and_authenticate($CONFIG);

$nntp->command('list active ' .  $CONFIG['newsgroups']['filter'], 215);
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
		
		if ( empty($post_overview) ) {
			// Bugfix for INN 2.4, it always returns 224, even if it does not provide valid post data
			$latest_post = null;
		} else {
			list($number, $subject, $from, $date, $message_id, $references, $bytes, $lines) = explode("\t", $post_overview, 8);
			list($author_name, $author_mail) = MessageParser::split_from_header( MessageParser::decode_words($from) );
			$latest_post = array(
				'number' => $number,
				'subject' => MessageParser::decode_words($subject),
				'author_name' => $author_name,
				'author_mail' => $author_mail,
				'date' => MessageParser::parse_date_and_zone($date)
			);
		}
	} else {
		// Or just give up if there is none
		$latest_post = null;
	}
	
	$newsgroups[$group] = array(
		'post_count' => $estimated_post_count,
		'last_post' => $latest_post
	);
}

// Query the newsgroup description file. The order of these descriptions are also used as a
// starting point for the display order.
$nntp->command('list newsgroups', 215);
$descriptions = $nntp->get_text_response();
$nntp->close();

// The `trim()` call is a bugfix for INN 2.4
$desc_ordered_newsgroups = array();
if ( !empty($descriptions) ){
	foreach(explode("\n", $descriptions) as $group_info){
		list($name, $description) = preg_split('/\s+/', $group_info, 2);
		if ( isset($newsgroups[$name]) ){
			// Only show a group if it was returned by the initial `list` (active) command.
			// Otherwise we might get a description for a not selectable group.
			$desc_ordered_newsgroups[$name] = $newsgroups[$name];
			$desc_ordered_newsgroups[$name]['description'] = trim($description);
		}
	}
}

// Append the newsgroups not mentioned in the description file below the ordered newsgroups.
foreach($newsgroups as $name => $infos){
	if ( ! array_key_exists($name, $desc_ordered_newsgroups) )
		$desc_ordered_newsgroups[$name] = $infos;
}

// Sort the newsgroups according to the configuration
if ( is_array($CONFIG['newsgroups']['order']) ) {
	// If we got an array of names show that groups first, the rest after them
	$ordered_newsgroups = array();
	foreach( $CONFIG['newsgroups']['order'] as $name )
		if ( array_key_exists($name, $desc_ordered_newsgroups) )
			$ordered_newsgroups[$name] = $desc_ordered_newsgroups[$name];
	foreach( $desc_ordered_newsgroups as $name => $infos )
		if ( ! array_key_exists($name, $ordered_newsgroups) )
			$ordered_newsgroups[$name] = $infos;
} else if ( is_callable($CONFIG['newsgroups']['order']) ) {
	// If a callable is supplied let it order the list
	$ordered_newsgroups = call_user_func($CONFIG['newsgroups']['order'], $desc_ordered_newsgroups);
} else {
	// If nothing is configured just use the order of the description list
	$ordered_newsgroups = $desc_ordered_newsgroups;
}

// Load the unread tracking information for this user
if ( $CONFIG['unread_tracker']['file'] )
	$tracker = new UnreadTracker($CONFIG['unread_tracker']['file']);
else
	$tracker = null;

// Setup layout variables
$title = null;
$body_class = 'newsgroups';
?>

<h2><?= lh('newsgroups', 'title') ?></h2>

<table>
	<thead>
		<tr>
			<th><?= lh('newsgroups', 'newsgroup_header') ?></th>
			<th><?= lh('newsgroups', 'post_count_header') ?></th>
			<th><?= lh('newsgroups', 'last_post_header') ?></th>
		</tr>
	</thead>
	<tbody>
<?	foreach($ordered_newsgroups as $name => $newsgroup): ?>
<?		if ( $tracker and $tracker->is_newsgroup_unread($name, $newsgroup['last_post']['number']) ): ?>
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
				<?= h($newsgroup['last_post']['subject']) ?>
				<small><?= l('newsgroups', 'last_post_info',
					sprintf('<abbr title="%s">%s</abbr>', ha($newsgroup['last_post']['author_mail']), h($newsgroup['last_post']['author_name'])),
					timezone_aware_date($newsgroup['last_post']['date'], l('newsgroups', 'last_post_info_date_format'))
				) ?></small>
			</td>
<?			else: ?>
			<td><?= lh('newsgroups', 'no_last_post') ?></td>
<?			endif ?>
		</tr>
<?	endforeach ?>
	</tbody>
</table>

<? require(ROOT_DIR . '/include/footer.php') ?>