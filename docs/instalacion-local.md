# Instalación local durante la extracción

Hasta que el paquete se publique en Packagist, una aplicación puede enlazarlo desde el disco:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../gframe-framework",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "gorvet/gframe": "dev-main"
    }
}
```

Después se ejecuta:

```bash
composer update gorvet/gframe
```

Esta modalidad permite probar cambios localmente. En producción, cada aplicación debe instalar una versión etiquetada y desplegar su `composer.lock`.
