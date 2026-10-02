# Cambio de nombre del paquete Composer

El nombre del paquete es `gorvet/gframe`. La marca GFrame, los namespaces PHP `GFrame\`, las rutas y los nombres de módulos no cambian.

El proveedor `gframe` de Packagist está reservado por otro desarrollador. La etiqueta histórica `v1.0.0` de GitHub conserva su identidad original; no se reescribe una versión publicada.

## Proyectos existentes

En `composer.json`, sustituye el requisito `gframe/framework` por `gorvet/gframe` y conserva una restricción compatible con la nueva versión publicada. Cambia también el script:

```json
"gframe:update": "@php packages/gorvet/gframe/bin/gframe-update"
```

Después ejecuta `composer update` y `composer gframe:update -- --dry-run`. Revisa los archivos administrados antes de ejecutar `composer gframe:update`. No copies ni edites el paquete instalado a mano.

Si usas un repositorio local `path` o uno Git, ajusta también cualquier nombre de paquete declarado en `options.versions`. Conserva tus personalizaciones en `app` y tus valores de entorno en `.env`.
