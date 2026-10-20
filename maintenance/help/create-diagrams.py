"""Esquemas de contratos existentes; no representa pantallas incluidas."""
from pathlib import Path
from html import escape
from textwrap import wrap

DESTINATION = Path(__file__).resolve().parents[2] / 'docs/assets/4.3.1'

DIAGRAMS = {
    'entrega-correo': (
        'Solicitar un envío no confirma su recepción',
        'MailService: lanzamiento Async, ejecución del worker y aceptación SMTP.',
        [('Acción web', ['Valida el formulario.', 'Solicita el envío Async.', 'Recibe mail_queued o fallo.']),
         ('Worker', ['Carga la aplicación.', 'Lee SMTP y la plantilla.', 'Intenta realizar el envío.']),
         ('Servidor SMTP', ['Acepta o rechaza el mensaje.', 'La aceptación no prueba', 'recepción en la bandeja.'])],
        ['mail_queued solo confirma el lanzamiento aceptado, no el resultado SMTP.',
         'Los fallos posteriores se registran; estos métodos no aportan reintentos automáticos.',
         'En un worker puedes utilizar el método síncrono: no hace falta volver a encolar.']),
    'flujo-campanas': (
        'De la audiencia a los trabajos de entrega',
        'Una campaña coordina destinatarios; cada canal tiene su procesamiento.',
        [('Audiencia', ['Resuelve destinatarios', 'en el ámbito autorizado.', 'Prepara la campaña.']),
         ('Despacho por lotes', ['Reserva destinatarios.', 'Genera trabajos en cola', 'con deduplicación.']),
         ('Procesamiento de cola', ['Ejecuta el canal elegido.', 'Incorpora su resultado', 'de entrega o fallo.'])],
        ['completed indica que terminó la preparación de trabajos para los destinatarios.',
         'Cancelar la campaña no elimina trabajos que ya están en notification_queue.',
         'Registrar un transporte no lo añade automáticamente al selector del formulario.']),
    'caminos-cron': (
        'El disparador y el ejecutor son decisiones distintas',
        'Los cuatro caminos terminan en el runner de tareas persistentes del proyecto.',
        [('Disparador', ['Real: programador externo.', 'Virtual: visita web/AJAX', 'compatible con el disparador.']),
         ('Ejecutor', ['CLI: PHP y procesos.', 'HTTP: POST autenticado', 'a la URL del proyecto.']),
         ('Runner persistente', ['Selecciona tareas vencidas.', 'Aplica límites y reservas.', 'Ejecuta sus manejadores.'])],
        ['Real + CLI / Real + HTTP / Virtual + CLI / Virtual + HTTP.',
         'Sin visitas, virtual no garantiza cadencia. Real necesita programación del servidor.',
         'HTTP requiere secreto y URL confiable; no permite elegir handlers desde la petición.']),
    'politica-seo': (
        'Una política para la página y sus índices',
        'Decide qué puede indexarse; protege el acceso mediante autorización.',
        [('Configuración global', ['SEO e indexación habilitados', 'o bloqueados para el sitio.', 'Una ruta no supera el bloqueo.']),
         ('Elegibilidad de ruta', ['Tipo y protección de ruta.', 'context.seo.indexable.', 'Exclusiones de cada índice.']),
         ('Salidas', ['Metas del HTML.', 'sitemap.xml y llms.txt', 'según su política aplicable.'])],
        ['seo.indexable=false excluye de Sitemap y LLMS y fuerza noindex en el HTML.',
         'Las pantallas protegidas no se publican como rutas públicas indexables.',
         'robots.txt, noindex y la ausencia en un sitemap no sustituyen auth ni permisos.']),
    'seleccion-multimedia': (
        'Biblioteca, selección y formulario tienen su propio guardado',
        'Un archivo puede existir aunque el formulario consumidor todavía no se haya guardado.',
        [('Biblioteca', ['Guarda archivos y atributos.', 'Conserva su identidad', 'y las URLs del servidor.']),
         ('Selector', ['Elige y ordena archivos.', 'Confirma con «Usar».', 'Devuelve IDs y orden.']),
         ('Formulario del proyecto', ['Recibe la selección.', 'Valida acceso a los IDs.', 'Persiste la entidad propia.'])],
        ['Quitar de la selección no borra el archivo de la biblioteca.',
         'Cancelar no revierte atributos ya guardados ni elimina subidas correctas.',
         'Nombre visible y texto alternativo no renombran el archivo físico.']),
}

