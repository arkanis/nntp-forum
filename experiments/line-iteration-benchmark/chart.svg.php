<?php

$options = [
	'chart' => [
		'height' => 200,
		'margin' => 10,
		'horizontal_padding' => 10,
		'grid_lines' => 5
	],
	'records' => [
		'margin' => 50,
		'label_height' => 20,
		'values' => [
			'width' => 40,
			'margin' => 5,
			'label_height' => 14,
			'unit_height' => 6
		]
	],
	'legend' => [
		'top_margin' => 10,
		'line_height' => 20,
		'text_offset' => 12
	]
];

/*
$axes = [
	['name' => 'Throughput in MiByte/s',			'unit' => 'MiB/s',	'to' => 325,	'style' => 'fill: #3366cc', 'label_style' => ''],
	['name' => 'Peak memory usage in MiByte',	'unit' => 'MiB',	'to' => 4096,	'style' => 'fill: #dc3912', 'label_style' => '']
];

$records = [
	['fgets',			261.35,				0.50],
	['stream_get_line',	175.75,				0.50],
	['fgets with rtrim',	123.25,				0.50],
	['fscanf',			68.04,				0.50]
];
*/
$axes = [
	['name' => 'Throughput in MiByte/s',			'unit' => 'MiB/s',	'to' => 325,	'style' => 'fill: #3366cc', 'label_style' => ''],
	['name' => 'Peak memory usage - buffer size in MiByte',	'unit' => 'MiB',	'to' => 4096,	'style' => 'fill: #dc3912', 'label_style' => '']
];

$records = [
	['fgets',			243.75,	1015.75 - 507.71221447],
	['strtok',			237.73,	1015.75 - 507.71221447],
	['stream_get_line',	166.35,	1015.75 - 507.71221447],
	['strpos',			111.21,	508.00 - 507.71221447],
	['preg_split',		23.40,	3079.75 - 507.71221447]
];


$record_width = count($axes) * $options['records']['values']['width'] + (count($axes) - 1) * $options['records']['values']['margin'];
$chart_width = $options['chart']['horizontal_padding'] * 2 + count($records) * $record_width + (count($records) - 1) * $options['records']['margin'];

$total_width = $options['chart']['margin'] * 2 + $chart_width;
$total_height = $options['chart']['height'] + $options['chart']['margin'] * 2 + $options['records']['label_height'] + $options['legend']['top_margin'] + count($axes) * $options['legend']['line_height'];

$legend_x = $options['chart']['height'] + $options['records']['label_height'] + $options['legend']['top_margin'];

function round_to_digits($number, $digits){
	$log10 = log10($number);
	$integer_digits = ceil($log10);
	$fraction_digits = max($digits - $integer_digits, 0);
	// For sub zero nubers we need a digit for the leading 0
	if ($integer_digits <= 0)
		$fraction_digits--;
	
	$rounded = round($number, $fraction_digits);
	return sprintf('%' . ($integer_digits + $fraction_digits) . '.' . $fraction_digits . 'f', $rounded);
}

?>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="<?= $total_width ?>" height="<?= $total_height ?>">
	<defs>
		<style type="text/css">
			text { font-family: sans-serif; font-size: 14px; color: #333; }
			text.value { text-anchor: middle; }
			text.unit { text-anchor: middle; font-size: 12px; }
			text.label { text-anchor: middle; }
			text.legend {}
		</style>
	</defs>
<?#	<rect x="0" y="0" width="100%" height="100%" fill="white"></rect> ?>
	<g transform="translate(<?= $options['chart']['margin'] ?>, <?= $options['chart']['margin'] ?>)">
		<g>
<?			for($i = 0; $i < $options['chart']['grid_lines']; $i++): ?>
			<rect x="0" y="<?= $options['chart']['height'] / $options['chart']['grid_lines'] * $i ?>" width="<?= $chart_width ?>" height="1" fill="#ccc"></rect>
<?			endfor ?>
			<rect x="0" y="<?= $options['chart']['height'] ?>" width="<?= $chart_width ?>" height="1" fill="black"></rect>
		</g>
<?		foreach($records as $record_index => $record): ?>
<?
			$title = array_shift($record);
			$x = $options['chart']['horizontal_padding'] + ($record_width + $options['records']['margin']) * $record_index;
			
?>
		<g id="records" transform="translate(<?= $x ?>, 0)">
<?			foreach($axes as $index => $axis): ?>
<?
				$value_height = ceil(($options['chart']['height'] / $axis['to']) * $record[$index]);
				$value_height = max($value_height, 1);
				$value_x = ($options['records']['values']['width'] + $options['records']['values']['margin']) * $index;
				$value_y = $options['chart']['height'] - $value_height;
				$value_x_center = $value_x + $options['records']['values']['width'] / 2;
				$unit_y = $value_y - $options['records']['values']['unit_height'];
				$label_y = $unit_y - $options['records']['values']['label_height'];
?>
			<rect x="<?= $value_x ?>" y="<?= $value_y ?>" width="<?= $options['records']['values']['width'] ?>" height="<?= $value_height ?>" style="<?= $axis['style'] ?>"></rect>
			<text class="value" x="<?= $value_x_center ?>" y="<?= $label_y ?>"><?= round_to_digits($record[$index], 3) ?></text>
			<text class="unit" x="<?= $value_x_center ?>" y="<?= $unit_y ?>"><?= $axis['unit'] ?></text>
<?			endforeach ?>
			<text class="label" x="<?= $record_width / 2 ?>" y ="<?= $options['chart']['height'] + $options['records']['label_height'] ?>"><?= $title ?></text>
		</g>
<?		endforeach ?>
		
		<g transform="translate(0, <?= $legend_x ?>)">
<?		foreach($axes as $axis_index => $axis): ?>
			<g id="foo<?= $axis_index ?>">
				<rect x="0" y="<?= $axis_index * $options['legend']['line_height'] ?>" width="14" height="14" style="<?= $axis['style'] ?>"></rect>
				<text class="legend" x="18" y="<?= $axis_index * $options['legend']['line_height'] + $options['legend']['text_offset'] ?>"><?= $axis['name'] ?></text>
			</g>
<?		endforeach ?>
		</g>
	</g>
</svg>