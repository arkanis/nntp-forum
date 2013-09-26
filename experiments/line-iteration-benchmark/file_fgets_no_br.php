<?php

$durations = [];
for($i = 0; $i < 11; $i++) {
	$start = microtime(true);
	
	$fd = fopen('../mails.txt', 'rb');
	$size = fstat($fd)['size'];
	while( ($line = fgets($fd)) !== false ) {
		$trimmed_line = ( $line[strlen($line) - 2] === "\r" ) ? substr($line, 0, -2) : substr($line, 0, -1);
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