# Desarrollo con una copia local del framework

Para desarrollar y probar cambios del framework sin publicar una versión, una aplicación puede enlazar una copia local desde el disco:

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

## Directorio de dependencias

Los proyectos GFrame configuran Composer para instalar los paquetes en `packages/`:

```json
{
    "config": {
        "vendor-dir": "packages",
        "bin-dir": "packages/bin"
    }
}
```

El arranque de la aplicación carga `packages/autoload.php`. No debe existir otra copia manual del framework ni de sus dependencias.


## Flujo de trabajo recomendado

Una copia local sirve para desarrollar el framework contra una aplicación real sin publicar cada cambio. Mantenga repositorio y proyecto como carpetas hermanas o ajuste la ruta relativa de `repositories.path`.

Después de cambiar código PHP del framework, Composer suele ver inmediatamente los archivos cuando el repositorio está enlazado mediante symlink. Si cambia metadatos de Composer, dependencias o la referencia de versión, ejecute de nuevo `composer update gorvet/gframe` para regenerar el lock y el autoload según corresponda.

Los archivos que GFrame publica dentro del proyecto —CSS, JS, vistas base, configuraciones administradas o migraciones— no cambian solo porque el paquete esté enlazado. Para probar esos cambios ejecute primero:

```bash
composer gframe:update -- --dry-run
composer gframe:update
```

Así se diferencia claramente el código runtime cargado desde `packages/gorvet/gframe` de los archivos materializados en la aplicación.

## Evitar contaminar producción

No versiona una ruta `path` local como configuración definitiva de un proyecto que se despliega. Antes de publicar, vuelva a una restricción de versión normal, actualice el lock y compruebe que la aplicación funciona sin el repositorio vecino.

Una aplicación en producción debe poder reconstruirse con su `composer.json` y `composer.lock`; nunca debe depender de una carpeta fuera del proyecto o de un symlink creado en la estación de desarrollo.

## Symlink y copia

`"symlink": true` facilita que los cambios del framework se reflejen al instante. En entornos donde Composer no pueda crear enlaces, puede utilizar una copia, pero entonces los cambios locales no aparecerán hasta que Composer vuelva a sincronizar el paquete.

Compruebe qué modalidad quedó activa antes de diagnosticar un cambio que «no se ve».

## Pruebas durante el desarrollo

Valide dos niveles:

1. en `gframe-framework`, ejecute `composer check` o las pruebas específicas del componente;
2. en la aplicación consumidora, ejecute su recorrido real después de `composer gframe:update -- --dry-run` cuando existan archivos publicados o migraciones.

Una prueba verde del framework no sustituye una integración real cuando el cambio depende del esqueleto, configuración del proyecto, servidor web o base de datos.

## Volver a una versión publicada

Antes de cerrar una prueba local:

1. retire o desactive el repositorio `path` si no debe quedar en el proyecto;
2. restaure una restricción publicada de `gorvet/gframe`;
3. ejecute `composer update gorvet/gframe`;
4. revise `composer.lock`;
5. ejecute `composer gframe:update -- --dry-run`;
6. pruebe la aplicación con la versión resuelta.

Esto evita que un cambio funcione únicamente porque el proyecto seguía enlazado a código no publicado.
