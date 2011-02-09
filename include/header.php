<?php

require('config.php');
require('nntp_connection.php');
require('message_parser.php');
require('view_helpers.php');
require('action_helpers.php');
require('markdown.php');

// Setup variables for the layout and start output redirection to capture any content code
$title = '';
$breadcrumbs = array();
$layout = 'soft-red';
$scripts = array('jquery.min.js');
$body_class = '';
ob_start();

?>