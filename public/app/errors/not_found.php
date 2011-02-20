<?php

// If ROOT_DIR is already defined this page is used as an error message from another page.
// In that case the header is already included so do not define ROOT_DIR and don't include
// the header again.
if ( !defined('ROOT_DIR') ){
	define('ROOT_DIR', '../../..');
	require(ROOT_DIR . '/include/header.php');
}

// Setup layout variables
$title = 'Unbekannte Adresse';
?>

<h2><?= h($title) ?></h2>

<p>Sorry, aber zu der Adresse <code><?= h($_SERVER['REQUEST_URI']) ?></code> konnte nichts passendes gefunden werden.</p>
<ul>
	<li>Vielleicht hast du dich bei der URL nur vertippt. Ein kurzer Blick in die Adressleiste sollte dann reichen.</li>
	<li>Das entsprechende Thema oder die entsprechende Newsgroup existiert nicht mehr. In dem Fall hilft leider
		nur in <a href="/">den Newsgroups</a> nach etwas ähnlichem zu suchen.</li>
	<li>Vielleicht gibt es aber auch gerade Probleme mit den Newsgroups oder der Website. Wenn das so ist sind wir
		sicher schon dabei wieder alles gerade zu biegen.</li>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>