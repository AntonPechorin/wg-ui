<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'WG Panel', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php if (isset($_SESSION['user'])): ?>
<nav class="nav">
    <a href="/">Dashboard</a>
    <a href="/clients">Clients</a>
    <form method="post" action="/logout" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(App\Support\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Logout</button>
    </form>
</nav>
<?php endif; ?>
<main class="container">
    <?php include $templatePath; ?>
</main>
</body>
</html>
