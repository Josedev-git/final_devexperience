<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli')
    exit('Somente CLI.');
require __DIR__ . '/../config/bootstrap.php';
use Core\DB;
if (count($argv) !== 4)
    exit("Uso: php bin/install.php Nome email senha-inicial-12+\nNão deixe a senha no histórico em ambiente real; use leitura interativa apropriada.\n");
[$script, $name, $email, $password] = $argv;
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72)
    exit("E-mail inválido ou senha fora de 12–72 bytes.\n");
if (DB::one("SELECT id FROM users WHERE role='admin' LIMIT 1"))
    exit("O administrador já existe; criação de outro bloqueada.\n");
DB::run("INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES(?,?,?,'admin',NOW())", [$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrador criado. Entre com o e-mail e a senha escolhidos.\n";
