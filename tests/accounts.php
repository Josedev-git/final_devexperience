<?php
declare(strict_types=1);
// Exercita os serviços reais com SQLite em memória. Adapta apenas sintaxe do driver.
// Não substitui a validação final de DDL/concorrência em MySQL/MariaDB.
putenv('APP_SECRET=' . str_repeat('e', 64));
putenv('APP_ENV=local');
require __DIR__ . '/../config/bootstrap.php';
session_start();
ob_start();
use Core\{DB, Auth};
use Services\Accounts;
class AccountTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace('IF(failures>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL)', "CASE WHEN failures+1>=5 THEN datetime('now','+15 minutes') ELSE NULL END", $query);
        $query = str_replace(['INSERT IGNORE INTO', ' FOR UPDATE', 'NOW()'], ['INSERT OR IGNORE INTO', '', 'CURRENT_TIMESTAMP'], $query);
        return parent::prepare($query, $options);
    }
}
$pdo = new AccountTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,active INTEGER DEFAULT 1,privacy_accepted_at TEXT, admin_singleton INTEGER GENERATED ALWAYS AS (CASE WHEN role='admin' THEN 1 ELSE NULL END) STORED UNIQUE)");
$pdo->exec('CREATE TABLE audit_logs(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER)');
$pdo->exec('CREATE TABLE login_attempts(key_hash TEXT PRIMARY KEY,failures INTEGER DEFAULT 0,blocked_until TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$property = new ReflectionProperty(DB::class, 'pdo');
$property->setValue(null, $pdo);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$count = 0;
function check(bool $ok, string $name): void
{
    global $count;
    if (!$ok)
        throw new RuntimeException('Falhou: ' . $name);
    echo 'OK ' . $name . PHP_EOL;
    $count++;
}
function rejects(callable $fn, string $name): void
{
    $rejected = false;
    try {
        $fn();
    } catch (DomainException | PDOException $e) {
        $rejected = true;
    }
    check($rejected, $name);
}
$data = ['name' => 'Usuário Teste', 'email' => 'pessoa@example.test', 'password' => 'Senha-Teste-2026!', 'password_confirmation' => 'Senha-Teste-2026!', 'privacy' => '1', 'role' => 'motorista'];
try {
    check(!Accounts::hasAdmin(), 'Banco inicialmente sem administrador');
    $uid = Accounts::register($data);
    $row = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
    check($row['role'] === 'motorista' && (int) $row['active'] === 1, 'Cadastro público ativo e sem privilégios administrativos');
    check(password_verify($data['password'], $row['password_hash']), 'Senha registrada como hash verificável');
    rejects(fn() => Accounts::register(array_replace($data, ['email' => 'outro@example.test', 'role' => 'admin'])), 'Recusa admin forjado no POST');
    rejects(fn() => Accounts::register(array_replace($data, ['role' => 'root'])), 'Recusa função não reconhecida');
    $missing = $data;
    unset($missing['role']);
    rejects(fn() => Accounts::register($missing), 'Exige escolha explícita do perfil');
    foreach (['gestor', 'despachante'] as $role) {
        $id = Accounts::register(array_replace($data, ['email' => $role . '@example.test', 'role' => $role]));
        check(DB::one('SELECT role FROM users WHERE id=?', [$id])['role'] === $role, 'Cadastro público salva perfil ' . $role);
    }
    rejects(fn() => Accounts::register(array_replace($data, ['password_confirmation' => 'diferente'])), 'Recusa confirmação de senha divergente');
    rejects(fn() => Accounts::register(array_replace($data, ['privacy' => '0'])), 'Exige ciência de privacidade');
    rejects(fn() => Accounts::register($data), 'Não duplica e-mail');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
    check(!Accounts::isLocal(), 'Setup indisponível fora do computador do servidor');
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $admin = Accounts::createAdmin(array_replace($data, ['email' => 'admin@example.test']));
    check(Accounts::hasAdmin() && $admin !== $uid, 'Configura o administrador separadamente do cadastro público');
    rejects(fn() => Accounts::createAdmin(array_replace($data, ['email' => 'segundo@example.test'])), 'Serviço bloqueia segundo administrador');
    rejects(fn() => DB::run("INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES('Segundo','segundo@example.test','hash','admin',CURRENT_TIMESTAMP)"), 'Restrição gerada/UNIQUE bloqueia admin adicional diretamente no banco de teste');
    check(!Auth::login($data['email'], $data['password'], 'admin'), 'Recusa login com perfil diferente mesmo com senha correta');
    check(Auth::user() === null, 'Perfil escolhido não concede sessão administrativa');
    foreach (['motorista' => $data['email'], 'gestor' => 'gestor@example.test', 'despachante' => 'despachante@example.test', 'admin' => 'admin@example.test'] as $role => $email) {
        check(Auth::login($email, $data['password'], $role), 'Autentica o perfil ' . $role);
        check(Auth::user()['role'] === $role, 'Sessão mantém somente perfil real ' . $role);
        Auth::logout();
    }
    check(Auth::landingPage('motorista') === 'driver' && Auth::landingPage('despachante') === 'deliveries' && Auth::landingPage('gestor') === 'dashboard', 'Direciona cada função para sua área');
    check((int) DB::one('SELECT COUNT(*) n FROM users')['n'] === 4, 'Erros não deixam usuários parciais');
    $seed = file_get_contents(__DIR__ . '/../database/contas_teste.sql');
    $seed = preg_replace('/USE `senn_aix`;/', '', $seed);
    $seed = str_replace(['START TRANSACTION;', 'NOW()'], ['BEGIN TRANSACTION;', 'CURRENT_TIMESTAMP'], $seed);
    $beforeAdmin = DB::one('SELECT * FROM users WHERE id=?', [$admin]);
    $beforeGestor = DB::one("SELECT * FROM users WHERE email='gestor@example.test'");
    $pdo->exec($seed);
    $seedCount = (int) DB::one('SELECT COUNT(*) n FROM users')['n'];
    check(password_verify('Demo-Senn-2026!', DB::one("SELECT password_hash FROM users WHERE email='motorista@example.test'")['password_hash']), 'Senha documentada das novas contas de teste confere com o hash SQL');
    $pdo->exec($seed);
    check((int) DB::one('SELECT COUNT(*) n FROM users')['n'] === $seedCount, 'Importação repetida das contas de teste não duplica usuários');
    check(DB::one('SELECT * FROM users WHERE id=?', [$admin]) === $beforeAdmin, 'SQL de testes preserva o administrador existente');
    check(DB::one("SELECT * FROM users WHERE email='gestor@example.test'") === $beforeGestor, 'SQL de testes preserva senha e perfil de conta existente');
    echo "$count verificações de contas aprovadas.\n";
} finally {
    session_destroy();
    ob_end_flush();
}
