<?php

// This page is meant to be included at the end of each page.

$content = ob_get_clean();
if ($layout)
	require('layouts/' . $layout . '.tpl.php');
else
	echo($content);

?>