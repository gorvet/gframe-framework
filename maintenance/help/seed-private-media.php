<?php
require $argv[1] . '/packages/autoload.php';
$root = $argv[2];
// Resolve the fixture's actual configured SQLite path rather than assuming its name.
$databases = glob($root . '/storage/*.sqlite');
if (count($databases) !== 1) throw new RuntimeException('No se pudo resolver la base de datos temporal.');
define('DB_DEFAULT_CONNECTION', 'capture');
define('DB_CONNECTIONS', ['capture' => ['driver' => 'sqlite', 'path' => $databases[0]]]);
$storage = new GFrame\Media\MediaStorage($root . '/public', $root . '/private');
$media = new GFrame\Media\MediaLibraryService(new GFrame\Media\MediaModel(), $storage);
$file = $root . '/documento.txt';
file_put_contents($file, 'Documento de ejemplo para mostrar la visibilidad de un archivo.');
foreach ([['Guía de bienvenida.txt', 'public'], ['Recursos del equipo.txt', 'public'], ['Documento de acceso restringido.txt', 'private'], ['Notas de coordinación.txt', 'private']] as [$name, $visibility]) {
    $result = $media->registerLocalFile($file, $name, 'capture', GFrame\Media\MediaScope::global(), [], $visibility);
    if ($result['status'] !== 'success') throw new RuntimeException($result['code']);
}
