# Navegación pública

El home inicial conserva el mensaje «Algo maravilloso se construye aquí.» y la navegación de acceso o administración según la sesión. Su header está en el flujo normal; el body usa flexbox con altura mínima de ventana y el contenido ocupa el espacio restante, dejando visible el footer común. No se fija el alto ni se oculta contenido: con contenido ampliado o zoom elevado puede aparecer scroll natural. La animación de entrada se desactiva con `prefers-reduced-motion`. El acabado visual no requiere React ni otro módulo adicional.

## Alcance

`public/js/app/home/mngnoadmin.js` se publica con el esqueleto de la aplicación y se carga desde `home.group.meta.php`. Se extrae del archivo activo de Bebots, compartido con Base Confías y Dane. No es un módulo instalable adicional ni incluye contenido comercial, formularios de contacto o estilos de esas aplicaciones.

No modifica el diseño del header, sidebar ni footer. La portada inicial conserva su estructura; para activar las funciones de menú se usan los selectores siguientes en la plantilla pública del proyecto.

## Uso

Ejemplo de estructura, no de diseño obligatorio:

```html
<header id="header" class="sticky-top">
  <button type="button" class="mobile-nav-toggle" aria-controls="mng"
          aria-expanded="false" aria-label="Abrir o cerrar navegación">Menú</button>
  <nav id="mng" aria-label="Navegación principal">
    <ul class="navbar-nav">
      <li><a href="#servicios">Servicios</a></li>
      <li><a href="#contacto">Contacto</a></li>
    </ul>
  </nav>
</header>
<main>
  <section id="servicios">…</section>
  <section id="contacto">…</section>
</main>
<a href="#" class="scroll-top" aria-label="Volver arriba">Arriba</a>
```

En otro grupo público, añade el archivo a su meta:

```php
'js' => ['public/js/app/home/mngnoadmin.js'],
```

Cárgalo una sola vez después del HTML, como hacen los scripts del footer. No requiere jQuery. Bootstrap solo es necesario si el proyecto utiliza sus componentes. El controlador alterna `gicon-menu` y `gicon-close` en el control móvil, conservando el contrato original. Para un botón accesible con un icono `i.gicon` interior, define su presentación en el CSS del proyecto según `aria-expanded`; las clases del botón no sustituyen automáticamente las del icono interior.

El CSS de la aplicación define la apariencia y visibilidad usando `body.mobile-nav-active`, `body.scrolled`, `#mng a.current` y `.scroll-top.active`. El JavaScript no incorpora una hoja de estilos ni hace visible un menú que no tenga esas reglas. Bootstrap collapse y este controlador no deben controlar el mismo menú móvil.

## Funcionamiento

- Alterna el menú móvil, actualiza `aria-expanded` y bloquea el desplazamiento del body. Al cerrar restaura su valor anterior de overflow.
- Cierra al navegar, pulsar fuera del menú o usar Escape. Escape devuelve el foco al control.
- Intercepta las anclas existentes dentro del header solo cuando pertenecen a la página y consulta actuales. Enlaces externos, otras páginas, otros filtros, anclas inexistentes y controles dropdown/collapse conservan su comportamiento nativo.
- Desplaza a la sección compensando la altura del header. Mide sus estados normal y reducido mediante una copia invisible, con caché por ancho de pantalla.
- `#hero`, body y html son destinos de inicio. El movimiento respeta `prefers-reduced-motion`.
- Al seguir una sección elimina el fragmento de la URL, conservando ruta y consulta. También procesa el fragmento recibido al cargar la página.
- Marca enlaces de secciones con `current` mediante IntersectionObserver. Sin esta API siguen funcionando los clics y el menú, pero no el marcado automático al desplazarse.
- Aplica `scrolled` después de 50 px si el header tiene `sticky-top`, `fixed-top` o `scroll-up-sticky`; activa el botón de retorno después de 100 px.

## Personalización y límites

El proyecto define enlaces, contenido, estilos y puntos de ruptura en sus vistas y CSS. No se cambia el límite de 992 px de los proyectos originales. Las clases Bootstrap y los selectores anteriores permiten reutilizar el comportamiento sin copiar sus páginas comerciales.

El recurso del esqueleto pertenece a la aplicación creada: puedes ampliarlo allí. No edites el paquete instalado de Composer. Si cambias selectores, actualiza conjuntamente HTML, CSS y JavaScript. Este controlador inicializa el DOM inicial; no ofrece una API para reinicializar cabeceras sustituidas por AJAX, ni un gestor completo de foco para un menú modal.

## Verificación

Las pruebas automatizadas cubren carga sin elementos opcionales, menú y Escape, restauración de overflow, anclas con otra consulta, movimiento reducido, ausencia de IntersectionObserver y registro en meta. Queda pendiente la comprobación visual en una instalación con un header público real.
