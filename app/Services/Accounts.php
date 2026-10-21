<?php
declare(strict_types=1);
namespace Services;
use Core\{Auth, DB};
final class Accounts
{
    public static function fields(array $data): array
    {
        $name = \text($data, 'name', 120);
        $email = mb_strtolower(\text($data, 'email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new \DomainException('Informe um e-mail válido.');
        $password = \passwordInput($data, 72);
        if (strlen($password) < 12)
            throw new \DomainException('A senha precisa ter de 12 a 72 bytes.');
        if (!isset($data['password_confirmation']) || !is_string($data['password_confirmation']) || $password !== $data['password_confirmation'])
            throw new \DomainException('As senhas não coincidem.');
        if (($data['privacy'] ?? '') !== '1')
            throw new \DomainException('Confirme a leitura do aviso de privacidade.');
        return [$name, $email, password_hash($password, PASSWORD_DEFAULT)];
    }
    public static function isLocal(): bool
    {
        return env('APP_ENV', 'local') === 'local' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }
    public static function hasAdmin(): bool
    {
        return DB::one("SELECT id FROM users WHERE role='admin' LIMIT 1") !== null;
    }
    public static function register(array $data): int
    {
        // Somente as três funções operacionais podem ser escolhidas no cadastro.
        $role = \choice($data, 'role', ['gestor', 'despachante', 'motorista']);
        self::quota();
        [$name, $email, $hash] = self::fields($data);
        return DB::transaction(function () use ($name, $email, $hash, $role) {
            DB::run('INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES(?,?,?,?,NOW())', [$name, $email, $hash, $role]);
            $id = (int) DB::connection()->lastInsertId();
            DB::run("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,'register','user',?)", [$id, $id]);
            return $id;
        });
    }
    public static function createAdmin(array $data): int
    {
        if (!self::isLocal())
            \abort(403, 'A configuração inicial só pode ser aberta no próprio computador do servidor.');
        [$name, $email, $hash] = self::fields($data);
        return DB::transaction(function () use ($name, $email, $hash) {
            if (self::hasAdmin())
                throw new \DomainException('O administrador já foi configurado. Use a tela de login.');
            // A chave UNIQUE do banco impede dois administradores mesmo em concorrência.
            DB::run("INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES(?,?,?,'admin',NOW())", [$name, $email, $hash]);
            $id = (int) DB::connection()->lastInsertId();
            DB::run("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,'setup_admin','user',?)", [$id, $id]);
            return $id;
        });
    }
    private static function quota(): void
    {
        $key = Auth::keyHash('signup-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $allowed = DB::transaction(function () use ($key) {
            DB::run('INSERT IGNORE INTO login_attempts(key_hash) VALUES(?)', [$key]);
            $row = DB::one('SELECT * FROM login_attempts WHERE key_hash=? FOR UPDATE', [$key]);
            if (strtotime($row['updated_at']) < time() - 900) {
                DB::run('UPDATE login_attempts SET failures=0,updated_at=NOW() WHERE key_hash=?', [$key]);
                $row['failures'] = 0;
            }
            if ((int) $row['failures'] >= 10)
                return false;
            // Cada tentativa conta, inclusive validação ou e-mail duplicado.
            DB::run('UPDATE login_attempts SET failures=failures+1 WHERE key_hash=?', [$key]);
            return true;
        });
        if (!$allowed)
            throw new \DomainException('Muitas tentativas de cadastro. Aguarde até 15 minutos.');
    }
}
