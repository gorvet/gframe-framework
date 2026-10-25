<?php
$roles = (array)($data['data']['roles'] ?? []);
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="col-12">
    <div class="pagetitle"><h1>Añadir usuario</h1></div>
    <div class="card"><div class="card-body">
        <form id="user-create-form" class="needs-validation" data-save-url="<?= $escape(site_url . 'ajax/admin/users/create') ?>" novalidate>
            <?= \GFrame\Security\CsrfToken::fields() ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="user-create-email" class="form-label">Correo electrónico</label>
                    <input id="user-create-email" name="email" class="form-control" type="email" autocomplete="off" maxlength="190" required>
                    <div class="invalid-feedback">Escribe un correo electrónico válido.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="user-create-role" class="form-label">Rol</label>
                    <select id="user-create-role" name="role_id" class="form-select" required>
                        <option value="">Selecciona un rol</option>
                        <?php foreach ($roles as $role): ?><option value="<?= (int)$role['role_id'] ?>"><?= $escape($role['name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Selecciona un rol.</div>
                </div>
                <div class="col-12"><p class="text-body-secondary mb-0">El usuario recibirá un enlace para definir su contraseña y verificar la cuenta. El enlace caduca en 24 horas.</p></div>
            </div>
            <div id="user-create-result" class="alert mt-4" role="status" hidden></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a class="btn btn-outline-secondary" href="<?= $escape(site_url . 'admin/users') ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit">Crear usuario y enviar enlace</button>
            </div>
        </form>
    </div></div>
</div>
