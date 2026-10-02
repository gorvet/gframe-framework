<?php
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)site_url, '/');
$rules = (array)($data['data']['rules'] ?? []);
?>
<div class="col-12" data-automatic-campaigns>
    <div class="pagetitle"><div class="d-inline-block"><h1>Campañas automáticas</h1><span class="d-block">Avisos de cuenta por correo</span></div></div>
    <div class="card"><div class="card-body"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th scope="col">Campaña</th><th scope="col">Estado</th><th scope="col">No repetir durante</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $rule): $key = $rule['rule_key']; ?>
                <tr><td><strong><?= $escape($rule['name']) ?></strong><small class="d-block text-body-secondary"><?= $escape($rule['description']) ?></small></td>
                    <td><span data-rule-state="<?= $escape($key) ?>"><?= !empty($rule['is_active']) ? 'Activa' : 'Inactiva' ?></span></td>
                    <td><span data-rule-cooldown="<?= $escape($key) ?>"><?= (int)($rule['cooldown_days'] ?? 7) ?></span> días</td>
                    <td class="text-end text-nowrap"><button type="button" class="btn btn-outline-secondary btn-list-actions btn-sm" data-bs-toggle="modal" data-bs-target="#edit-<?= $escape($key) ?>">Editar</button> <button type="button" class="btn btn-outline-primary btn-sm" data-automatic-send="<?= $escape($key) ?>" data-send-url="<?= $escape($base . '/ajax/admin/notifications/campaigns/automatic/send') ?>">Enviar ahora</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php foreach ($rules as $rule): $key = $rule['rule_key']; ?>
    <div class="modal fade" id="edit-<?= $escape($key) ?>" tabindex="-1" aria-labelledby="edit-title-<?= $escape($key) ?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form class="needs-validation" novalidate data-automatic-form data-save-url="<?= $escape($base . '/ajax/admin/notifications/campaigns/automatic/save') ?>">
            <div class="modal-header"><h2 class="modal-title fs-5" id="edit-title-<?= $escape($key) ?>"><?= $escape($rule['name']) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" name="rule_key" value="<?= $escape($key) ?>">
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="<?= $escape($key) ?>-active" name="is_active" value="1"<?= !empty($rule['is_active']) ? ' checked' : '' ?>><label class="form-check-label" for="<?= $escape($key) ?>-active">Activar envíos automáticos</label></div>
                <p class="text-body-secondary"><?= $escape($rule['description']) ?></p>
                <div class="mb-3"><label class="form-label" for="<?= $escape($key) ?>-title">Título</label><input class="form-control" id="<?= $escape($key) ?>-title" name="title" maxlength="160" value="<?= $escape($rule['title']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="<?= $escape($key) ?>-message">Mensaje</label><textarea class="form-control" id="<?= $escape($key) ?>-message" name="message" rows="3" maxlength="10000" required><?= $escape($rule['message']) ?></textarea></div>
                <div><label class="form-label" for="<?= $escape($key) ?>-days">No repetir durante (días)</label><input class="form-control" id="<?= $escape($key) ?>-days" name="cooldown_days" type="number" min="1" max="3650" value="<?= (int)($rule['cooldown_days'] ?? 7) ?>" required><div class="form-text">Se aplica tanto al envío automático como al manual.</div></div>
            </div>
            <div class="modal-footer justify-content-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div></div></div>
    <?php endforeach; ?>
</div>
