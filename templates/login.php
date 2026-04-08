<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WG Panel</title>
    <style>
        body {font-family: Inter, Arial, sans-serif; background:#0f172a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0;}
        .box {background:#111827; padding:32px; border-radius:12px; width:340px; box-shadow:0 12px 24px rgba(0,0,0,.35);}
        h1 {margin:0 0 20px; font-size:22px;}
        input {width:100%; margin-bottom:12px; padding:10px; border-radius:8px; border:1px solid #334155; background:#0b1220; color:#fff;}
        button {width:100%; padding:10px; border:none; background:#2563eb; color:white; border-radius:8px; cursor:pointer;}
        .error {background:#7f1d1d; color:#fecaca; padding:8px; border-radius:6px; margin-bottom:12px;}
    </style>
</head>
<body>
<div class="box">
    <h1>WireGuard Panel</h1>
    <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="text" name="login" placeholder="Логин" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>
