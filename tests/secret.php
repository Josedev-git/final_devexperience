<?php
declare(strict_types=1);
// Testa apenas geração/persistência da chave; não precisa de banco.
const AUTH_FILE = __DIR__ . '/../app/Core/Auth.php';
define('ROOT', sys_get_temp_dir() . '/senn-key-test-' . bin2hex(random_bytes(8)));
mkdir(ROOT, 0700, true);
function env(string $name, string $default = ''): string
{
    return getenv($name) === false ? $default : (string) getenv($name);
}
require AUTH_FILE;
$method = new ReflectionMethod(Core\Auth::class, 'secretKey');
$count = 0;
function check(bool $ok, string $name): void
{
    global $count;
    if (!$ok)
        throw new RuntimeException('Falhou: ' . $name);
    echo 'OK ' . $name . PHP_EOL;
    $count++;
}
try {
    putenv('APP_SECRET');
    $key = $method->invoke(null);
    check((bool) preg_match('/^[a-f0-9]{64}$/D', $key), 'Gera chave aleatória sem APP_SECRET');
    check(is_file(ROOT . '/storage/app.key'), 'Persiste chave fora de public');
    check($method->invoke(null) === $key, 'Reutiliza chave entre chamadas');
    putenv('APP_SECRET=');
    check($method->invoke(null) === $key, 'Aceita configuração vazia');
    putenv('APP_SECRET=SUBSTITUA_POR_64_CARACTERES_ALEATORIOS');
    check($method->invoke(null) === $key, 'Ignora placeholder antigo');
    putenv('APP_SECRET=curta');
    check($method->invoke(null) === $key, 'Gera fallback para configuração curta');
    $custom = str_repeat('f', 64);
    putenv('APP_SECRET=' . $custom);
    check($method->invoke(null) === $custom, 'Preserva configuração avançada explícita');
    putenv('APP_SECRET');
    file_put_contents(ROOT . '/storage/app.key', 'corrompida');
    $failed = false;
    try {
        $method->invoke(null);
    } catch (DomainException $error) {
        $failed = true;
    }
    check($failed, 'Não substitui silenciosamente chave corrompida');
    echo "$count testes da chave automática aprovados.\n";
} finally {
    putenv('APP_SECRET');
    if (is_file(ROOT . '/storage/app.key'))
        unlink(ROOT . '/storage/app.key');
    if (is_dir(ROOT . '/storage'))
        rmdir(ROOT . '/storage');
    rmdir(ROOT);
}
