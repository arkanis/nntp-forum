<?php

// If ROOT_DIR is already defined this page is used as an error message from another page.
// In that case the header is already included so do not define ROOT_DIR and don't include
// the header again.
if ( !defined('ROOT_DIR') ){
	define('ROOT_DIR', '../../..');
	require(ROOT_DIR . '/include/header.php');
}

// Setup layout variables
$title = l('error_pages', 'unauthorized', 'title');
$suggestions = l('error_pages', 'unauthorized', 'suggestions');
if ( isset($CONFIG['suggestions']['unauthorized'][$CONFIG['lang']]) )
	$suggestions = array_merge($suggestions, $CONFIG['suggestions']['unauthorized'][$CONFIG['lang']]);
?>

<h2><?= h($title) ?></h2>

<p><?= l('error_pages', 'unauthorized', 'description') ?></p>

<? if ( count($suggestions) ): ?>
<ul>
<? foreach($suggestions as $suggestion): ?>
	<li><?= $suggestion ?></li>
<? endforeach ?>
</ul>
<? endif ?>

<? require(ROOT_DIR . '/include/footer.php') ?>