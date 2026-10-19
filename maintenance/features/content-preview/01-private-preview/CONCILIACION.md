# Etapa 01 — Vista previa privada

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md).

## Resultado observable

Abrir la plantilla pública con el texto privado y actualizarla cuando cambia el autoguardado confirmado o el contenido guardado.

## Dependencias

Autoguardado privado y guardado por versión existentes; se conservan sus contratos.

## Entra

Lectura de copia del usuario/tenant/recurso autorizados, fallback al contenido actual cuando no existe copia compatible, headers privados/noindex, polling de estado autorizado, botón propio del editor y recetas de plantilla pública. Sustituir preview nativo sin fabricar rutas de artículos.

## No entra

Barra administrativa (aplazada por el usuario), cambios en vivo sin persistir, compartir preview anónimo, publicar contenido, sitios instalados ni otros campos del formulario no soportados por el autoguardado.

## Aclaraciones

2026-10-19: el usuario acuerda sustituir la preview TinyMCE por la plantilla real y autoriza implementar; la actualización se produce tras autoguardado/guardado confirmado conforme a la propuesta aceptada.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** leer preview → copia privada del actor y tenant/recurso autorizados; una copia de base obsoleta no sustituye el contenido actual. No publica ni altera borradores. Evidencia sistema.
- **CA-02 — invariante acumulado:** falta identidad/sesión o contradice tenant → rechazo; preview/status privados/no-store y noindex; cada ruta autoriza de nuevo el registro. Evidencia sistema y estructural.
- **CA-03 — condición de salida:** cambia estado confirmado → preview abierta recarga una vez por versión nueva; oculta/destruida no inicia polling y fallo/403 no expone datos ni recarga a login en bucle. Evidencia conductual.
- **CA-04 — condición de salida:** campo con URL autorizada → botón propio abre preview del mismo origen; sin URL no ofrece un preview falso. Nativo TinyMCE retirado de defaults. Docs/assets/skills y regresión coherentes. Evidencia conductual y estructural.

## Riesgos y compatibilidad

La plantilla y campos de negocio pertenecen a la aplicación. El autoguardado actual cubre HTML de un campo, no todo el formulario. El servidor/proxy debe respetar no-store. No se comparten enlaces públicos secretos ni se confirma publicación al previsualizar.

## Preguntas abiertas

Ninguna para este alcance.
