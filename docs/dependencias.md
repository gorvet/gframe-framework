# Política de dependencias

## Ubicación de los paquetes PHP

GFrame utiliza `packages/` como directorio de instalación de Composer. El autoload se carga desde `packages/autoload.php` y los ejecutables se generan en `packages/bin/`.

Composer mantiene su estructura estándar `proveedor/paquete` dentro de esa carpeta. Por ejemplo, el framework se instala en una aplicación como `packages/gorvet/gframe` y PHPMailer como `packages/phpmailer/phpmailer`.

`packages/` es generado, no se versiona y nunca forma parte del archivo distribuible de GFrame.

## Dependencias externas administradas por Composer

| Función | Paquete |
|---|---|
| Correo SMTP | `phpmailer/phpmailer` |
| Serialización de closures para `Async` | `opis/closure` `^4.3` |
| Server-Sent Events | `hhxsv5/php-sse` |
| Variables de entorno | `vlucas/phpdotenv` |

Estas librerías no deben copiarse dentro de `src` ni mantenerse manualmente en `core/vendors`.

## Componentes propios

- `Async`: componente asíncrono de GFrame que serializa closures mediante `Opis\Closure\serialize()` y las ejecuta con PHP CLI. Consulta [Async](async.md) para sus límites y compatibilidad con Opis 3: no es una cola persistente ni ofrece reintentos automáticos.
- `GFrame\Security\Encryption`: utilidad opcional de cifrado autenticado AES-256-GCM incluida en el framework. Su formato es propio y no migra automáticamente datos cifrados por implementaciones anteriores.
- `gf-select`: componente propio de interfaz extraído y documentado en [GF Select](gfselect.md).
- `password-utils`: utilidad propia extraída y documentada en [Password Utils](password-utils.md).
- Fuente de iconos [`gframe-icons`](gframe-icons.md): recurso propio extraído y documentado.

Las dependencias de negocio pertenecen al proyecto que las utiliza. PHPMailer, Opis Closure, PHP-SSE y PHP dotenv se instalan mediante Composer; no se mantienen copias manuales en el núcleo.

## Componentes con requisito de extensión PHP

Algunas capacidades del framework dependen además de extensiones del runtime y no de otro paquete Composer. Por ejemplo, el cliente HTTP saliente utiliza cURL. Consulta la guía de la capacidad y los requisitos generales antes de desplegarla.

## Dependencias del navegador

Bootstrap, jQuery, SweetAlert2, AOS, Venobox y otras bibliotecas de interfaz se distribuyen mediante el [catálogo de módulos](modulos-opcionales.md). Cada manifiesto registra sus dependencias y destinos públicos. Las bibliotecas externas conservan su autoría y licencia; las guías de cada una enlazan su fuente oficial.

El inventario de versiones y licencias de los archivos distribuidos está en [Dependencias frontend distribuidas](dependencias-frontend.md).

## Regla de actualización

Composer fija las versiones en `composer.lock`. Las actualizaciones se prueban en el framework y luego se incorporan de forma explícita a cada aplicación. En una aplicación se usa `composer install`; no se copian paquetes manualmente.


## Criterio para añadir una dependencia

Antes de incorporar un paquete al core, compruebe si la necesidad pertenece realmente al framework o a una aplicación. Una dependencia del paquete principal afecta instalación, superficie de seguridad, compatibilidad de PHP, licencias y actualizaciones de todos los consumidores.

Evalúe como mínimo:

1. mantenimiento y procedencia del paquete;
2. licencia compatible con la distribución prevista;
3. versiones de PHP y extensiones requeridas;
4. tamaño y dependencias transitivas;
5. estabilidad de su API;
6. si existe una solución pequeña y razonable dentro del core;
7. si la capacidad debería ser un módulo opcional en lugar de una dependencia obligatoria.

## Dependencias de módulos

Las bibliotecas frontend y capacidades opcionales deben declarar sus relaciones en el catálogo de módulos. El instalador resuelve esas dependencias; no replique manualmente el mismo archivo en varios módulos.

Un módulo funcional puede depender de otro módulo interno o de una distribución externa. Esa relación debe quedar documentada y probada para que una instalación limpia y una actualización produzcan la misma estructura.

## Actualizar paquetes PHP

No cambie versiones directamente dentro de `packages/`. Modifique la restricción correspondiente, deje que Composer resuelva el grafo y ejecute las pruebas con el lock resultante.

Revise especialmente:

- cambios de API;
- requisitos mínimos de PHP;
- avisos de seguridad;
- licencias;
- comportamiento de serialización o persistencia;
- binarios o scripts instalados.

Una actualización compatible del paquete no garantiza que la integración de GFrame siga siendo compatible: las pruebas del framework son la autoridad para esa combinación.

## Extensiones de PHP y entorno

Composer puede instalar paquetes, pero no habilita necesariamente extensiones del runtime o servicios del sistema. cURL, DOM, OpenSSL, PDO, Redis o PHP CLI deben comprobarse según las capacidades utilizadas por el proyecto.

Documente el fallo esperado cuando una extensión sea opcional y bloquee la instalación o arranque cuando sea obligatoria para el perfil elegido.

## Dependencias duplicadas

Evite cargar una segunda copia manual de una biblioteca ya administrada. En PHP puede romper resolución de clases o versiones; en frontend puede registrar plugins sobre otra instancia de jQuery o aplicar dos hojas de estilo incompatibles.

Use las rutas y versiones publicadas por el catálogo salvo que una aplicación tenga una integración conscientemente aislada.

## Auditoría antes de una release

Cuando cambie una dependencia:

- ejecute Composer con el lock nuevo;
- corra `composer check`;
- verifique instalación limpia;
- revise actualización de un proyecto existente;
- pruebe manualmente las bibliotecas visuales afectadas;
- actualice [Dependencias frontend distribuidas](dependencias-frontend.md) si cambia una copia vendorizada;
- revise avisos de licencia incluidos en la distribución.
