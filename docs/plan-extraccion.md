# Plan de extracción

## Proyectos de referencia

Baseconfías y Bebots son las referencias principales:

- Baseconfías valida aplicaciones sin tenant y los permisos globales.
- Bebots valida aplicaciones con tenant, canales y procesos asíncronos.

DANE, Libros, RAG y AIPrint MVP sirven para detectar compatibilidad y evitar que el framework se diseñe alrededor de dos aplicaciones solamente.

## Etapas

### 1. Núcleo PHP

Extraer Router, RouteBuilder, Middleware, ORM, Render, Meta, errores, SEO, cron, heartbeat y utilidades. Mantener una capa de carga compatible con los proyectos existentes.

### 2. Dependencias

Sustituir las copias internas de librerías externas por Composer. Mantener separadas las librerías desarrolladas para GFrame hasta definir si pertenecen al núcleo o a paquetes opcionales.

### 3. Baseconfías

Instalar el paquete mediante un repositorio Composer de tipo `path`, ejecutar la matriz de pruebas y retirar la copia local del core únicamente cuando exista una reversión segura.

### 4. Bebots

Repetir la integración y validar tenancy, permisos, webhooks, SSE, cron y tareas en segundo plano. Las integraciones con canales, inteligencia artificial y proveedores permanecen en Bebots.

### 5. Recursos públicos

Extraer JavaScript, CSS, iconos y fuentes comunes. Antes de unificar `common.css`, `variables.css`, `admin.css` y `auth.css`, resolver las diferencias acumuladas entre proyectos.

### 6. Proyecto base e instalador

Crear `gframe/app` como plantilla mínima y un instalador que permita iniciar proyectos con `composer create-project`.

## Criterios de salida

- Las pruebas pasan en Baseconfías y Bebots.
- Ninguna dependencia externa está copiada dentro de `src`.
- El paquete no contiene secretos ni configuración de una aplicación.
- Los cambios incompatibles están documentados.
- Cada proyecto fija una versión mediante `composer.lock`.
