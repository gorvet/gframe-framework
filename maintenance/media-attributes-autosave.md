# Guardado de atributos al salir del selector

Solicitud: guardar atributos al usar una imagen, cambiar de imagen o cerrar el selector, sin exigir un botón previo. Se conserva el guardado manual y Descartar. Skills canónicas: orchestrator, media-module y frontend-admin. Fuentes del framework; no modificar aplicaciones instaladas. La publicación pendiente continúa después de esta corrección.

El cambio espera el resultado del guardado antes de completar la acción. Un rechazo o fallo de red conserva el modal y los valores, muestra un aviso y permite reintentar. Una operación en curso no debe generar solicitudes duplicadas ni aplicar una respuesta a otra imagen. Se comprobará el recorrido del selector con su controlador y SQLite temporales, incluida selección simple/múltiple y cierre.

Verificado con Chrome y el controlador real sobre SQLite temporal: guardado normalizado al cerrar, cambiar de imagen e insertar en selección simple/múltiple; un solo guardado ante doble clic; rechazo de permisos y corte de red con modal y valores conservados; reintento posterior correcto. El mismo recorrido mantiene subida, orden, cancelación, campos consumidores y respuestas tardías. Una prueba de navegador aprobada en 24 s; no se repitieron suites generales ni se modificó una aplicación instalada.
