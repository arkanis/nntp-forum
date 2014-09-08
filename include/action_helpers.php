<?php

//
// This file contains helper functions used by the processing logic (actions) of the system.
//

/**
 * Creates a new NNTP connection and authenticates it with the user and password from the
 * requests HTTP headers. This is common in almost any page therefore it deserves a function
 * of it's own. :)
 * 
 * If an authentication user name is configured the authentication is performed. If the
 * NNTP authentication failed `exit_with_forbidden_error()` is called.
 */
function nntp_connect_and_authenticate($config){
	$nntp = new NntpConnection($config['nntp']['uri'], $config['nntp']['timeout'], $config['nntp']['options']);
	
	if ( isset($config['nntp']['user']) ){
		if ( ! $nntp->authenticate($config['nntp']['user'], $config['nntp']['pass']) ){
			$nntp->close();
			exit_with_forbidden_error();
		}
	}
	
	return $nntp;
}

/**
 * Builts the message tree of the specified newsgroup. Note that the tree itself is returned as a nested
 * array of message IDs. The message overview information is returned in a second array indexed by
 * those message IDs. If the specified newsgroup does not exist both return values are `null` (but still
 * two values are returned so the `list()` construct won't fail.
 * 
 * If cached data is used no command will be send over the NNTP connection. Therefore you _can not_
 * assume that the specified newsgroup is selected on the connection after this function.
 * 
 * This information caches the tree for the time specified in the `cache_lifetime` configuration option.
 */
function get_message_tree($nntp_connection, $newsgroup){
	return cached($newsgroup . '-message-tree', function() use(&$nntp_connection, $newsgroup){
		return built_message_tree($nntp_connection, $newsgroup);
	});
}

/**
 * Same as `get_message_tree()` but does not use cached data. Actually this function rebuilds the
 * cache each time it's called.
 */
function rebuilt_message_tree($nntp_connection, $newsgroup){
	clean_cache($newsgroup . '-message-tree');
	return get_message_tree($nntp_connection, $newsgroup);
}

/**
 * Builts the message tree of the specified newsgroup. Note that the tree itself is returned as a nested
 * array of message IDs. The message overview information is returned in a second array indexed by
 * those message IDs. If the specified newsgroup does not exist both return values are `null` (but still
 * two values are returned so the `list()` construct won't fail.
 */
function built_message_tree($nntp_connection, $newsgroup){
	// Select the specified newsgroup and return both parameters as `null` if the newsgroup does not exist.
	list($status, $group_info) = $nntp_connection->command('group ' . $newsgroup, array(211, 411));
	if ($status == 411)
		return array(null, null);
	
	list($estimated_post_count, $first_article_number, $last_article_number,) = explode(' ', $group_info);
	list($status,) = $nntp_connection->command('over ' . $first_article_number . '-' . $last_article_number, array(224, 423));
	
	$message_tree = array();
	$message_infos = array();
	
	// For status code 423 (group is empty) we just use the empty array. If 224 is returned messages
	// were found and we can read the overview information from the text response line by line.
	if ($status == 224){
		$nntp_connection->get_text_response_per_line(function($overview_line) use(&$message_tree, &$message_infos){
			list($number, $subject, $from, $date, $message_id, $references, $bytes, $lines, $rest) = explode("\t", $overview_line, 9);
			$referenced_ids = preg_split('/\s+/', $references, 0, PREG_SPLIT_NO_EMPTY);
			
			// Add this message to the tree, creating tree nodes for the referenced message if they
			// do not exists already. These nodes will be cleaned out later on if there is no matching
			// message in the message info array. The node for the current message is only initialized
			// with an empty array if it was not already created by a previously found child of it.
			$tree_level = &$message_tree;
			foreach($referenced_ids as $ref_id){
				if ( ! array_key_exists($ref_id, $tree_level) )
					$tree_level[$ref_id] = array();
				$tree_level = &$tree_level[$ref_id];
			}
			if ( ! isset($tree_level[$message_id]) )
				$tree_level[$message_id] = array();
			
			// Store display information for the message
			list($author_name, $author_mail) = MessageParser::split_from_header( MessageParser::decode_words($from) );
			$message_infos[$message_id] = array(
				'number' => intval($number),
				'subject' => MessageParser::decode_words($subject),
				'author_name' => $author_name,
				'author_mail' => $author_mail,
				'date' => MessageParser::parse_date($date)
			);
		});
		
		// Remove all nodes from the message tree that point to not existing message. The order is
		// not important since the topic list is sorted later on any way.
		$recursive_cleaner = null;
		$recursive_cleaner = function(&$tree) use(&$recursive_cleaner, $message_infos){
			reset($tree);
			// While with each() is needed here since for and foreach will only handle one appended
			// element before exiting the loop.
			while( list($id, $replies) = each($tree) ){
				if ( ! isset($message_infos[$id]) ) {
					// Message does not exist any more, add its replies to the parent level (those
					// are checked later on in the iteration too). Delete the message id after that.
					foreach($replies as $reply_id => $reply_children)
						$tree[$reply_id] = $reply_children;
					unset($tree[$id]);
				} else {
					// Message exists, check it's replies
					$recursive_cleaner($tree[$id]);
				}
			}
		};
		$recursive_cleaner($message_tree);
		
		// A nice debug output of the generated message tree
		/*
		$tree_iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($message_tree),  RecursiveIteratorIterator::SELF_FIRST );
		foreach($tree_iterator as $id => $children){
			echo( str_repeat('  ', $tree_iterator->getDepth()) . '- ' . $id . ': ' );
			if ( isset($message_infos[$id]) )
				printf("%s (%s)\n", $message_infos[$id]['subject'], date('r', $message_infos[$id]['date']));
			else
				echo("DELETED\n");
		}
		*/
	}
	
	return array($message_tree, $message_infos);
}

