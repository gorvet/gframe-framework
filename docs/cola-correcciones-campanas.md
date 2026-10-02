# Cola de correcciones de Campañas

Orden acordado con el usuario. Todo se implementa en GFrame; los demos se actualizan después.

1. [x] Campañas automáticas en una vista separada: suspensión/bloqueo, verificación y recordatorios de cuentas desactivadas. Política posterior aprobada: 60 días de retención y aviso previo de 72 horas, configurables.
   - [x] Apartado, vista separada, configuración por ámbito y aviso de suspensión desde Gestión de usuarios.
   - [x] Listado, edición en modal, envío manual y revisión periódica de suspendidos, bloqueados y pendientes de verificación. Plazo antirduplicados compartido por regla y usuario.
   - [x] Ciclo registrado desde Mi cuenta, correo inmediato, recordatorio previo y borrado únicamente tras vencer la fecha y transcurrir el margen desde el correo enviado. Protección de cuentas antiguas, reactivadas y del superadministrador; rollback ante relaciones que impidan borrar.
2. [x] Selección manual de destinatarios antes del mensaje.
3. [x] Calendario con tipografía, tamaños y colores de variables, también en modo oscuro. Comprobados estilos computados en ambos temas.
4. [x] Acciones alineadas a la derecha; Cancelar antes del botón principal. Formularios, modales y confirmaciones de los módulos revisados.
5. [x] Campañas inmediatas completadas visibles en la campana: consumidor inbox transaccional, persistencia, registro cron y actualización al abrir.
6. [x] Recurrencia diaria/semanal, vista previa de destinatarios, envío de prueba exclusivo para la cuenta conectada, enlace de acción, importancia y caducidad.

Las peticiones nuevas se añaden a esta cola; no reemplazan tareas pendientes.

7. [x] Envío automático directo, con destinatarios resueltos por la regla e historial separado de envíos.
8. [x] Subtítulo debajo del título; formulario de contenido y entrega coherente, fecha junto a frecuencia y botones auxiliares diferenciados.
9. [x] Edición completa de campañas pendientes y próxima ejecución de recurrentes, sin reescribir envíos anteriores.
10. [x] Campana y contador juntos; desplegable del inbox alineado, desplazamiento interno y adaptación móvil.
