<?php

$content = ob_get_clean();
if ($layout)
	require('layouts/' . $layout . '.tpl.php');
else
	echo($content);

?>