<?php
declare(strict_types=1);
namespace Controllers;
use Core\{DB, Auth};
final class DeliveryController
{
    public static function insert(array $input): array
    {
        $reference = strtoupper(\text($input, 'reference', 50));
        if (!preg_match('/^PED-[A-Z0-9-]{1,46}$/', $reference))
            throw new \DomainException('Referência deve começar com PED- e usar letras, números ou hífens.');
        $start = \dateInput($input, 'window_start');
        $end = \dateInput($input, 'window_end');
        if ($end <= $start)
            throw new \DomainException('Fim da janela deve ser após o início.');
        $code = (string) random_int(100000, 999999);
        DB::run('INSERT INTO deliveries(reference,recipient_name,address,region,lat,lng,window_start,window_end,weight_kg,volume_m3,priority,confirmation_hash,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $reference,
            \text($input, 'recipient_name', 120),
            \text($input, 'address', 255),
            \text($input, 'region', 80),
            \number($input, 'lat', -90, 90),
            \number($input, 'lng', -180, 180),
            $start,
            $end,
            \number($input, 'weight_kg', 0.01, 100000),
            \number($input, 'volume_m3', 0.001, 1000),
            \choice($input, 'priority', ['normal', 'alta', 'urgente']),
            password_hash($code, PASSWORD_DEFAULT),
            Auth::require()['id']
        ]);
        \audit('create', 'delivery', (int) DB::connection()->lastInsertId());
        return ['reference' => $reference, 'code' => $code];
    }
    public function index(): void
    {
        Auth::require(['admin', 'gestor', 'despachante']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $codes = [];
            DB::transaction(function () use (&$codes) {
                if (($_POST['action'] ?? '') === 'import') {
                    $file = $_FILES['csv'] ?? null;
                    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1024 * 1024 || !is_uploaded_file($file['tmp_name']))
                        throw new \DomainException('Envie um CSV válido de até 1 MB.');
                    $f = fopen($file['tmp_name'], 'r');
                    $headers = fgetcsv($f, 0, ',', '"', '');
                    $expected = ['reference', 'recipient_name', 'address', 'region', 'lat', 'lng', 'window_start', 'window_end', 'weight_kg', 'volume_m3', 'priority'];
                    if ($headers)
                        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                    if ($headers !== $expected) {
                        fclose($f);
                        throw new \DomainException('Cabeçalho inválido. Use database/deliveries-example.csv.');
                    }
                    $line = 1;
                    try {
                        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
                            $line++;
                            if ($row === [null])
                                continue;
                            if ($line > 201 || count($row) !== count($headers))
                                throw new \DomainException('Limite de 200 linhas ou quantidade de colunas inválida.');
                            try {
                                $codes[] = self::insert(array_combine($headers, $row));
                            } catch (\DomainException $e) {
                                throw new \DomainException('Linha ' . $line . ': ' . $e->getMessage());
                            }
                        }
                    } finally {
                        fclose($f);
                    }
                    if (!$codes)
                        throw new \DomainException('CSV sem entregas.');
                } else
                    $codes[] = self::insert($_POST);
            });
            $_SESSION['new_codes'] = $codes;
            \flash('Pedidos cadastrados. Guarde e envie os códigos aos destinatários; serão mostrados uma única vez.');
            \redirect('deliveries');
        }
        $page = max(1, min(100000, (int) ($_GET['p'] ?? 1)));
        $offset = ($page - 1) * 50;
        $rows = DB::all('SELECT id,reference,address,region,window_start,window_end,weight_kg,volume_m3,status FROM deliveries ORDER BY id DESC LIMIT 50 OFFSET ' . $offset);
        $codes = $_SESSION['new_codes'] ?? [];
        unset($_SESSION['new_codes']);
        \view('deliveries', ['title' => 'Pedidos & entregas', 'rows' => $rows, 'codes' => $codes, 'page' => $page]);
    }
}
