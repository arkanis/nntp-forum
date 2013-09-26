<?php

$durations = [];
for($i = 0; $i < 11; $i++) {
	$start = microtime(true);
	
	$fd = fopen('sample.conf', 'rb');
	$size = fstat($fd)['size'];
	while(true){
		$line = fgets($fd);
		if ($line === false)
			break;
		preg_match('/^(?:#.*|([^:]+):\s+"([^"]*)"(?:\s#.*)?)/', $line, $matches);
		// Skip comment lines
		if ( count($matches) === 1 )
			continue;
		//printf("%s: %s\n", $matches[1], $matches[2]);
	}
	fclose($fd);
	
	$duration = microtime(true) - $start;
	$durations[] = $duration;
	fprintf(STDERR, "%s throughput: %.2f MiB/s, peak mem: %.2f MiB\n", basename(__file__), $size / $duration / (1024*1024), memory_get_peak_usage(true) / (1024*1024));
}

// Discard first run. After it all data should be in the kernel read cache and IO should no longer distort the results.
array_shift($durations);
$avg_duration = array_sum($durations) / count($durations);
fprintf(STDERR, "%s average throughput: %.2f MiB/s, peak mem: %.2f MiB\n", basename(__file__), $size / $avg_duration / (1024*1024), memory_get_peak_usage(true) / (1024*1024));

?>