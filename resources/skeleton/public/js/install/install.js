/* Solo utiliza los activos del asistente antes de instalar la aplicación. */
(() => {
    'use strict';
    const form = document.querySelector('#installer-form');
    if (!form) return;
    const config = JSON.parse(document.querySelector('#installer-data').textContent);
    const profile = form.elements.profile;
    const driver = form.elements.database_driver;
    const steps = [...form.querySelectorAll('[data-step]')];
    const back = form.querySelector('#installer-back');
    const next = form.querySelector('#installer-next');
    const submit = form.querySelector('#installer-submit');
    const password = form.elements.password;
    const account = form.querySelector('#installer-account');
    let current = 0;
    let visible = [];
    form.noValidate = true;
    profile.value = config.values.profile || 'managed';
    if (config.posted) {
        [...form.elements].forEach(input => {
            if (!input.name || input.type === 'password' || input.name === 'csrf') return;
            if (input.name === 'modules[]') input.checked = (config.values.modules || []).includes(input.value);
            else if (input.type === 'checkbox') input.checked = Boolean(config.values[input.name]);
            else if (Object.hasOwn(config.values, input.name)) input.value = config.values[input.name];
        });
    }
    const controls = section => [...section.querySelectorAll('input,select')];
    form.querySelectorAll('.installer-field').forEach(row => {
        const input = row.querySelector('input,select');
        const help = row.querySelector('p');
        if (input && help) { help.id ||= `${input.id}-help`; input.setAttribute('aria-describedby', help.id); }
    });
    const summary = () => {
        const rows = [
            ['Aplicación', form.elements.app_name.value],
            ['Tipo de proyecto', profile.selectedOptions[0].textContent],
        ];
        const option = profile.selectedOptions[0];
        if (option.dataset.database === '1') rows.push(['Base de datos', driver.value === 'sqlite' ? 'SQLite · storage/database.sqlite' : `MySQL · ${form.elements.database_name.value} · ${form.elements.database_host.value}`]);
        if (option.dataset.auth === '1') rows.push(['Superadministrador', form.elements.email.value]);
        if (option.dataset.tenancy === '1') rows.push(['Ámbito', 'Multitenant']);
        const extras = [...form.querySelectorAll('[name="modules[]"]:checked')].filter(input => !input.disabled).map(input => input.closest('label').querySelector('span').firstChild.textContent);
        if (extras.length) rows.push(['Módulos adicionales', extras.join(', ')]);
        const mount = form.querySelector('#installer-summary');
        mount.replaceChildren();
        rows.forEach(([label, value]) => {
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value;
            mount.append(term, description);
        });
    };
    const render = () => {
        steps.forEach(step => { step.hidden = step !== visible[current]; });
        back.hidden = current === 0;
        next.hidden = current === visible.length - 1;
        submit.hidden = !next.hidden;
        next.textContent = visible[current].dataset.section === 'database' ? 'Comprobar conexión' : 'Continuar';
        if (submit.hidden === false) summary();
    };
    const configureModules = () => {
        const labels = [...form.querySelectorAll('[data-module-profiles]')];
        const available = label => label.dataset.moduleProfiles.split(' ').includes(profile.value);
        const automatic = new Set();
        labels.filter(label => available(label) && label.querySelector('input').checked).forEach(label => {
            (config.dependencies[label.querySelector('input').value] || []).forEach(name => automatic.add(name));
        });
        labels.forEach(label => {
            const input = label.querySelector('input');
            label.hidden = !available(label) || automatic.has(input.value);
            input.disabled = label.hidden;
            if (!available(label)) input.checked = false;
        });
        form.querySelectorAll('.installer-module-group').forEach(group => {
            group.hidden = ![...group.querySelectorAll('[data-module-profiles]')].some(label => !label.hidden);
        });
    };
    const configure = () => {
        const option = profile.selectedOptions[0];
        form.querySelector('#profile-description').textContent = config.descriptions[profile.value] || '';
        visible = steps.filter(step => !step.dataset.section || option.dataset[step.dataset.section === 'publication' ? 'public' : step.dataset.section] === '1');
        steps.forEach(step => {
            controls(step).forEach(input => { input.disabled = !visible.includes(step); });
        });
        form.querySelectorAll('[data-mysql]').forEach(element => {
            element.hidden = driver.value !== 'mysql';
            (element.matches('input') ? [element] : controls(element)).forEach(input => { input.disabled = option.dataset.database !== '1' || driver.value !== 'mysql'; });
        });
        form.querySelector('[data-sqlite]').hidden = driver.value !== 'sqlite';
        ['database_host', 'database_name', 'database_user'].forEach(name => { form.elements[name].required = option.dataset.database === '1' && driver.value === 'mysql'; });
        account.hidden = option.dataset.auth !== '1';
        controls(account).forEach(input => { input.disabled = account.hidden; input.required = !account.hidden; });
        form.querySelector('#installer-show-password').disabled = account.hidden;
        configureModules();
        current = Math.min(current, visible.length - 1);
        render();
    };
    const valid = step => {
        password.setCustomValidity(!password.disabled && password.value && !passwordValidate(password.value, form.elements.email.value) ? 'La contraseña debe tener entre 8 y 72 bytes.' : '');
        const invalid = controls(step).find(input => !input.disabled && !input.checkValidity());
        if (!invalid) return true;
        invalid.reportValidity();
        return false;
    };
    const showDatabaseError = async (message, code) => {
        await Swal.fire({
            icon: 'error',
            title: code === 'database_not_empty' ? 'La base de datos ya está en uso' : 'No se pudo conectar',
            text: message,
            confirmButtonText: 'Corregir datos',
        });
        driver.focus();
    };
    form.querySelector('#installer-show-password').addEventListener('click', event => {
        const shown = password.type === 'password';
        password.type = shown ? 'text' : 'password';
        event.currentTarget.textContent = shown ? 'Ocultar' : 'Mostrar';
        event.currentTarget.setAttribute('aria-pressed', String(shown));
    });
    const updatePassword = () => { password.setCustomValidity(''); updateMeterPassword(password.value, form.elements.email.value); };
    password.addEventListener('input', updatePassword);
    form.elements.email.addEventListener('input', updatePassword);
    next.addEventListener('click', async () => {
        if (!valid(visible[current])) return;
        if (visible[current].dataset.section === 'database') {
            const status = form.querySelector('#database-status');
            status.textContent = 'Comprobando la conexión…';
            next.disabled = true;
            back.disabled = true;
            try {
                const data = new FormData(form);
                data.set('installer_action', 'check_database');
                data.delete('password');
                data.delete('email');
                const response = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
                const result = await response.json();
                status.textContent = result.message;
                if (result.status !== 'success') { await showDatabaseError(result.message, result.code); return; }
            } catch {
                status.textContent = 'No se pudo comprobar la conexión. Vuelve a intentarlo.';
                await showDatabaseError(status.textContent, 'connection_failed');
                return;
            } finally {
                next.disabled = false;
                back.disabled = false;
            }
        }
        current++;
        render();
        const focusTarget = visible[current].querySelector('input:not(:disabled),select:not(:disabled)') || visible[current].querySelector('h1,h2');
        if (focusTarget) { if (/^H[12]$/.test(focusTarget.tagName)) focusTarget.tabIndex = -1; focusTarget.focus(); }
    });
    back.addEventListener('click', () => { current--; render(); });
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (current < visible.length - 1) { next.click(); return; }
        for (let index = 0; index < visible.length - 1; index++) {
            current = index;
            render();
            if (!valid(visible[index])) return;
        }
        current = visible.length - 1;
        render();
        submit.disabled = true;
        submit.textContent = 'Instalando…';
        form.submit();
    });
    profile.addEventListener('change', configure);
    driver.addEventListener('change', configure);
    form.querySelectorAll('[name="modules[]"]').forEach(input => input.addEventListener('change', configureModules));
    configure();
})();