def text(x, y, content, size=18, bold=False, color='#203448'):
    return f'<text x="{x}" y="{y}" font-size="{size}" fill="{color}" font-weight="{700 if bold else 400}">{escape(content)}</text>'

def start(title, description):
    return ['<svg xmlns="http://www.w3.org/2000/svg" width="1120" height="640" viewBox="0 0 1120 640" role="img" aria-labelledby="title desc">',
            f'<title id="title">{escape(title)}</title><desc id="desc">{escape(description)}</desc>',
            '<defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0 0 8 4 0 8" fill="#596780"/></marker></defs>',
            '<rect width="1120" height="640" rx="20" fill="#f3f5fb"/><g font-family="Arial,sans-serif">',
            text(48, 65, title, 27, True), text(48, 104, description, 18)]

for filename, (title, description, cards, notes) in DIAGRAMS.items():
    svg = start(title, description)
    for index, (heading, lines) in enumerate(cards):
        x = 48 + index * 360
        svg.append(f'<rect x="{x}" y="158" width="304" height="190" rx="12" fill="{ "#e9e3ff" if index == 1 else "#fff"}" stroke="#c6cce0"/>')
        svg.append(text(x + 20, 196, heading, 21, True))
        for row, line in enumerate(lines):
            svg.append(text(x + 20, 238 + row * 30, line, 17))
        if index < 2:
            svg.append(f'<path d="M{x+304} 253H{x+349}" stroke="#596780" stroke-width="2" marker-end="url(#arrow)"/>')
    svg.append('<rect x="48" y="392" width="1024" height="200" rx="12" fill="#203448"/>')
    y = 430
    for note in notes:
        for line in wrap(note, width=105):
            svg.append(text(72, y, line, 17, color='#fff'))
            y += 25
        y += 10
    assert y <= 605, filename
    svg.append('</g></svg>')
    (DESTINATION / (filename + '.svg')).write_text('\n'.join(svg) + '\n', encoding='utf-8')

title = 'Cinco capas, de lo compartido a lo específico'
svg = start(title, 'Los valores posteriores amplían o sustituyen; CSS y JS se deduplican por su ruta.')
layers = [
    ('1. Global', 'config/meta/global.meta.php'),
    ('2. Template', 'app/views/templates/<plantilla>.meta.php'),
    ('3. Aportaciones al template', 'app/views/templates/meta/<plantilla>/*.meta.php'),
    ('4. Grupo de vistas', 'app/views/<grupo>/<grupo>.group.meta.php'),
    ('5. Vista concreta', 'app/views/<grupo>/<vista>.meta.php'),
]
for index, (heading, location) in enumerate(layers):
    y = 142 + index * 72
    svg.append(f'<rect x="48" y="{y}" width="1024" height="56" rx="10" fill="{ "#e9e3ff" if index == 4 else "#fff"}" stroke="#c6cce0"/>')
    svg.extend([text(70, y + 35, heading, 19, True), text(425, y + 35, location, 18)])
    if index < 4:
        svg.append(f'<path d="M560 {y+56}V{y+69}" stroke="#596780" stroke-width="2" marker-end="url(#arrow)"/>')
svg.append(text(48, 567, 'Las metas de carpetas superiores no se heredan automáticamente en grupos anidados.', 19))
svg.append('</g></svg>')
(DESTINATION / 'capas-meta.svg').write_text('\n'.join(svg) + '\n', encoding='utf-8')
print('Seis esquemas generados en docs/assets/4.3.1.')
