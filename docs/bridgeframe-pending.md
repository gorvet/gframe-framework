# Cambios futuros recomendados para BridgeFrame

Este documento se basa en la revisión del plugin ubicado en `C:\xampp\htdocs\gorvet\wp-content\plugins\bridgeframe`. El plugin no fue modificado durante la auditoría de GFrame.

## Contrato que consume GFrame

GFrame define el contrato `2.0` bajo `bridgeframe/v2` y utiliza los endpoints `GET /html`, `GET /list`, `GET /terms`, `GET /menu` y `GET /schema`. Envía el token exclusivamente mediante `Authorization: Bearer` y la cabecera `X-BridgeFrame-Contract: 2.0`.

## Cambios prioritarios

1. Crear el namespace `bridgeframe/v2` y mover la verificación del token a `permission_callback`. Las rutas v1 actuales usan `__return_true` y cada callback valida después de entrar.
2. Retirar el token por query string. Puede quedar temporalmente durante una migración, pero las URL se registran con facilidad en proxies, analítica e historiales.
3. Separar permisos de lectura pública y lectura privada. Un token de contenido público no debe obtener contenido privado solo por enviar `private=true`.
4. Permitir varios consumidores con credenciales almacenadas mediante hash, revocables, rotatorias, con fecha de expiración y scopes como `content.read`, `private.read`, `comments.write` y `comments.moderate`.
5. Homogeneizar todas las respuestas con `status`, `code`, `data` y `meta`. Actualmente `/html` añade `status`, pero `/terms` y otros endpoints devuelven estructuras diferentes; los errores devuelven solamente `error`.
6. Incluir `meta.contract_version` y `meta.request_id` en todas las respuestas. GFrame rechaza respuestas sin el contrato `2.0`.
7. Mantener límites estrictos, rate limiting y auditoría por consumidor, evitando registrar tokens completos.

## Contrato recomendado

```json
{
  "status": "success",
  "code": "content_loaded",
  "data": {},
  "meta": {
    "contract_version": "2.0",
    "request_id": "..."
  }
}
```

Los errores deben conservar su código HTTP y usar códigos estables como `invalid_token`, `insufficient_scope`, `content_not_found`, `invalid_content_type`, `invalid_taxonomy` y `rate_limited`.

## Migración del plugin

La implementación v1 puede conservarse temporalmente para consumidores anteriores, pero el módulo de GFrame no la utiliza ni incorpora una capa heredada. La integración quedará operativa cuando BridgeFrame implemente `bridgeframe/v2` con este contrato.
