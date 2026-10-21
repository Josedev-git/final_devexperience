<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
// Não publicar ROOT: somente public/ deve ser acessível pelo servidor web.
$envFile = ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '='))
            continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (getenv($key) === false)
            putenv($key . '=' . trim(trim($value), "\"'"));
    }
}
function env(string $key, string $default = ''): string
{
    return getenv($key) === false ? $default : (string) getenv($key);
}
date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));
spl_autoload_register(function (string $class): void {
    $path = ROOT . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path))
        require $path;
});
require ROOT . '/app/Core/helpers.php';
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    session_name('senn_session');
    session_set_cookie_params(['httponly' => true, 'secure' => env('SESSION_SECURE') === '1', 'samesite' => 'Lax', 'path' => env('APP_BASE_PATH', '') . '/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://unpkg.com; style-src 'self' https://unpkg.com 'unsafe-inline'; img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}
set_exception_handler(function (Throwable $error): void {
    $incident = bin2hex(random_bytes(6));
    error_log('SENN incident=' . $incident . ' type=' . get_class($error)); // sem SQL/dados pessoais
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Falha interna. Referência: $incident\n");
        exit(1);
    }
    echo 'Falha interna. Confira a configuração ou contate o suporte. Referência: ' . e($incident);
});
