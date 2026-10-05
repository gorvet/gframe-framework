# Reconstrucción de la documentación de GFrame

Esta rama reconstruye la documentación de GFrame tomando **el código como fuente de verdad**. La documentación existente se conserva como evidencia secundaria: se reutiliza cuando coincide con el runtime actual, se corrige cuando quedó obsoleta y se reorganiza cuando el problema es de aprendizaje o descubribilidad.

## Objetivo

La documentación debe permitir que un desarrollador pueda construir una aplicación real sin tener que conocer de antemano la arquitectura interna del framework.

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
- empaquetar o personalizar una capacidad mediante módulos.

La referencia técnica continúa existiendo, pero queda detrás de una guía práctica de desarrollo.

## Principios de esta reconstrucción

1. **El código manda.** Una afirmación documental debe poder justificarse con el runtime, el instalador, los módulos o las pruebas actuales.
2. **No confundir convención con obligación.** Por ejemplo, `app/controllers`, `app/services`, `app/models` y `app/views` forman la estructura recomendada del proyecto, pero el autoload ordinario de la aplicación y el runtime de módulos tienen mecanismos distintos que deben explicarse por separado.
3. **Separar aprendizaje y referencia.** La guía práctica enseña recorridos completos. Las páginas especializadas documentan contratos, opciones y casos límite.
4. **No documentar cada clase interna como si fuera API pública.** Primero se clasifica cada pieza como API de proyecto, punto de extensión, compatibilidad legacy o infraestructura interna.
5. **Conservar lo que ya está bien.** Varias guías actuales —rutas, render, middleware, ORM, sesiones, notificaciones, correo, multimedia, cron— contienen información útil y no deben reescribirse por deporte.
6. **Hacer visibles las capacidades.** Una capacidad documentada pero fuera del índice sigue siendo, en la práctica, difícil de descubrir.
7. **Usar recorridos verticales.** Los conceptos se introducen dentro de funcionalidades completas, no como una sucesión de subsistemas aislados.
8. **No romper compatibilidad documental sin señalarla.** Las funciones, clases o rutas históricas que permanezcan por compatibilidad deben marcarse como legacy cuando exista una API preferida.

## Capas de la nueva documentación

### 1. Aprender GFrame

Debe responder, en orden:

- qué es GFrame;
- cómo queda un proyecto instalado;
- qué ocurre desde la URL hasta la respuesta;
- qué responsabilidad tiene cada carpeta;
- cómo crear una funcionalidad completa;
- cómo evolucionarla con AJAX, permisos, multimedia, correo, notificaciones y procesos en segundo plano.

### 2. Guías por tarea

Ejemplos:

- crear una página;
- crear un CRUD;
- crear un listado AJAX;
- proteger una acción;
- consumir una API externa;
- trabajar con archivos;
- enviar correo;
- usar Async o Cron;
- crear o personalizar un módulo.

### 3. Referencia técnica

Aquí permanecen los contratos detallados de Router, Render, Middleware, ORM, SessionRuntime, módulos, Mail, Media, Notifications, etc.

## Mapa real de capacidades detectadas

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

El inventario de `resources/modules` contiene también las bibliotecas visuales e integraciones publicables. Su existencia no implica que cada una necesite una guía extensa: primero se distingue módulo funcional, runtime MVC, componente frontend e integración de terceros.

## Backlog vivo

Estados:

- **cubierto**: la información existe y coincide razonablemente con el código;
- **parcial**: existe, pero falta recorrido, integración, precisión o descubribilidad;
- **ausente**: no existe una guía adecuada;
- **obsoleto**: contradice el estado actual del código;
- **en auditoría**: aún no se ha cerrado la comparación código/documentación.

