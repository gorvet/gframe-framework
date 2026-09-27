# Arquitectura de GFrame

## Objetivo

GFrame debe funcionar como un framework instalable y versionado. El núcleo no puede depender de las reglas de negocio de una aplicación concreta.

## Capas

### Framework

- Enrutamiento y resolución de controladores.
- Middleware y permisos genéricos.
- ORM, conexiones y dialectos de base de datos.
- Renderizado, vistas y metadatos.
- Errores, SEO, tareas asíncronas, cron y heartbeat.
- Servicios HTTP y de correo.
- Utilidades sin conocimiento del dominio.

### Aplicación

- Controladores, modelos y servicios del negocio.
- Rutas y permisos concretos.
- Vistas, plantillas, identidad visual y textos.
- Configuración, migraciones y datos propios del negocio.
- Integraciones exclusivas del proyecto.

### Módulos opcionales

Los módulos reutilizables que no sean necesarios en todas las aplicaciones se seleccionan desde el catálogo durante la instalación. Entre ellos están la biblioteca multimedia, el editor enriquecido, las notificaciones y WordPress headless. Los algoritmos propios de un dominio, como `TextClassifier` en Bebots, permanecen en su aplicación.

Los módulos fundamentales pueden incluir esquemas de instalación portables. El módulo de autenticación proporciona las tablas estándar de usuarios, roles y permisos; las aplicaciones no añaden datos de perfil o negocio a esa tabla base.

## Compatibilidad inicial

La serie `0.x` conserva las clases globales del core actual. Composer genera el mapa de clases y `GFrame\Foundation\Bootstrap` inicia la aplicación. Los namespaces se incorporarán gradualmente, con periodos de compatibilidad y avisos de obsolescencia.

## Configuración

El framework admite dos modos de permisos:

- Global, cuando la aplicación no define tenancy.
- Por tenant, cuando define conjuntamente el identificador y la tabla correspondiente.

Las reglas particulares de áreas, estados editoriales o visibilidad pertenecen a la aplicación.

## Recursos públicos

Los recursos comunes y opcionales se registran en `resources/modules`. Cada manifiesto declara sus dependencias y los destinos bajo `public/vendors/external`, `public/vendors/internal`, `public/js/core` o el directorio público correspondiente. `ModuleAssetPublisher` resuelve dependencias y publica únicamente los módulos solicitados.

Los estilos propios de cada aplicación permanecen en `public/css/app` y se cargan después de los estilos compartidos.

## Proyecto inicial

`gframe/framework` contiene el núcleo y mantiene en `resources/skeleton` la fuente única del proyecto inicial. El comando `composer new` genera desde allí la portada pública, la estructura MVC mínima y el instalador visual. No existen aplicaciones base diferentes por perfil: el mismo instalador configura un sitio estático, una aplicación administrada o un SaaS.
