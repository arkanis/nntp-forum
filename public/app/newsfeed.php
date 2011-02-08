<?php

define('ROOT_DIR', '../../');

if ( !isset($_SERVER['PHP_AUTH_USER']) or !isset($_SERVER['PHP_AUTH_PW']) )
	die('Keine Anmeldedaten vorhanden um die Newsgroup zu lesen!');

require(ROOT_DIR . 'include/header.php');

if ( !( isset($_GET['name']) and array_key_exists($_GET['name'], $CONFIG['newsfeeds']) ) )
	die('Der angeforderte Newsfeed wurde leider nicht gefunden.');

$nntp = new NntpConnection($CONFIG['news_uri'], $CONFIG['port']);
$nntp->authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

$feed_config = $CONFIG['newsfeeds'][$_GET['name']];

$start_date = date('Ymd His', time() - $feed_config['history_duration']);
$nntp->command('newnews ' . $feed_config['newsgroups'] . ' ' . $start_date, 230);
$new_post_ids = $nntp->get_text_response();

$posts = array();
foreach(explode("\n", $new_post_ids) as $post_id){
	$nntp->command('article ' . $post_id, 220);
	$posts[] = new Message( $nntp->get_text_response() );
	if ( count($posts) >= $feed_config['limit'] )
		break;
}

$nntp->close();

// Setup layout variables
$title = $feed_config['title'];
$layout = 'atom-feed';
$feed_url = 'http://' . urlencode($_SERVER['SERVER_NAME']) . '/' . urlencode($_GET['name']) . '.xml';
$updated = isset($posts[0]) ? $posts[0]->date_as_time : time();
?>
<? foreach ($posts as $post): ?>
	<entry>
		<title><?= h($post->subject) ?></title>
		<author>
			<name><?= h($post->author_name) ?></name>
			<email><?= h($post->author_mail) ?></email>
		</author>
		<id>nntp://<?= urlencode(trim($post->message_id, '<>')) ?>/</id>
		<updated><?= date('c', $post->date_as_time); ?></updated>
		<content type="html">
<?= h(Markdown($post->content)) . "\n" ?>
		</content>
	</entry>
	
<? endforeach ?>
<? require(ROOT_DIR . 'include/footer.php') ?>