| ID | Área | Estado | Prioridad | Trabajo |
| --- | --- | --- | --- | --- |
| DOC-001 | Guía real de desarrollo | ausente | P0 | Crear recorrido URL → ruta → middleware → controller → service → model/ORM → view/template → respuesta. |
| DOC-002 | Índice y navegación | parcial | P0 | Reorganizar por tareas y recorridos, conservando referencia por subsistema. |
| DOC-003 | Primera funcionalidad completa | ausente | P0 | Tutorial vertical con una funcionalidad tipo Productos. |
| DOC-004 | Services | parcial | P0 | Explicar responsabilidad, cuándo introducirlos y relación con controller/model. |
| DOC-005 | CRUD + AJAX | parcial | P0 | Mostrar patrón web inicial + recargas parciales por AJAX + CSRF + feedback. |
| DOC-006 | Auth + permisos + sesiones | parcial | P0 | Unificar el modelo mental sin eliminar las guías técnicas actuales. |
| DOC-007 | Módulos | parcial | P0 | Separar «instalar capacidad», «módulo runtime», «personalizar» y «crear módulo». |
| DOC-008 | Helpers PHP | parcial | P1 | Clasificar API moderna, utilidades internas y wrappers legacy. |
| DOC-009 | Cliente HTTP saliente | ausente/no descubrible | P1 | Documentar `HttpClient::request()` y diferenciarlo del canal API entrante. |
| DOC-010 | Cifrado | ausente/no descubrible | P1 | Documentar `GFrame\Security\Encryption` y gestión de claves. |
| DOC-011 | Async | cubierto pero oculto | P1 | Enlazar directamente y compararlo con Cron. |
| DOC-012 | Cron | cubierto | P1 | Integrarlo en una guía de decisión Async vs Cron. |
| DOC-013 | Media | cubierto técnicamente | P1 | Integrarlo en recorridos de aplicación. |
| DOC-014 | Notificaciones | cubierto técnicamente | P1 | Integrarlo con eventos de negocio, correo, cola y cron. |
| DOC-015 | Mail | cubierto técnicamente | P1 | Crear entrada práctica más corta y dejar `mail.md` como referencia extensa. |
| DOC-016 | SEO | parcial | P1 | Crear mapa de capacidades y recorrido de uso. |
| DOC-017 | Autoload de aplicación vs módulos | parcial | P1 | Explicar Bootstrap vs ModuleRuntime sin mezclarlos. |
| DOC-018 | Instalador y perfiles | parcial | P1 | Explicar qué genera cada perfil y qué capacidades quedan instaladas. |
| DOC-019 | `src/database/ORM_GUIDE.md` | obsoleto | P0 | Corregir o retirar referencias a rutas/configuración antiguas. |
| DOC-020 | README / `composer new` | parcial | P1 | Evitar confusión entre script del repositorio y comando nativo de Composer. |
| DOC-021 | Capabilities map completo | en auditoría | P0 | Mantener matriz código → API útil → documentación → acción. |
| DOC-022 | Compatibilidad legacy | en auditoría | P1 | Identificar funciones/clases históricas y señalar API preferida. |

## Orden de trabajo

### Fase A — columna vertebral

1. rehacer `docs/index.md`;
2. crear una guía de desarrollo práctica;
3. crear el primer recorrido vertical completo;
4. enlazar las referencias técnicas existentes desde esos recorridos.

### Fase B — huecos reales

1. cliente HTTP saliente;
2. cifrado;
3. helpers PHP;
4. decisión Async vs Cron;
5. mapa de Auth/permisos/sesiones;
6. módulos desde el punto de vista de una aplicación.

### Fase C — saneamiento

1. retirar o corregir documentos legacy;
2. revisar ejemplos contra código actual;
3. eliminar duplicaciones contradictorias;
4. unificar terminología;
5. comprobar enlaces y navegación.

## Convivencia con otras ramas

Esta reconstrucción se desarrolla en `codex/reconstruccion-documentacion` para no interferir con cambios simultáneos de código o documentación.

Cuando otra rama cambie APIs, rutas, módulos o comportamiento documentado, la fusión debe hacerse comparando primero el código resultante. No se debe resolver un conflicto documental escogiendo automáticamente «la versión más nueva»: después de la integración, el código vuelve a ser la fuente de verdad.

## Criterio de finalización

La reconstrucción estará suficientemente cerrada cuando un desarrollador nuevo pueda:

1. instalar GFrame;
2. entender la estructura del proyecto;
3. crear una funcionalidad completa sin leer primero la implementación del framework;
4. encontrar la capacidad adecuada para autenticación, permisos, AJAX, archivos, correo, notificaciones, tareas, integraciones y SEO;
5. pasar de una guía práctica a la referencia técnica cuando necesite detalles;
6. distinguir con claridad API recomendada, compatibilidad legacy e infraestructura interna.
