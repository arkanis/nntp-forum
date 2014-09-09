<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

if (empty($_SERVER['PHP_AUTH_USER']))
	exit_with_unauthorized_error();


function locked_file_edit($path, $default_data, $action) {
	$file_created = false;
	
	$fd = fopen($path, 'r+');
	if (!$fd) {
		$fd = fopen($path, 'w+');
		if (!$fd)
			return false;
		
		$file_created = true;
	}
	
	if ( ! flock($fd, LOCK_EX) ) {
		fclose($fd);
		return false;
	}
	
	if ($file_created) {
		$data = $default_data;
	} else {
		$data = stream_get_contents($fd);
		if (!$data) {
			flock($fd, LOCK_UN);
			fclose($fd);
			return false;
		}
	}
	
	try {
		$new_data = $action($data);
	} catch (Exception $e) {
		flock($fd, LOCK_UN);
		fclose($fd);
		throw $e;
	}
	
	if ( ! (rewind($fd) and ftruncate($fd, 0) and fwrite($fd, $new_data)) ) {
		flock($fd, LOCK_UN);
		fclose($fd);
		return false;
	}
	
	flock($fd, LOCK_UN);
	fclose($fd);
	
	return true;
}

// Fetch the users subscriptions
list($subscribed_messages, $user_message_list_file) = load_subscriptions();
$watchlist_file = $CONFIG['subscriptions']['watchlist'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	// The user is subscribing to a new message
	$id = trim(file_get_contents('php://input'));
	
	if (empty($id))
		exit_with_not_found_error();
	
	if (!in_array($id, $subscribed_messages)) {
		$subscribed_messages[] = $id;
		if ( ! file_put_contents($user_message_list_file, json_encode($subscribed_messages)) ) {
			header('HTTP/1.1 500 Internal Server Error');
			exit();
		}
		
		// All fine so far, let's add this to the watch list
		$result = locked_file_edit($watchlist_file, array(), function($data) use($id) {
			$list = json_decode($data, true);
			
			if ( ! isset($list[$id]) )
				$list[$id] = array();
			$list[$id][$_SERVER['PHP_AUTH_USER']] = array(time(), $id);
			
			return json_encode($list);
		});
		
		if ( ! $result ) {
			header('HTTP/1.1 500 Internal Server Error');
			exit();
		}
	}
	
	header('Location: ' . url_for('/your/subscriptions/' . urlencode($id)));
	header('HTTP/1.1 201 Created');
	exit();
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
	// The user is unsubscribing a message
	$id = trim($_GET['id']);
	if (empty($id))
		exit_with_not_found_error();
	
	if ( ($key = array_search($id, $subscribed_messages)) !== false ) {
		unset($subscribed_messages[$key]);
		if ( ! file_put_contents($user_message_list_file, json_encode($subscribed_messages)) ) {
			header('HTTP/1.1 500 Internal Server Error');
			exit();
		}
		
		// All fine so far, let's remove this from the watch list
		$result = locked_file_edit($watchlist_file, array(), function($data) use($id) {
			$list = json_decode($data, true);
			$user = $_SERVER['PHP_AUTH_USER'];
			
			unset($list[$id][$user]);
			if (empty($list[$id]))
				unset($list[$id]);
			
			return json_encode($list);
		});
		
		if ( ! $result ) {
			header('HTTP/1.1 500 Internal Server Error');
			exit();
		}
		
		header('HTTP/1.1 204 No Content');
		exit();
	} else {
		exit_with_not_found_error();
	}
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' or $_SERVER['REQUEST_METHOD'] == 'HEAD') {
	// Display a website so the user can manage his subscriptions
	
} else {
	// No idea what request method landed here...
	exit_with_not_found_error();
}

// Setup layout variables
$title = l('subscriptions', 'title');
$breadcrumbs[l('subscriptions', 'title')] = '/your/subscriptions';
$body_class = 'subscriptions';
?>

<h2><?= lh('subscriptions', 'title') ?></h2>

<ul>
<? foreach($subscribed_messages as $message_id): ?>
	<li><?= h($message_id) ?></li>
<? endforeach ?>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>