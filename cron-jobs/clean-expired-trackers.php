#!/usr/bin/php
<?php

/**
 * The file is intended to be used as a cron job to clean out old tracker files for users that do not
 * use the NNTP forum. Tracker files which have not been updated (modified) for the time period
 * defined in the `unread_tracker_unused_expire_time` config option are deleted.
 * 
 * To enable this cron job just create a symlink for it in the /etc/cron.daily directory.
 */

define('ROOT_DIR', dirname(__FILE__) . '/..');
require(ROOT_DIR . '/include/config.php');

$tracker_files = glob($CONFIG['unread_tracker_dir'] . '/*');
foreach($tracker_files as $file){
	if ( filemtime($file) + $CONFIG['unread_tracker_unused_expire_time'] < time() )
		unlink($file);
}

?>