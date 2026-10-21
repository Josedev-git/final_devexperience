<?php
// Roteador somente para servidor PHP de desenvolvimento; permite apenas assets e index.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && preg_match('#^/assets/[a-zA-Z0-9._/-]+$#', $path)) {
    $real = realpath(__DIR__ . $path);
    $base = realpath(__DIR__ . '/assets');
    if ($real && $base && str_starts_with($real, $base . DIRECTORY_SEPARATOR) && is_file($real))
        return false;
}
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Não encontrado.';
