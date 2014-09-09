<?php

// This page is meant to be included at the begin of each page. Right after the `ROOT_DIR`
// constant has been defined.

// Load first because the `autodetect_lang()` function can be used in the configuration file.
require('action_helpers.php');

// If we are run in an environment load the matching config file. Otherwise just load the
// defaul config.
if ($_CONFIG_ENV = getenv('ENVIRONMENT'))
	$CONFIG = require( basename("config.$_CONFIG_ENV.php") );
else
	$CONFIG = require('config.php');

// Load the configured locale
$_LOCALE = require(ROOT_DIR . '/locales/' . $CONFIG['lang'] . '.php');

// Load the used classes, functions and helpers
require('nntp_connection.php');
require('message_parser.php');
require('unread_tracker.php');
require('view_helpers.php');
require('markdown.php');

// Setup variables for the layout and start output redirection to capture any content code
$title = '';
$breadcrumbs = array();
$layout = 'soft-red';
$scripts = array('jquery.min.js', 'timezone-converter.js');
$body_class = '';
ob_start();

?>