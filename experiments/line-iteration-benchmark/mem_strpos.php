<?php

$data = file_get_contents('../mails.txt');
fprintf(STDERR, "Loading complete, peak mem: %.2f MiB\n", memory_get_peak_usage(true) / (1024*1024));

$durations = [];
for($i = 0; $i < 11; $i++) {
	$start = microtime(true);
	
	$pos = 0;
	while ( ($line_end_pos = strpos($data, "\r\n", $pos)) !== false ) {
		$line = substr($data, $pos, $line_end_pos - $pos);
		
		$pos = $line_end_pos + 2;
	}
	
	$duration = microtime(true) - $start;
	$durations[] = $duration;
	fprintf(STDERR, "%s throughput: %.2f MiB/s, peak mem: %.2f MiB\n", basename(__file__), strlen($data) / $duration / (1024*1024), memory_get_peak_usage(true) / (1024*1024));
}

// Discard first run. After it all data should be in the kernel read cache and IO should no longer distort the results.
array_shift($durations);
$avg_duration = array_sum($durations) / count($durations);
fprintf(STDERR, "%s average throughput: %.2f MiB/s, peak mem: %.2f MiB\n", basename(__file__), strlen($data) / $avg_duration / (1024*1024), memory_get_peak_usage(true) / (1024*1024));

?>