<?php /** @var string $__yield */ ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Sign in') ?> | <?= e(config('app.name')) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-shell">
<div class="auth-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>
<main class="auth-container"><?= $__yield ?></main>
</body>
</html>
