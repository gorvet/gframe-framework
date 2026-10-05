# Reconstrucción de la documentación de GFrame

Esta rama reconstruye la documentación de GFrame tomando **el código como fuente de verdad**. La documentación existente se reutiliza cuando coincide con el runtime, se corrige cuando quedó obsoleta y se reorganiza cuando el problema es de aprendizaje o descubribilidad.

## Objetivo

La documentación debe permitir que un desarrollador construya una aplicación real sin tener que conocer de antemano la implementación interna del framework.

La pregunta principal deja de ser «¿qué clases existen?» y pasa a ser «¿cómo hago esta tarea con GFrame?».

Ejemplos:

- crear una pantalla `/productos`;
- listar y guardar datos;
- actualizar un fragmento por AJAX;
- proteger una acción con autenticación o permisos;
- subir y relacionar archivos;
- enviar correo o notificaciones;
- sacar trabajo fuera de la petición web;
- programar una tarea;
- consumir una API externa;
- instalar, extender o personalizar un módulo.

## Principios

1. **El código manda.** Toda afirmación debe poder justificarse con el runtime, instalador, módulos o pruebas actuales.
2. **No confundir convención con obligación.** La estructura recomendada del proyecto y el comportamiento impuesto por el runtime se documentan por separado.
3. **Separar aprendizaje y referencia.** Las guías prácticas enseñan recorridos completos; las páginas especializadas documentan contratos y casos límite.
4. **No documentar cada clase interna como API pública.** Primero se clasifica como API de proyecto, punto de extensión, compatibilidad legacy o infraestructura interna.
5. **Conservar lo que ya está bien.** Rutas, Render, Middleware, ORM, Sesiones, Media, Notificaciones, Mail y Cron ya contienen bastante referencia útil.
6. **Hacer visibles las capacidades.** Una capacidad técnicamente documentada pero ausente del recorrido principal sigue siendo difícil de descubrir.
7. **Usar recorridos verticales.** Los conceptos se introducen dentro de funcionalidades completas.
8. **Señalar compatibilidad legacy.** Los wrappers y contratos históricos no deben enseñarse como primera opción cuando existe una API preferida.

## Capas de la nueva documentación

### Aprender GFrame

Debe explicar en orden:

- qué es GFrame;
- cómo queda un proyecto instalado;
- cómo viaja una petición;
- qué responsabilidad tiene cada carpeta;
- cómo crear una funcionalidad completa;
- cómo evolucionarla con AJAX, permisos, multimedia, correo, notificaciones y procesos en segundo plano.

### Guías por tarea

Ejemplos:

- crear una página;
- crear un CRUD;
- crear un listado AJAX;
- proteger una acción;
- consumir una API externa;
- trabajar con archivos;
- enviar correo;
- elegir Async o Cron;
- instalar o personalizar un módulo.

### Referencia técnica

Aquí permanecen Router, Render, Middleware, ORM, SessionRuntime, módulos, Mail, Media, Notifications y demás contratos detallados.

## Mapa de capacidades detectadas

La auditoría del código confirma, entre otras, estas áreas:

- bootstrap y configuración;
- routing web, AJAX, API, webhook, system y SSE;
- render, vistas, templates, meta y footer;
- middleware, autenticación, roles y permisos;
- sesiones `database`, `redis` y `native`;
- ORM, conexiones y dialectos;
- módulos, catálogo, dependencias, publicación y runtime;
- multimedia;
- correo y plantillas;
- notificaciones, transportes, colas y campañas;
- ejecución asíncrona;
- cron y tareas persistentes;
- heartbeat;
- cliente HTTP saliente;
- cifrado y sanitización HTML;
- SEO, sitemap, robots, JSON-LD y `llms.txt`;
- WordPress headless;
- utilidades PHP y frontend;
- instalación, perfiles y actualización.

