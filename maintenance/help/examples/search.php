<?php
// Ejemplo documental: interfaz propia, resultados calculados por el motor original.
require $argv[1] . '/resources/modules/lexical-search/application/app/services/lexical-search/LexicalSearchEngine.php';
$rows = [
    ['title' => 'Gestión de campañas', 'body' => 'Prepara avisos para el equipo y elige la audiencia autorizada antes de programar una entrega.'],
    ['title' => 'Programar campañas recurrentes', 'body' => 'Organiza recordatorios periódicos y revisa sus destinatarios, el estado de la campaña y las próximas ejecuciones.'],
    ['title' => 'Biblioteca multimedia', 'body' => 'Organiza imágenes, documentos y archivos compartidos para reutilizarlos en tus publicaciones.'],
];
$query = 'campañs';
$results = (new GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine())->rank($rows, $query, ['title' => 2, 'body' => 1], ['snippet_fields' => ['body']]);
if (count($results) !== 2) throw new RuntimeException('El ejemplo debe mostrar dos coincidencias calculadas por el motor.');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html lang="es"><meta charset="utf-8"><title>Ejemplo de búsqueda léxica</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:36px;background:#f3f5fb;color:#203448;font:17px/1.6 Arial,sans-serif}main{background:white;padding:32px;border:1px solid #d9dfeb;border-radius:12px}small{font-size:14px;color:#626d7c}h1{font-size:32px;line-height:1.2;margin:8px 0 14px}p{margin:0 0 20px}label{display:block;font-weight:700;margin-bottom:8px}input{width:100%;padding:13px 16px;font:inherit;border:2px solid #7961d8;border-radius:7px;color:inherit;background:white}ol{padding:0;margin:0;list-style:none}li{padding:22px 0;border-top:1px solid #d9dfeb}h2{font-size:22px;margin:0 0 8px}li p{margin:0}footer{padding-top:18px;border-top:1px solid #d9dfeb;font-size:14px;color:#626d7c}
</style><main>
<small>ESTUDIO NEXO · EJEMPLO DE INTEGRACIÓN</small>
<h1>Encuentra una guía del equipo</h1>
<p>La aplicación entrega los documentos permitidos. El motor busca sobre ese conjunto y los ordena por relevancia.</p>
<label for="query">Buscar en las guías</label><input id="query" value="<?= $escape($query) ?>" readonly>
<p><small><?= count($results) ?> resultados entre <?= count($rows) ?> documentos del ejemplo. El título tiene más peso que el cuerpo.</small></p>
<ol><?php foreach ($results as $result): ?><li><h2><?= $escape($result['title']) ?></h2><p><?= $escape($result['snippet']) ?></p></li><?php endforeach; ?></ol>
<footer>Resultados reales de LexicalSearchEngine::rank(). La interfaz pertenece al ejemplo; el módulo no instala esta pantalla.</footer>
</main></html>
