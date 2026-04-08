<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Panel</title>
    <style>
        body{font-family:Inter,Arial,sans-serif;background:#f1f5f9;margin:0;color:#0f172a}
        .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
        .card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 4px 12px rgba(15,23,42,.08);margin-bottom:16px}
        .top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
        .badge{padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #e2e8f0;font-size:14px;text-align:left;vertical-align:top}
        .actions form,.actions a{display:inline-block;margin:2px}
        button,.btn{padding:8px 10px;border:none;border-radius:8px;background:#1d4ed8;color:#fff;text-decoration:none;cursor:pointer;font-size:12px}
        .btn.gray,button.gray{background:#475569}
        .btn.red,button.red{background:#b91c1c}
        .status-online{color:#166534;font-weight:700}.status-offline{color:#9a3412;font-weight:700}.status-blocked{color:#991b1b;font-weight:700}
        .flash{padding:10px;border-radius:8px;background:#dbeafe;margin-bottom:12px}
        input[type=text]{padding:8px;border:1px solid #cbd5e1;border-radius:8px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card top">
        <div>
            <strong>WireGuard status:</strong> <span class="badge"><?= e($wgStatus) ?></span>
        </div>
        <div>
            <form method="post" action="/server/restart" style="display:inline-block">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="gray">Restart WireGuard</button>
            </form>
            <form method="post" action="/server/reboot" style="display:inline-block" onsubmit="return confirm('Reboot server?')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="red">Reboot server</button>
            </form>
            <form method="post" action="/logout" style="display:inline-block">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="gray">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <form method="post" action="/clients/create">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="text" name="name" required placeholder="Имя клиента: user1">
            <button type="submit">Создать клиента</button>
        </form>
    </div>

    <?php if (!empty($flash)): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Имя</th><th>IP</th><th>Статус</th><th>Handshake</th><th>RX</th><th>TX</th><th>Endpoint</th><th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= e((string)$c['name']) ?></td>
                    <td><?= e((string)$c['ip']) ?></td>
                    <td class="status-<?= e((string)$c['runtime_status']) ?>"><?= e((string)$c['runtime_status']) ?></td>
                    <td><?= e((string)$c['latest_handshake']) ?></td>
                    <td><?= e((string)$c['rx']) ?></td>
                    <td><?= e((string)$c['tx']) ?></td>
                    <td><?= e((string)$c['endpoint']) ?></td>
                    <td class="actions">
                        <a class="btn" href="/clients/<?= e((string)$c['name']) ?>/download">Скачать</a>
                        <a class="btn gray" href="/clients/<?= e((string)$c['name']) ?>/qr" target="_blank">QR</a>
                        <?php if (!empty($c['blocked'])): ?>
                            <form method="post" action="/clients/unblock">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="name" value="<?= e((string)$c['name']) ?>">
                                <button type="submit" class="gray">Разблокировать</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/clients/block">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="name" value="<?= e((string)$c['name']) ?>">
                                <button type="submit" class="gray">Блокировать</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/clients/delete" onsubmit="return confirm('Удалить клиента?')">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="name" value="<?= e((string)$c['name']) ?>">
                            <button type="submit" class="red">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
