<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status) ?> — <?= e($title) ?> | <?= e(App\Core\Config::get('app.name', 'CompHub')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="error-page">
    <main class="error-shell" id="main">
        <p class="error-code"><?= e($status) ?></p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <p><a class="button" href="<?= e(url('/')) ?>">Kembali ke beranda</a></p>
    </main>
</body>
</html>
