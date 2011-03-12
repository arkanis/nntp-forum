<?php

$CONFIG = array(
	'nntp' => array(
		// Transport URI for the NNTP connection, see http://php.net/transports.inet
		'uri' => 'ssl://news.hdm-stuttgart.de:563',
		// Timeout for the connection. Should be short since the user will see nothing but a white page during the
		// timeout. A value of 0.5 resulted in a connection timeout on the Debian VM, the value 1 worked.
		'timeout' => 1,
		// Stream options for the NNTP connection socket
		'options' => array(
			// SSL options to verify the connection certificate agains a CA certificate. See http://php.net/context.ssl
			'ssl' => array(
				'verify_peer' => true,
				// CA to verify against. The file have to be in the PEM format. To convert a DER file to PEM use
				// openssl x509 -inform DER -outform PEM -in hdm-stuttgart.de.der -out hdm-stuttgart.de.pem
				// To verify that all is working correctly with the cert use
				// socat stdio openssl:news.hdm-stuttgart.de:563,cafile=hdm-stuttgart.de.pem
				'cafile' => ROOT_DIR . '/certs/hdm-stuttgart.de.pem',
			)
		)
	),
	
	/**
	 * A list of newsfeeds. The key of every entry in this array will be the URL for the newsfeed (e.g. "offiziell" will
	 * be available as "/offiziell.xml" on the website. Each newsfeed needs to define the following configuration
	 * options:
	 * 
	 * 	'newsgroups': An RFC 977 wildmat (http://tools.ietf.org/html/rfc977#section-3.8) that lists the newsgroups
	 * 		that will be searched for new messages. Examples: "hdm.allgemein", "hdm.mi.*-offiziell",
	 * 		"hdm.*,!hdm.test.*" (matches all newsgroups in "hdm" but not any "hdm.test.*" newsgroups).
	 * 		Note that newsfeeds are cached and this cached data is not checked for authorization. Therefore using
	 * 		wildmats that might include messages not everyone should see is a bad idea.
	 * 	'title': The display name of the newsfeed.
	 * 	'history_duration': The number of seconds the NNTP server will look into the past to search for messages.
	 * 		Messages older than that time will not be reported by the NNTP server.
	 * 	'limit': The number of messages actually shown in the newsfeed.
	 */
	'newsfeeds' => array(
		'offiziell' => array(
			// 
			'newsgroups' => 'hdm.mi.*-offiziell',
			'title' => 'Offizielle News',
			'history_duration' => 60 * 60 * 24 * 30, // 1 month
			'limit' => 10
		),
		'messages' => array(
			// Listed the newsgroups explicitly since some users might see more newsgroups than others. The
			// wildmat "hdm.*" might contain messages meant for staff only but would be leaked when a student
			// gets cached newsfeed data.
			'newsgroups' => 'hdm.allgemein,hdm.suche_biete,hdm.mi.allgemein,hdm.mi.*-offiziell,!hdm.test.*',
			'title' => 'Neue Beiträge',
			'history_duration' => 60 * 60 * 24 * 30, // 1 month
			'limit' => 10
		)
	),
	
	// Connection and search settings for the LDAP name lookup. ldap2 only works in the student
	// IP range, ldap1 is the server for the internal IP range.
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
