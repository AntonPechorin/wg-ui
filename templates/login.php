<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WG Panel</title>
    <style>
        :root{--bg:#eef6ff;--card:#ffffff;--line:#cfe3ff;--text:#1e3a5f;--primary:#6da8ff;--primary-dark:#4a8ff5}
        body{font-family:Inter,Arial,sans-serif;background:linear-gradient(180deg,#eaf4ff 0%,#f4f9ff 100%);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .box{background:var(--card);padding:32px;border-radius:16px;width:360px;box-shadow:0 14px 30px rgba(109,168,255,.22);border:1px solid var(--line)}
        h1{margin:0 0 18px;font-size:24px;color:#27507f}
        p{margin:0 0 16px;color:#4f7096;font-size:14px}
        input{width:100%;margin-bottom:12px;padding:11px;border-radius:10px;border:1px solid var(--line);background:#f7fbff;color:var(--text);box-sizing:border-box}
        input:focus{outline:none;border-color:#9bc2ff;box-shadow:0 0 0 3px rgba(109,168,255,.18)}
        button{width:100%;padding:11px;border:none;background:var(--primary);color:white;border-radius:10px;cursor:pointer;font-weight:600}
        button:hover{background:var(--primary-dark)}
        .error{background:#ffe9ee;color:#9d3957;padding:9px;border-radius:8px;margin-bottom:12px;border:1px solid #ffd0dc}
    </style>
</head>
<body>
<div class="box">
    <h1>WireGuard Panel</h1>
    <p>Вход в панель управления VPN.</p>
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
