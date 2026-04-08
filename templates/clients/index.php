<h1>Clients</h1>
<div class="card">
    <form method="post" action="/clients/create" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="name" placeholder="client name" required>
        <button type="submit">Create client</button>
    </form>
</div>

<div class="card">
<table>
    <thead>
    <tr>
        <th>Name</th>
        <th>Address</th>
        <th>Status</th>
        <th>Created</th>
        <th>Handshake</th>
        <th>RX</th>
        <th>TX</th>
        <th>Endpoint</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($clients as $client): ?>
        <tr>
            <td><?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($client['address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($client['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($client['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$client['runtime']['latest_handshake'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$client['runtime']['rx'] ?></td>
            <td><?= (int)$client['runtime']['tx'] ?></td>
            <td><?= htmlspecialchars((string)$client['runtime']['endpoint'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <form method="post" action="/clients/action" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="client" value="<?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="action" value="show">Show</button>
                    <button type="submit" name="action" value="regen">Regenerate</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