El inventario de `resources/modules` ya enumera los módulos existentes. El problema documental principal no era contar carpetas, sino explicar **qué capacidad resuelve cada pieza y cómo se integra en una aplicación**.

## Backlog vivo

Estados:

- **cubierto**: existe una guía o referencia suficientemente útil para esta fase;
- **parcial**: existe, pero falta integración, precisión o descubribilidad;
- **corregido**: existía una contradicción concreta y ya se saneó en esta rama;
- **en auditoría**: falta cerrar la comparación código/documentación.

| ID | Área | Estado | Prioridad | Resultado / siguiente acción |
| --- | --- | --- | --- | --- |
| DOC-001 | Guía real de desarrollo | cubierto | P0 | `guia-desarrollo.md`: recorrido URL → ruta → middleware → controller → service → model/ORM → view/template → respuesta. |
| DOC-002 | Índice y navegación | cubierto | P0 | `index.md` reorganizado por tareas y recorridos; conserva referencia técnica. |
| DOC-003 | Primera funcionalidad completa | cubierto | P0 | `tutorial-productos.md`: CRUD vertical con web, AJAX, partial, ORM y permisos. |
| DOC-004 | Services | cubierto | P0 | Introducidos en guía y tutorial como capa opcional para lógica reutilizable. |
| DOC-005 | CRUD + AJAX | cubierto | P0 | Tutorial muestra carga web inicial, mutaciones AJAX, CSRF, permisos y recarga de fragmento. |
| DOC-006 | Auth + permisos + sesiones | cubierto | P0 | `identidad-autorizacion.md` conecta autenticación, identidad, middleware, permisos, tenant y reglas de recurso. |
| DOC-007 | Módulos | cubierto | P0 | `modulos-en-aplicacion.md` separa capacidad instalable, runtime MVC, componente frontend y funcionalidad propia. |
| DOC-008 | Helpers PHP | cubierto | P1 | `helpers-php.md` clasifica helpers y wrappers globales legacy. |
| DOC-009 | Cliente HTTP saliente | cubierto | P1 | `http-client.md`; se distingue claramente de API entrante. |
| DOC-010 | Cifrado | cubierto | P1 | `encryption.md`: AES-256-GCM, claves, rotación y límites reales. |
| DOC-011 | Async | cubierto | P1 | Enlazado desde índice y comparado con Cron/colas en `procesos-segundo-plano.md`. |
| DOC-012 | Cron | cubierto | P1 | Conserva referencia y ahora forma parte de la guía de decisión. |
| DOC-013 | Media | parcial | P1 | Referencia técnica ya fuerte; falta revisar si merece una entrada práctica más corta además de su integración en guía/tutorial. |
| DOC-014 | Notificaciones | parcial | P1 | Referencia técnica fuerte; ya aparece en flujos de negocio, pero falta cerrar auditoría de campañas/transportes. |
| DOC-015 | Mail | parcial | P1 | Referencia extensa; se corrigió dependencia Async. Evaluar una entrada práctica corta sin duplicar `mail.md`. |
| DOC-016 | SEO | parcial | P1 | Auditar Sitemap/Robots/Llms/JsonLD/SchemaComposer y construir mapa práctico si aporta valor. |
| DOC-017 | Autoload de aplicación vs módulos | cubierto | P1 | `autoload-proyecto.md` explica Bootstrap basename map frente a ModuleRuntime namespaced overrides. |
| DOC-018 | Instalador y perfiles | cubierto | P1 | `perfiles-instalacion.md` verificado contra `profiles.php`, `InstallationProfileCatalog`, `ProjectInstaller`, `ProjectConfigWriter` y `SchemaInstaller`. |
| DOC-019 | `src/database/ORM_GUIDE.md` | corregido | P0 | Sustituido por nota interna actual; ya no enseña `core/database` ni `config/Config.php`. |
| DOC-020 | README / `composer new` | corregido | P1 | README aclara que `composer new` es un script del repo y no un comando nativo de Composer. |
| DOC-021 | Capabilities map completo | en auditoría | P0 | Seguir cerrando SEO y áreas avanzadas antes de declararlo completo. |
| DOC-022 | Compatibilidad legacy | parcial | P1 | Helpers globales ya clasificados; continuar revisión de fachadas/contratos históricos restantes. |
| DOC-023 | Serialización Async | corregido | P0 | `async.md` y `mail.md` ahora reflejan `Opis\Closure\SerializableClosure` / `opis/closure:^3.7`. |
| DOC-024 | Autoload y namespaces | corregido/cubierto | P0 | Documentado que el proyecto generado no trae PSR-4 general `App\`; clases normales y overrides de módulos siguen contratos distintos. |

## Trabajo realizado en esta rama

### Columna vertebral nueva

- `docs/guia-desarrollo.md`
- `docs/tutorial-productos.md`
- `docs/identidad-autorizacion.md`
- `docs/modulos-en-aplicacion.md`
- `docs/procesos-segundo-plano.md`
- `docs/autoload-proyecto.md`

### Capacidades antes poco descubribles

- `docs/http-client.md`
- `docs/encryption.md`
- `docs/helpers-php.md`

### Navegación y onboarding

- `docs/index.md` reorganizado por tareas.
- `README.md` reconstruido para separar creación de proyecto de instalación del paquete.

### Contradicciones saneadas

- `src/database/ORM_GUIDE.md`: rutas/configuración antiguas retiradas.
- `docs/async.md`: serialización corregida a Opis Closure.
- `docs/mail.md`: dependencia de Async corregida a `opis/closure:^3.7`.

### Instalación y perfiles verificados

- `resources/install/profiles.php` define de forma explícita `static`, `managed`, `intranet` y `saas`.
- `InstallationProfileCatalog` normaliza perfiles y filtra módulos opcionales incompatibles.
- `ProjectInstaller` resuelve defaults + módulos del perfil + opcionales + dependencias, instala esquemas y escribe el lock.
- `ProjectConfigWriter` genera `config/app.php` y `.env`, incluido `SESSION_DRIVER` según exista o no base de datos.
- `SchemaInstaller` aplica tenancy antes de auth y después los esquemas de módulos resueltos.
- `docs/perfiles-instalacion.md` coincide con ese comportamiento en esta revisión.

## Siguiente bloque de auditoría

### SEO

Comparar documentación con:

- `Sitemap`;
- `SitemapDataProvider`;
- `Robots`;
- `Llms`;
- `JsonLD`;
- `SchemaComposer`;
- presets de schema.

### Capacidades avanzadas

Cerrar auditoría de:

- Media;
- Notifications / Email transport / Campaigns;
- Heartbeat;
- WordPress Headless;
- errores y respuestas;
- posibles APIs legacy restantes.

## Convivencia con otras ramas

Esta reconstrucción se desarrolla en `codex/reconstruccion-documentacion` para no interferir con cambios simultáneos de código o documentación.

Cuando otra rama cambie APIs, rutas, módulos o comportamiento documentado, la fusión debe hacerse comparando primero el código resultante. No se resolverá un conflicto documental escogiendo automáticamente «la versión más nueva»: después de integrar, **el código vuelve a ser la fuente de verdad**.

## Criterio de finalización

La reconstrucción estará suficientemente cerrada cuando un desarrollador nuevo pueda:

1. instalar GFrame;
2. entender la estructura del proyecto;
3. crear una funcionalidad completa sin leer primero la implementación del framework;
4. encontrar la capacidad adecuada para autenticación, permisos, AJAX, archivos, correo, notificaciones, tareas, integraciones y SEO;
5. pasar de una guía práctica a la referencia técnica cuando necesite detalles;
6. distinguir API recomendada, compatibilidad legacy e infraestructura interna;
7. saber qué instala cada perfil y qué sigue siendo responsabilidad del proyecto.
