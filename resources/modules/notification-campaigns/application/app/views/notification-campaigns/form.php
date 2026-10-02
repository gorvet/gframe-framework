<?php
$campaign = (array)($data['data']['campaign'] ?? []);
$criteria = (array)($data['data']['criteria'] ?? []);
$editing = !empty($campaign['campaign_id']);
$channels = json_decode((string)($campaign['channels_json'] ?? '["inbox"]'), true) ?: [];
$scope = (string)($criteria['scope'] ?? 'active');
$selectedIDs = array_map('intval', (array)($criteria['user_ids'] ?? []));
$base = rtrim((string)site_url, '/');
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="col-12" data-campaigns-root>
    <div class="pagetitle"><h1><?= $editing ? 'Editar campaña' : 'Nueva campaña' ?></h1></div>
    <div class="card"><div class="card-body">
        <form class="needs-validation" data-campaign-form data-editing="<?= $editing ? '1' : '0' ?>" data-save-url="<?= $escape($base . '/ajax/admin/notifications/campaigns/' . ($editing ? 'update' : 'create')) ?>" data-return-url="<?= $escape($base . '/admin/notifications/campaigns') ?>" novalidate>
            <?php if ($editing): ?><input type="hidden" name="campaign_id" value="<?= (int)$campaign['campaign_id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="campaign-title">Título</label><input class="form-control" id="campaign-title" name="title" maxlength="160" value="<?= $escape($campaign['title'] ?? '') ?>" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="campaign-audience">Destinatarios</label><select class="form-select" id="campaign-audience" name="audience"><?php foreach (['active' => 'Todos los usuarios activos', 'administrators' => 'Solo administradores activos', 'manual' => 'Selección manual'] as $value => $label): ?><option value="<?= $value ?>"<?= $scope === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="col-12" data-manual-audience<?= $scope !== 'manual' ? ' hidden' : '' ?>><label class="form-label" for="campaign-users">Seleccionar usuarios</label><select class="form-select" id="campaign-users" name="user_ids[]" multiple<?= $scope !== 'manual' ? ' disabled' : ' required' ?>><?php foreach ((array)($data['data']['users'] ?? []) as $user): ?><option value="<?= (int)$user['user_id'] ?>"<?= in_array((int)$user['user_id'], $selectedIDs, true) ? ' selected' : '' ?>><?= $escape($user['email']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label" for="campaign-message">Mensaje</label><textarea class="form-control" id="campaign-message" name="message" rows="5" maxlength="10000" required><?= $escape($campaign['message'] ?? '') ?></textarea></div>
                <?php include \GFrame\Modules\ModuleRuntime::file('views', 'notification-campaigns/_placeholders.php', 'notification-campaigns'); ?>
                <div class="col-12"><label class="form-label" for="campaign-action-url">Enlace de acción (opcional)</label><input class="form-control" id="campaign-action-url" name="action_url" maxlength="255" value="<?= $escape($campaign['action_url'] ?? '') ?>"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="campaign-importance">Importancia</label><select class="form-select" id="campaign-importance" name="importance"><?php foreach (['info' => 'Informativa', 'warning' => 'Aviso', 'danger' => 'Importante'] as $value => $label): ?><option value="<?= $value ?>"<?= ($campaign['importance'] ?? 'info') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-6"><label class="form-label" for="campaign-expiry">Caducidad (días)</label><input class="form-control" id="campaign-expiry" type="number" name="expires_after_days" min="0" max="3650" value="<?= (int)($campaign['expires_after_days'] ?? 0) ?>"><div class="form-text">0: sin caducidad.</div></div>
            </div>
            <div class="border-top mt-4 pt-4">
                <h2 class="fs-6 fw-semibold mb-3">Entrega</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label" for="campaign-scheduled">Fecha programada (opcional)</label><input class="form-control" id="campaign-scheduled" name="scheduled_at" type="text" autocomplete="off" data-scheduled-utc="<?= $escape($campaign['scheduled_at'] ?? '') ?>"></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="campaign-recurrence">Frecuencia</label><select class="form-select" id="campaign-recurrence" name="recurrence"><?php foreach (['once' => 'Una vez', 'daily' => 'Diaria', 'weekly' => 'Semanal'] as $value => $label): ?><option value="<?= $value ?>"<?= ($campaign['recurrence'] ?? 'once') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><span class="form-label d-block">Canales</span><div class="d-flex flex-wrap gap-4"><div class="form-check"><input class="form-check-input" id="campaign-inbox" name="channels[]" value="inbox" type="checkbox"<?= in_array('inbox', $channels, true) ? ' checked' : '' ?>><label class="form-check-label" for="campaign-inbox">Notificaciones</label></div><div class="form-check"><input class="form-check-input" id="campaign-email" name="channels[]" value="email" type="checkbox"<?= in_array('email', $channels, true) ? ' checked' : '' ?>><label class="form-check-label" for="campaign-email">Correo</label></div></div></div>
                </div>
            </div>
            <input type="hidden" name="user_timezone" value="UTC">
            <input type="hidden" name="template_id" value="<?= $escape($campaign['template_id'] ?? 'notification') ?>">
            <div class="d-flex justify-content-end flex-wrap gap-2 mt-4"><button class="btn btn-outline-primary" type="button" data-preview-audience data-url="<?= $escape($base . '/ajax/admin/notifications/campaigns/preview') ?>">Ver destinatarios</button><button class="btn btn-outline-primary" type="button" data-test-campaign data-url="<?= $escape($base . '/ajax/admin/notifications/campaigns/test') ?>">Enviar prueba a mi cuenta</button></div>
            <div class="d-flex justify-content-end flex-wrap gap-2 border-top mt-3 pt-3"><a class="btn btn-secondary" href="<?= $escape($base . '/admin/notifications/campaigns') ?>">Cancelar</a><button class="btn btn-primary" type="submit" data-save-label data-label-send="Enviar ahora" data-label-schedule="Programar campaña" data-label-edit="Guardar cambios"><?= $editing ? 'Guardar cambios' : 'Enviar ahora' ?></button></div>
        </form>
    </div></div>
    <div class="modal fade" id="campaign-audience-preview" tabindex="-1" aria-labelledby="campaign-preview-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="campaign-preview-title">Destinatarios</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" data-audience-preview></div><div class="modal-footer justify-content-end"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
</div>
