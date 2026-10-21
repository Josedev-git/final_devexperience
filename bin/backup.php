<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli')
    exit;
require __DIR__ . '/../config/bootstrap.php';
// Arquivo temporário de credenciais evita senha na linha de comando do dump.
$dir = env('BACKUP_DIR') ?: ROOT . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true))
    exit("Falha ao criar diretório.\n");
$config = tempnam(sys_get_temp_dir(), 'senn-db-');
chmod($config, 0600);
$quote = fn(string $s): string => '"' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $s) . '"';
file_put_contents($config, "[client]\nhost=" . $quote(env('DB_HOST')) . "\nport=" . $quote(env('DB_PORT', '3306')) . "\nuser=" . $quote(env('DB_USER')) . "\npassword=" . $quote(env('DB_PASS')) . "\n");
$file = $dir . '/senn-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
$h = fopen($file, 'x');
chmod($file, 0600);
try {
    $binary=env('MYSQLDUMP_PATH');
    if($binary==='') { $xampp=dirname(PHP_BINARY,2).'/mysql/bin/mysqldump.exe'; $binary=is_file($xampp)?$xampp:'mysqldump'; }
    $process = proc_open([$binary, '--defaults-extra-file=' . $config, '--single-transaction', '--skip-triggers', '--no-tablespaces', env('DB_NAME')], [0 => ['pipe', 'r'], 1 => $h, 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process))
        throw new RuntimeException('Dump não iniciou.');
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    fclose($h);
    $h = null;
    if ($code !== 0 || filesize($file) === 0) {
        unlink($file);
        throw new RuntimeException('mysqldump falhou. Confira instalação e permissões.');
    }
    echo "Backup criado: $file\n";
} finally {
    if (is_resource($h))
        fclose($h);
    unlink($config);
}
