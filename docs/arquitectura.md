# Arquitectura de GFrame

## Objetivo

GFrame debe funcionar como un framework instalable y versionado. El núcleo no puede depender de reglas de negocio de Baseconfías, Bebots ni de otra aplicación concreta.

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
- Configuración, migraciones y datos.
- Integraciones exclusivas del proyecto.

### Paquetes opcionales

Los módulos reutilizables que no sean necesarios en todas las aplicaciones deben instalarse aparte. Entre los candidatos están la biblioteca de medios, el editor, las notificaciones, la clasificación de textos y la conversión de audio.

## Compatibilidad inicial

La serie `0.x` conserva las clases globales del core actual. Composer genera el mapa de clases y `GFrame\Foundation\Bootstrap` inicia la aplicación. Los namespaces se incorporarán gradualmente, con periodos de compatibilidad y avisos de obsolescencia.

## Configuración

El framework admite dos modos de permisos:

- Global, cuando la aplicación no define tenancy.
- Por tenant, cuando define conjuntamente el identificador y la tabla correspondiente.

Las reglas particulares de áreas, estados editoriales o visibilidad pertenecen a la aplicación.

## Recursos públicos

Los recursos comunes se distribuirán desde un paquete de interfaz y se publicarán en `public/assets/gframe`. Los estilos propios permanecerán en `public/css/app` y se cargarán después de los estilos del framework.
