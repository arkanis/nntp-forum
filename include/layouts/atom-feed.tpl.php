<? header('Content-Type: application/atom+xml'); ?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?= h($title) ?></title>
	<id><?= h($feed_url) ?></id>
	<link href="<?= h($feed_url); ?>" rel="self" />
	<updated><?= $updated->format('c') ?></updated>
	
<?= $content; ?>
</feed>