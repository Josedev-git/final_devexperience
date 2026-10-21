<?php
declare(strict_types=1);
namespace Core;
final class Auth
{
    /** Chave persistente automática; APP_SECRET passa a ser uma opção avançada. */
    private static function secretKey(): string
    {
        $configured = env('APP_SECRET');
        if (strlen($configured) >= 32 && !str_starts_with($configured, 'SUBSTITUA'))
            return $configured;
        $directory = ROOT . '/storage';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \DomainException('Não foi possível preparar storage. Verifique a permissão de escrita da pasta.');
        }
        $file = $directory . '/app.key';
        $handle = fopen($file, 'c+');
        if ($handle === false)
            throw new \DomainException('Não foi possível gerar a chave automática. Permita escrita em storage.');
        try {
            if (!flock($handle, LOCK_EX))
                throw new \DomainException('Não foi possível acessar a chave automática. Tente novamente.');
            chmod($file, 0600);
            rewind($handle);
            $key = trim(stream_get_contents($handle));
            if ($key === '') {
                $key = bin2hex(random_bytes(32));
                rewind($handle);
                if (fwrite($handle, $key) !== 64 || !fflush($handle))
                    throw new \DomainException('Não foi possível salvar a chave automática em storage.');
            }
            if (!preg_match('/^[a-f0-9]{64}$/D', $key))
                throw new \DomainException('A chave em storage/app.key está inválida. Contate o responsável técnico.');
            return $key;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id']))
            return null;
        if (time() - ($_SESSION['last_activity'] ?? 0) > 1800) {
            self::logout();
            return null;
        }
        $_SESSION['last_activity'] = time();
        return DB::one('SELECT id,name,email,role FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]);
    }
    public static function require(array $roles = []): array
    {
        $user = self::user();
        if (!$user) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
                \abort(401, 'Faça login novamente.');
            \redirect('login');
        }
        if ($roles && !in_array($user['role'], $roles, true))
            \abort(403, 'Acesso não autorizado.');
        return $user;
    }
    public static function keyHash(string $value): string
    {
        return hash_hmac('sha256', $value, self::secretKey());
    }
    public static function login(string $email, string $password, string $role): bool
    {
        if (!in_array($role, ['admin', 'gestor', 'despachante', 'motorista'], true))
            return false;
        $secret = self::secretKey();
        // Limites independentes por conta e IP; não se contornam apenas mudando o e-mail.
        $keys = [hash_hmac('sha256', 'email:' . mb_strtolower($email), $secret), hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), $secret)];
        return DB::transaction(function () use ($keys, $email, $password, $role) {
            sort($keys);
            foreach ($keys as $key) {
                DB::run('INSERT IGNORE INTO login_attempts(key_hash) VALUES(?)', [$key]);
                $attempt = DB::one('SELECT * FROM login_attempts WHERE key_hash=? FOR UPDATE', [$key]);
                if ($attempt['blocked_until'] && strtotime($attempt['blocked_until']) > time())
                    return false;
                if (strtotime($attempt['updated_at']) < time() - 900)
                    DB::run('UPDATE login_attempts SET failures=0 WHERE key_hash=?', [$key]);
            }
            $u = DB::one('SELECT * FROM users WHERE email=? AND active=1', [$email]);
            $hash = $u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid = password_verify($password, $hash);
            if (!$u || !$valid || $role !== $u['role']) {
                foreach ($keys as $key)
                    DB::run('UPDATE login_attempts SET failures=failures+1,blocked_until=IF(failures>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL),updated_at=NOW() WHERE key_hash=?', [$key]);
                return false;
            }
            foreach ($keys as $key)
                DB::run('DELETE FROM login_attempts WHERE key_hash=?', [$key]);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT))
                DB::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            $_SESSION = ['user_id' => (int) $u['id'], 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
            \audit('login', 'user', (int) $u['id']);
            return true;
        });
    }
    public static function landingPage(string $role): string
    {
        return match ($role) { 'despachante' => 'deliveries', 'motorista' => 'driver', default => 'dashboard'};
    }
    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE)
            session_regenerate_id(true);
    }
    public static function driverId(): int
    {
        $u = self::require(['motorista']);
        $d = DB::one('SELECT id FROM drivers WHERE user_id=?', [$u['id']]);
        if (!$d)
            \abort(403, 'Seu usuário ainda não tem cadastro de motorista.');
        return (int) $d['id'];
    }
}
