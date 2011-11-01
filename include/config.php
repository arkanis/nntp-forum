<?php

// This is the default configuration file for the NNTP forum. This file contains the details
// of how the NNTP forum interacts with your infrastructure.
// 
// If the environment variable `ENVIRONMENT` is set a config file for that special
// environment is loaded. If `ENVIRONMENT` is set to `production` then `config.production.php`
// is loaded instead of `config.php`. This is a good way to keep your testing and development
// configuration seperate from your production configuration.
// 
// Environment variables can usually be set in the webserver configuration. In case of the
// Apache2 webserver you can use the `SetEnv` e.g. in the virtual host of the NNTP forum.

return array(
	'nntp' => array(
		// The login for the NNTP server. By default we pick up the HTTP authentication configured in the
		// webserver. If you want a public reader you can configure the NNTP user here that will be used
		// by the frontend.
		'user' => $_SERVER['PHP_AUTH_USER'],
		'pass' => $_SERVER['PHP_AUTH_PW'],
		
		// Transport URI for the NNTP connection, see http://php.net/transports.inet
		// For unencrypted NNTP servers use "tcp://news.example.com:119" (tcp on port 119), for encrypted
		// "ssl://news.example.com:563" (ssl on port 563).
		'uri' => 'tcp://news.example.com:119',
		// Timeout for the connection. Should be short since the user will see nothing but a white page during the
		// timeout. A value of 0.5 resulted in a connection timeout on the Debian VM, the value 1 worked.
		'timeout' => 1,
		// Stream options for the NNTP connection socket
		'options' => array(
			// SSL options to verify the connection certificate agains a CA certificate. See http://php.net/context.ssl
			'ssl' => array(
				// Set to `true` to enable the certificate check for encrypted connections.
				'verify_peer' => false,
				// CA to verify against. The file have to be in the PEM format. To convert a DER file to PEM use
				// openssl x509 -inform DER -outform PEM -in yourcert.der -out yourcert.pem
				// To verify that the converted certificate works correctly you can use the `socat` command to
				// connect to a newsgroup directly:
				// socat stdio openssl:news.example.com:563,cafile=yourcert.pem
				// If you see a welcome message from the news server everything worked perfectly.
				'cafile' => '/path/to/yourcert.pem',
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
		/* a small example newsfeed config
		'example' => array(
			'newsgroups' => 'all.news-*',
			'title' => 'All news',
			'history_duration' => 60 * 60 * 24 * 30, // 1 month
			'limit' => 10
		)
		*/
	),
	
	// Connection and search settings for the LDAP name lookup. This lookup is performed before a new message
	// is send to the NNTP server. It translates the login of the user into a display name that can then be used to
	// build a proper sender address (see below).
	// If 'host' is empty no LDAP lookup is performed. Otherwise insert your LDAP configuration here. For details
	// about the lookup itself take a look at the ldap_name_lookup() function in public/app/messages/create.php.
	'ldap' => array(
		'host' => null,
		'user' => 'uid=nobody,ou=userlist,dc=example,dc=com',
		'pass' => 'unknown',
		'directory' => 'ou=userlist,dc=example,dc=com'
	),
	
	// This function here builds the sender address of a new message (the thing that shows up in the "From" field
	// of the message). $login is the name used to connect to the NNTP server. $name is whatever the LDAP name
	// lookup returned (see above). If no LDAP name lookup is configured $name is the same as $login.
	'sender_address' => function($login, $name){
		return "$name <$login@example.com>";
	},
	
	// Function that determines if the specified address $mail belongs to the user $login. Right now this is used
	// to display the delete button only for messages you posted yourself (that is for all messages where this
	// function returns `true`).
	'sender_is_self' => function($mail, $login){
		return ($mail == "$login@example.com");
	},
	
	'cache_dir' => ROOT_DIR . '/cache',
	'cache_lifetime' => 5 * 60,  // 5 minutes
	
	'unread_tracker_dir' => ROOT_DIR . '/unread-tracker',
	'unread_tracker_topic_limit' => 50,
	// Used by the clean-expired-trackers cron job. Tracker that have not been modified for
	// the time specified here (in seconds) are deleted by the cron job. This will prevent a
	// slow disk overflow when students come and go.
	'unread_tracker_unused_expire_time' => 60 * 60 * 24 * 30 * 6,
	
	// The user agent string added as a message header. Important for others to see who is
	// responsible for an idealistically UTF-8 encoded message.
	'user_agent' => 'NNTP-Forum/1.0.0',
	
	// The title of the website shown in the header of each page.
	'title' => 'Newsgroups Forum',
	
	// The language file (locale) used for the forum.
	'lang' => autodetect_locale_with_fallback('en'),
	
	// The following stuff is a list of things a user can do when he sees an error page. You can
	// configure this list for every error page and every locale. For example you can add a note
	// to the `unauthorized` error page that users should send a mail to your support staff.
	'suggestions' => array(
		'forbidden' => array(),
		'not_found' => array(),
		'unauthorized' => array()
	),
	
	// Newsgroups howto link (e.g. 'http://example.com/news-howto.html'). This link is displayed
	// in the footer to provide a clue for newcommers on how to set up the newsgroups in
	// Thunderbird, etc.
	'howto_url' => null
);

?>
