<?php

define('ROOT_DIR', '../../');
require(ROOT_DIR . 'include/header.php');

if( !isset($_GET['newsgroup']) or !isset($_GET['number']) or !isset($_GET['attachment']) )
	exit_with_not_found_error();

$group = sanitize_newsgroup_name($_GET['newsgroup']);
$message_number = intval($_GET['number']);
$attachment_name = basename($_GET['attachment']);

// Connect to the newsgroup and get the (possibly cached) message tree and information.
$nntp = nntp_connect_and_authenticate($CONFIG);
list(, $message_infos) = get_message_tree($nntp, $group);

// If the newsgroup does not exists show the "not found" page.
if ( $message_infos == null )
	exit_with_not_found_error();

// Now look up the message id for the specified message number. If there is no message with
// that number show a "not found" page.
$message_id = null;
foreach($message_infos as $id => $overview_info){
	if ($overview_info['number'] == $message_number){
		$message_id = $id;
		break;
	}
}

if ($message_id == null)
	exit_with_not_found_error();

// Select the specified newsgroup for later content retrieval. We know it does exist (otherwise
// get_message_tree() would have failed).
$nntp->command('group ' . $group, 211);

$message_parser = new Message($attachment_name);
$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
$message_parser->finish();

$nntp->close();

?>