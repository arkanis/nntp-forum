<?php

// If ROOT_DIR is already defined this page is used as an error message from another page.
// In that case the header is already included so do not define ROOT_DIR and don't include
// the header again.
if ( !defined('ROOT_DIR') ){
	define('ROOT_DIR', '../../..');
	require(ROOT_DIR . '/include/header.php');
}

// Setup layout variables
$title = 'Login nötig';
?>

<h2><?= h($title) ?></h2>

<p>Sorry, aber bei dem Login irgendwas schief gegangen.</p>
<ul>
	<li>Vielleicht hast du dich bei deinem Passwort oder Benutzernamen vertippt. Versuche dich mit den gleichen
		Daten in deinem persönlichen Studenplan anzumelden.</li>
	<li>Wenn du gerade erst deinen HdM Account bekommen hast kann es sein, dass es bis zu einem Tag dauert,
		bis du dich hier anmelden kannst. In dem Fall bitte etwas Gedult.</li>
</ul>

<? require(ROOT_DIR . '/include/footer.php') ?>