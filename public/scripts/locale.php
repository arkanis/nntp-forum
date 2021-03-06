<?php

define('ROOT_DIR', '../..');
require(ROOT_DIR . '/include/action_helpers.php');

$locale_name = basename($_GET['name']);
$locale = @include(ROOT_DIR . '/locales/' . $locale_name . '.php');
if (empty($locale))
	exit_with_not_found_error();

$locale_subset = array(
	'delete_dialog' => $locale['messages']['delete_dialog'],
	
	'show_quote' => $locale['messages']['show_quote'],
	'hide_quote' => $locale['messages']['hide_quote'],
	
	'show_replies' => $locale['messages']['show_replies'],
	'hide_replies' => $locale['messages']['hide_replies'],
	
	'subscribe_failed' => $locale['messages']['subscribe_failed'],
	'unsubscribe_failed' => $locale['messages']['unsubscribe_failed'],
	
	'months' => $locale['months'],
	'days' => $locale['days']
);

header("Content-type: text/javascript");
?>
var locale = <?= json_encode($locale_subset) ?>;