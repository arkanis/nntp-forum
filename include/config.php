<?php

$CONFIG = array(
	// URI passed to the NntpConnection class which uses fsockopen() for the NNTP connection
	'news_uri' => 'ssl://news.hdm-stuttgart.de',
	// The port on which the NNTP server listens
	'port' => 563,
	
	// A list of newsfeeds
	'newsfeeds' => array(
		'offiziell' => array(
			// http://tools.ietf.org/html/rfc977#section-3.8
			'newsgroups' => 'hdm.mi.*-offiziell',
			'title' => 'Offizielle MI-Newsgroups',
			'limit' => 10,
			'history_duration' => 60 * 60 * 24 * 30 // 1 month
		)
	),
	
	// Connection and search settings for the LDAP name lookup
	'ldap' => array(
		'host' => 'ldap2.mi.hdm-stuttgart.de',
		'user' => 'uid=nobody,ou=userlist,dc=hdm-stuttgart,dc=de',
		'pass' => '',
		'directory' => 'ou=userlist,dc=hdm-stuttgart,dc=de'
	),
	
	'cache_dir' => ROOT_DIR . '/cache',
	'cache_lifetime' => 5 * 60,  // 5 minutes
	
	'unread_tracker_dir' => ROOT_DIR . '/unread-tracker',
	'unread_tracker_topic_limit' => 50,
	// Used by the clean-expired-trackers cron. Tracker that have not been modified for the
	// time specified here (in seconds) are deleted by the cron job. This will prevent a slow
	// disk overflow when students come and go.
	'unread_tracker_unused_expire_time' => 60 * 60 * 24 * 30 * 6,
	
	// The user agent string added as a message header. Important for others to see who is
	// responsible for an idealistically UTF-8 encoded message.
	'user_agent' => 'NNTP-Forum/1.0.0'
);

?>
