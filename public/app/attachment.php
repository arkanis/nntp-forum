<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/header.php');

if( !isset($_GET['newsgroup']) or !isset($_GET['number']) or !isset($_GET['attachment']) )
	exit_with_not_found_error();

$group = sanitize_newsgroup_name($_GET['newsgroup']);
$message_number = intval($_GET['number']);
$attachment_name = urldecode(basename($_GET['attachment']));

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

// Query the article and parse the text response by line. If we found the mime part of the
// attachment output all lines of it directly to the browser. The transfer encoding like base64
// is handled by the message parser.
$nntp->command('article ' . $message_id, 220);
$attachment_found = false;

$message_parser = new MessageParser(array(
	'part-header' => function($headers, $content_type, $content_type_params) use(&$attachment_name, &$attachment_found){
		if( isset($headers['content-disposition']) ) {
			list($disposition_type, $disposition_parms) = MessageParser::parse_type_params_header($headers['content-disposition']);
		} else {
			$disposition_type = null;
			$disposition_parms = array();
		}
		
		if ( isset($content_type_params['name']) )
			$name = $content_type_params['name'];
		if ( isset($disposition_parms['filename']) )
			$name = $disposition_parms['filename'];
		
		if ( isset($name) and $name == $attachment_name ){
			$attachment_found = true;
			header('Content-Type: ' . $headers['content-type']);
			if ( isset($headers['content-disposition']) )
				header('Content-Disposition: ' . $headers['content-disposition']);
			return 'emit-content';
		}
	},
	'emit-content' => function($line){
		echo($line);
	}
));
$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
$nntp->close();
$message_parser->end_of_message();

if ( !$attachment_found )
	exit_with_not_found_error();

?>