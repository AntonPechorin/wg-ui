<h1>Dashboard</h1>
<div class="grid">
    <div class="card"><strong>Interface</strong><br><?= htmlspecialchars((string)$wg['interface'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="card"><strong>Port</strong><br><?= htmlspecialchars((string)$wg['port'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="card"><strong>Endpoint</strong><br><?= htmlspecialchars((string)$wg['endpoint'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="card"><strong>Service</strong><br><?= $wg['service_active'] ? 'active' : 'inactive' ?></div>
    <div class="card"><strong>Clients</strong><br><?= (int)$client_count ?></div>
    <div class="card"><strong>Active / Blocked</strong><br><?= (int)$active_count ?> / <?= (int)$blocked_count ?></div>
</div>

<div class="card">
    <h3>System actions</h3>
    <form method="post" action="/actions" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" name="action" value="restart-wg">Restart WireGuard</button>
        <button type="submit" name="action" value="reboot-server">Reboot Server</button>
    </form>
</div>
