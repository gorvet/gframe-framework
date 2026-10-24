# Coordinación entre asistentes (Claude, Codex, …)

Canal compartido para que varios asistentes trabajen en el mismo repositorio sin pisarse: reservas de archivos con caducidad y mensajes. Reduce los pisotones; **todavía no es un chat en vivo** (es *pull*: cada agente lee cuando ejecuta). El panel en tiempo (casi) real puede venir después sobre este mismo canal.

## Principios

- **Una rama y un worktree por agente.** Dos ramas sobre la misma carpeta no aíslan: al cambiar de rama, cambia para todos. Usa `git worktree add` para que cada agente tenga su carpeta.
- **El canal vive FUERA de los worktrees**, en una ruta fija, para que todos lean al instante sin commits ni merges.
- **Las reservas caducan** (TTL). Una sesión interrumpida no deja archivos bloqueados para siempre.

## El canal

Almacén JSON en `$GFRAME_AGENT_CHANNEL`, o `<home>/.gframe-agents/channel.json` por defecto. Registra, por reserva: `agente`, `tarea`, `archivos`, `claimed_at`, `expires_at`, `estado`; y una lista de `mensajes` (`at`, `agente`, `texto`).

CLI: `bin/agent-coord.php`.

```bash
# Reservar antes de editar (TTL por defecto 2 h)
php bin/agent-coord.php claim --agent=claude --task="ORM" --files=src/database/ORM.php,docs/orm.md

# ¿Puedo tocar este archivo? (0 = libre o mío; 2 = reservado por otro)
php bin/agent-coord.php check --file=src/database/ORM.php --agent=claude

# Liberar al terminar (sin --files libera todo lo del agente)
php bin/agent-coord.php release --agent=claude --files=src/database/ORM.php

# Mensajes
php bin/agent-coord.php say  --agent=claude --text="voy con el router"
php bin/agent-coord.php read --since=2026-10-24T00:00:00Z

# Ver reservas vivas + últimos mensajes
php bin/agent-coord.php status
```

`claim` rechaza (código 2) los archivos ya reservados por otro agente. Las reservas caducadas se podan al leer.

## Protocolo por tarea

1. Al empezar: `status` (mira reservas y mensajes del otro).
2. Antes de editar un conjunto de archivos: `claim` con esos archivos.
3. Si `check`/`claim` indican conflicto: no edites; `say` para coordinar o espera a que el otro libere/caduque.
4. Al terminar o cambiar de foco: `release`.

## Enforcement opcional en Claude Code (hook)

`bin/agent-claim-hook.php` consulta el canal antes de cada `Edit`/`Write` y bloquea (código 2) si otro agente tiene el archivo reservado. Queda inerte hasta registrarlo en `.claude/settings.json`:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          { "type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/bin/agent-claim-hook.php\"" }
        ]
      }
    ]
  }
}
```

Define `GFRAME_AGENT` (p. ej. `claude`) en el entorno para que el hook sepa quién eres; por defecto `claude`. Codex necesita su propio hook equivalente en su configuración para que la protección sea mutua.

## Panel en vivo (opcional)

Una página web local para ver en tiempo (casi) real los mensajes y las reservas, y para que la persona escriba en el canal.

```bash
php -S 127.0.0.1:8787 bin/agent-coord-server.php
# abre http://127.0.0.1:8787
```

- `bin/agent-coord-server.php` es un servidor local que lee/escribe el **mismo** canal (delegando en `agent-coord.php`); respeta `GFRAME_AGENT_CHANNEL`.
- `bin/agent-coord-panel.html` es la página: se refresca sola cada 2 s, colorea por agente y trae una caja para escribir (por defecto como `juank`).
- Déjalo abierto mientras trabajáis. Sigue siendo *pull*: los agentes ven los mensajes cuando consultan el canal o cuando un hook los incorpora a su contexto. Un hook que solo ejecuta `check` comprueba reservas; no entrega por sí mismo los mensajes al modelo.
- Si falla la lectura, el panel muestra el error; si falla un envío, conserva el borrador. El historial sigue actualizándose cuando alcanza el límite de 200 mensajes.

## Verificación de integración

Base de Claude: `fe52baa`, cuatro commits de coordinación revisados por Codex. Especialista aplicado: `skills/gframe-framework-maintenance/SKILL.md`; este panel es una herramienta local independiente, no una vista del runtime MVC.

Se verifican conflictos, TTL, rutas absolutas/relativas y archivos nuevos en directorios inexistentes, escritura concurrente con un solo ganador, fallos de bloqueo, canal corrupto/esquema inválido y propagación HTTP de los errores. Las pruebas JS cubren rotación de 200 mensajes, conservación del borrador y errores de lectura. Los hooks personales de cada asistente se configuran localmente, fuera del paquete; no se distribuye ni activa confianza de Codex desde esta herramienta.

## Límites

- Es *pull*: nadie se "despierta" solo al recibir un mensaje; se ve al ejecutar la CLI o el hook (MCP por sí solo tampoco empuja).
- La protección es cooperativa: si un agente no reserva ni tiene el hook, puede editar igual. Por eso la disciplina de worktree por agente es la base.
