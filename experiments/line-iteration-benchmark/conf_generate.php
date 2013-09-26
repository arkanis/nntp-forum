<?php

$fd = fopen('sample.conf', 'wb');
for($i = 0; $i < 2000000; $i++){
	$kind = rand(0, 100);
	// 70% pure config directives, 20% directives with comments, 10% pure comments
	if ($kind < 70)
		fprintf($fd, "directive%s: \"%s\"\n", rand(1000, 999999), md5(rand()));
	elseif ($kind < 90)
		fprintf($fd, "directive%s: \"%s\" # comment: %s\n", rand(1000, 999999), md5(rand()), md5(rand()));
	else
		fprintf($fd, "# comment: %s\n", md5(rand()));
}
fclose($fd);

?>