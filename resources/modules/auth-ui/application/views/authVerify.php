<?php $result = (array)($data['result'] ?? []); ?>
<div class="card login-card auth-card">
    <div class="card-header text-center border-0 py-3"><h1 class="h4 mb-0">Verificar cuenta</h1></div>
    <div class="card-body p-4 text-center">
        <div class="alert <?= ($result['status'] ?? '') === 'success' ? 'alert-success' : 'alert-danger' ?>" role="alert"><?= htmlspecialchars((string)($result['message'] ?? 'No se pudo verificar la cuenta.'), ENT_QUOTES, 'UTF-8') ?></div>
        <a class="btn btn-primary" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a>
    </div>
</div>
