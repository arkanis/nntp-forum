<?php

// If ROOT_DIR is already defined this page is used as an error message from another page.
// In that case the header is already included so do not define ROOT_DIR and don't include
// the header again.
if ( !defined('ROOT_DIR') ){
	define('ROOT_DIR', '../../..');
	require(ROOT_DIR . '/include/header.php');
}

// Setup layout variables
$title = 'Login ungültig';
?>

<h2>Login ungültig</h2>

<p>Sorry, aber mit deinen Login hast du leider keinen Zugriff. Der HdM-Account ist zwar gültig, aber leider konnte
damit die Newsgroup nicht gelesen werden.</p>
<ul>
	<li>Bitte wende dich an einen Mitarbeiter der Hochschule oder des Studiengangs Medieninformatik. Am besten
		an den Mitarbeiter, der die Newsgroup pflegt.</li>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>