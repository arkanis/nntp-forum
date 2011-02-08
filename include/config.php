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
	
	'cache_dir' => ROOT_DIR . '/cache',
	'cache_lifetime' => 5 * 60
);

?>