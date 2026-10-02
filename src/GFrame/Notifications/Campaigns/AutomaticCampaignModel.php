<?php

namespace GFrame\Notifications\Campaigns;

class AutomaticCampaignModel extends \ORM
{
    protected $table = 'notification_campaign_rules';
    protected $primaryKey = 'rule_id';
    protected $fillable = ['rule_id', 'scope_id', 'rule_key', 'is_active', 'title', 'message', 'cooldown_days', 'updated_at'];

    public const RULES = [
        'account_suspended' => ['name' => 'Cuenta suspendida', 'description' => 'Envía un correo al suspender una cuenta desde Gestión de usuarios.', 'title' => 'Tu cuenta ha sido suspendida', 'message' => 'Hola {{user_name}}, tu cuenta ha sido suspendida. Contacta con la administración si necesitas más información.'],
        'account_blocked' => ['name' => 'Cuenta bloqueada', 'description' => 'Aviso por correo cuando la aplicación registra un bloqueo de cuenta.', 'title' => 'Tu cuenta ha sido bloqueada', 'message' => 'Hola {{user_name}}, tu cuenta ha sido bloqueada. {{reason}}'],
        'account_verification' => ['name' => 'Recordatorio de verificación', 'description' => 'Envía un enlace individual de verificación a cuentas pendientes.', 'title' => 'Verifica tu cuenta', 'message' => 'Hola {{user_name}}, verifica tu cuenta: {{verification_url}}'],
        'account_deletion_reminder' => ['name' => 'Recordatorio de cuenta desactivada', 'description' => 'Aviso previo a eliminar una cuenta desactivada por su titular.', 'title' => 'Recordatorio sobre tu cuenta desactivada', 'message' => 'Hola {{user_name}}, tu cuenta desactivada tiene prevista su eliminación el {{deletion_date}} UTC.'],
    ];

    public function definitions(): array
    {
        $definitions = [];
        foreach (static::RULES as $key => $definition) {
            $definitions[$key] = $definition + ['is_active' => $key === 'account_deletion_reminder' ? 1 : 0, 'cooldown_days' => 7, 'periodic' => $key !== 'account_deletion_reminder'];
        }
        return $definitions;
    }

    public function definition(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    public function eligible(string $key, array $user, array $context = [], ?int $tenantID = null): bool
    {
        $statuses = ['account_suspended' => 'suspended', 'account_blocked' => 'blocked', 'account_verification' => 'unverify', 'account_deletion_reminder' => 'disabled'];
        return isset($statuses[$key]) && ($user['status'] ?? null) === $statuses[$key];
    }

    public function variables(string $key, array $user, array $context = [], ?int $tenantID = null): array
    {
        return [];
    }

    public function rules(int $scopeID = 0): array
    {
        $saved = [];
        foreach ($this->reset()->where('scope_id', '=', $scopeID)->get() as $row) $saved[$row['rule_key']] = $row;
        $result = [];
        foreach ($this->definitions() as $key => $defaults) {
            $result[] = array_replace($defaults, ['rule_key' => $key], $saved[$key] ?? []);
        }
        return $result;
    }

    public function saveRule(int $scopeID, string $key, bool $active, string $title, string $message, int $cooldownDays = 7): void
    {
        if ($this->definition($key) === null || $cooldownDays < 1 || $cooldownDays > 3650) throw new \InvalidArgumentException('Invalid campaign rule');
        $row = $this->reset()->where('scope_id', '=', $scopeID)->where('rule_key', '=', $key)->limit(1)->get();
        $changes = ['is_active' => (int)$active, 'title' => $title, 'message' => $message, 'cooldown_days' => $cooldownDays, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($row === []) (new static($changes + ['scope_id' => $scopeID, 'rule_key' => $key]))->insert();
        else $this->reset()->where('rule_id', '=', (int)$row[0]['rule_id'])->update($changes);
    }

    public function eligibleUsers(string $key, int $scopeID = 0): array
    {
        $statuses = ['account_suspended' => 'suspended', 'account_blocked' => 'blocked', 'account_verification' => 'unverify', 'account_deletion_reminder' => 'disabled'];
        if (!isset($statuses[$key])) return [];
        $query = self::queryTable('users')->select('user_id', 'email')->where('status', '=', $statuses[$key]);
        if ($scopeID > 0) $query->whereRaw('EXISTS (SELECT 1 FROM tenant_memberships WHERE tenant_memberships.user_id = users.user_id AND tenant_memberships.tenant_id = ?)', [$scopeID]);
        return $query->orderBy('email', 'ASC')->get();
    }
}
