<main class="container py-4"><h1>Artículo de prueba</h1>
<article id="articleBody"><?= $data['article']['content'] ?? 'Contenido no disponible.' ?></article>
<?php if (isset($data['preview'])) {
    $contentPreview = ['state_url' => site_url . 'preview-state', 'version' => $data['preview']['version']];
    include GFrame\Modules\ModuleRuntime::file('views', 'content-preview/preview.php', 'content-preview');
} ?>
</main>
