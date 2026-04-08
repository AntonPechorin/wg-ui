<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>QR <?= e($name) ?></title>
    <style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:16px}pre{font-size:12px;line-height:1}</style>
</head>
<body>
<h3>QR для клиента: <?= e($name) ?></h3>
<pre><?= e($qr) ?></pre>
</body>
</html>
