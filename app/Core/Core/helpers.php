<?php
declare(strict_types=1);
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path = 'dashboard'): string
{
    return env('APP_BASE_PATH') . '/index.php?page=' . rawurlencode($path);
}
function asset(string $path): string
{
    return env('APP_BASE_PATH') . '/assets/' . $path;
}
function redirect(string $page): never
{
    header('Location: ' . url($page));
    exit;
}
function csrf(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function checkCsrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token))
        abort(419, 'Sessão expirada. Recarregue a página.');
}
function abort(int $status, string $message): never
{
    http_response_code($status);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
        jsonResponse(['error' => $message], $status);
    echo e($message);
    exit;
}
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $value = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $value;
}
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require ROOT . '/views/partials/header.php';
    require ROOT . '/views/' . $name . '.php';
    require ROOT . '/views/partials/footer.php';
}
function text(array $data, string $key, int $max = 190): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value))
        throw new DomainException('Campo inválido: ' . $key);
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $max)
        throw new DomainException('Preencha corretamente: ' . $key);
    return $value;
}
function number(array $data, string $key, float $min, float $max): float
{
    $v = filter_var($data[$key] ?? null, FILTER_VALIDATE_FLOAT);
    if ($v === false || $v === null || !is_finite((float) $v) || $v < $min || $v > $max)
        throw new DomainException('Número inválido: ' . $key);
    return (float) $v;
}
function choice(array $data, string $key, array $allowed): string
{
    $v = text($data, $key);
    if (!in_array($v, $allowed, true))
        throw new DomainException('Opção inválida: ' . $key);
    return $v;
}
function dateInput(array $data, string $key, bool $dateOnly = false): string
{
    $v = text($data, $key, 30);
    $format = $dateOnly ? 'Y-m-d' : 'Y-m-d\TH:i';
    $dt = DateTimeImmutable::createFromFormat('!' . $format, $v);
    if (!$dt || $dt->format($format) !== $v)
        throw new DomainException('Data inválida: ' . $key);
    return $dt->format($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s');
}
function audit(string $action, string $type, ?int $id = null): void
{
    Core\DB::run('INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,?,?,?)', [Core\Auth::user()['id'] ?? null, $action, $type, $id]);
}
function money(float $n): string
{
    return 'R$ ' . number_format($n, 2, ',', '.');
}

function passwordInput(array $data, int $max = 200): string
{
    $value = $data['password'] ?? null;
    if (!is_string($value) || $value === '' || strlen($value) > $max)
        throw new DomainException('Senha inválida.');
    return $value; // Não normaliza nem remove espaços de uma senha.
}
