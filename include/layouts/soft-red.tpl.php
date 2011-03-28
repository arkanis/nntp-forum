<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title><? if ($title) echo(h($title) . ' - '); ?>HdM Newsgroups Forum</title>
	<link rel="stylesheet" type="text/css" href="/styles/soft-red.css" />
<?	foreach($CONFIG['newsfeeds'] as $name => $newsfeed): ?>
	<link href="/<?= urlencode($name) ?>.xml" rel="alternate" title="<?= h($newsfeed['title']) ?>" type="application/atom+xml" />
<?	endforeach ?>
</head>
<body class="<?= ha($body_class) ?>">

<header>
	<h1><a href="/">HdM Newsgroups Forum</a></h1>
	<nav>
		<ul id="utilities">
<?	foreach($CONFIG['newsfeeds'] as $name => $newsfeed): ?>
			<li><a class="newsfeed" href="/<?= urlencode($name) ?>.xml" type="application/atom+xml" rel="alternate"><?= h($newsfeed['title']) ?></a></li>
<?	endforeach ?>
		</ul>
		<ul id="breadcrumbs">
			<li><a href="/">Übersicht</a></li>
<?			foreach($breadcrumbs as $name => $url): ?>
			<li><a href="<?= ha($url); ?>"><?= h($name); ?></a></li>
<?			endforeach; ?>
		</ul>
	</nav>
</header>

<?= $content ?>

<footer>
<? if (isset($CONFIG['howto_url'])): ?>
	<a class="help" href="<?= ha($CONFIG['howto_url']) ?>">Newsgroups in E-Mail-Program (z.B. Thunderbird) einrichten</a><br />
<? endif ?>
<?	list($name, $version) = explode('/', $CONFIG['user_agent'], 2) ?>
	<?= h($name) ?> v<?= h($version) ?>, entwickelt von <a href="http://arkanis.de/">Stephan Soller</a>.
	<a href="http://www.famfamfam.com/lab/icons/silk/">Silk Icons</a> von <a href="http://www.famfamfam.com/">famfamfam.com</a>.
</footer>

<? foreach($scripts as $script): ?>
<script src="/scripts/<?= ha($script) ?>"></script>
<? endforeach ?>

</body>
</html>