/**
 * Removes all invalid characters from the `$newsgroup_name`. See RFC 3977 section 4.1,
 * Wildmat Syntax (http://tools.ietf.org/html/rfc3977#section-4.1).
 */
function sanitize_newsgroup_name($newsgroup_name){
	return preg_replace('/ [ \x00-\x21 * , ? \[ \\ \] \x7f ] /x', '', $newsgroup_name);
}

/**
 * Builds a full URL out of a `$path` relative to the domain root. The path has to start with
 * a slash (`/`) to work.
 */
function url_for($path){
	$protocol = (empty($_SERVER['HTTPS']) or $_SERVER['HTTPS'] == 'off') ? 'http' : 'https';
	return $protocol . '://' . $_SERVER['HTTP_HOST'] . $path;
}

/**
 * Outputs the `unauthorized.php` error page with the 401 response code set and ends
 * the script.
 */
function exit_with_unauthorized_error(){
	global $CONFIG, $layout, $body_class, $breadcrumbs, $scripts;
	header('HTTP/1.1 401 Unauthorized');
	require(ROOT_DIR . '/public/app/errors/unauthorized.php');
	exit();
}

/**
 * Outputs the `forbidden.php` error page with the 403 response code set and ends
 * the script.
 */
function exit_with_forbidden_error(){
	global $CONFIG, $layout, $body_class, $breadcrumbs, $scripts;
	header('HTTP/1.1 403 Forbidden');
	require(ROOT_DIR . '/public/app/errors/forbidden.php');
	exit();
}

/**
 * Outputs the `not_found.php` error page with the 404 response code set and ends
 * the script.
 */
function exit_with_not_found_error(){
	global $CONFIG, $layout, $body_class, $breadcrumbs, $scripts;
	header('HTTP/1.1 404 Not Found');
	require(ROOT_DIR . '/public/app/errors/not_found.php');
	exit();
}

/**
 * This function makes caching easy. Just specify the cache file name and a closure
 * that calculates the data if necessary. If cached data is available and still within it's
 * lifetime (see configuration in $CONFIG) it will be returned at once. Otherwise the
 * closure is called to calculate the data and the result will be cached and returned.
 * 
 * The `$cache_file_name` is sanitized though `basename()` so only filenames work,
 * no subdirectories or something like that.
 * 
 * Example:
 * 
 * 	$data = cached('expensive_calc', function(){
 * 		// Do something expensive here...
 * 	});
 */
function cached($cache_file_name, $data_function)
{
	global $CONFIG;
	
	$cache_file_path = $CONFIG['cache_dir'] . '/' . basename($cache_file_name);
	
	if ( file_exists($cache_file_path) and filemtime($cache_file_path) + $CONFIG['cache_lifetime'] > time() ){
		$cached_data = @file_get_contents($cache_file_path);
		if ($cached_data)
			return unserialize($cached_data);
	}
	
	$data_to_cache = $data_function();
	@file_put_contents($cache_file_path, serialize($data_to_cache));
	return $data_to_cache;
}

/**
 * Clears the specified cache files. When you specify multiple arguments each
 * argument is interpreted as a cache file and deleted. All arguments are sanitized
 * though `basename()` so only filenames work, no subdirectories or something
 * like that.
 * 
 * Example:
 * 
 * 	clean_cache('expensive_calc_a', 'expensive_calc_b');
 */
function clean_cache($cache_file_name)
{
	global $CONFIG;
	
	foreach(func_get_args() as $cache_file_name)
		unlink($CONFIG['cache_dir'] . '/' . basename($cache_file_name));
}

/**
 * A little helper that looks what locales are available and what locales the user preferes
 * (by examining the HTTP `Accept-Language` header). It then returns the most prefered
 * locale available. If none of the prefered locales is available or no `Accept-Language`
 * was send the `$fallback_locale` is returned.
 * 
 * The details and format of the `Accept-Language` header are defined in RFC 2616,
 * chapter 14.4 (http://tools.ietf.org/html/rfc2616#section-14.4).
 */
function autodetect_locale_with_fallback($fallback_locale){
	if ( ! isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) )
		return $fallback_locale;
	
	// Look what locale files are available
	$locales_available = array_map(function($path){
		return basename($path, '.php');
	}, glob(ROOT_DIR . '/locales/*.php') );
	
	// Get the requested locale names and qualities out of the HTTP header
	$locales_requested = array();
	foreach( explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $qualified_locale ){
		if ( preg_match('/ (?<name> [\w\d-]+ ) ;q= (?<weight> \d \. \d+ ) /ix', $qualified_locale, $match) ) {
			$locales_requested[ $match['name'] ] = floatval($match['weight']);
		} else {
			$locales_requested[ $qualified_locale ] = 1.0;
		}
	}
	
	// Sort them so the most prefered locale is at the beginning
	arsort($locales_requested);
	
	// Now look for the first match
	foreach($locales_requested as $locale_name => $locale_quality){
		if ( in_array($locale_name, $locales_available) )
			return $locale_name;
	}
	
	// If nothing matched return the fallback locale
	return $fallback_locale;
}

?>