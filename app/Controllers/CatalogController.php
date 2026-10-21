<?php
declare(strict_types=1);
namespace Controllers;
use Core\{DB, Auth};
final class CatalogController
{
    public function users(): void
    {
        Auth::require(['admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'role') {
                $id = (int) \number($_POST, 'id', 1, PHP_INT_MAX);
                $role = \choice($_POST, 'role', ['gestor', 'despachante', 'motorista']);
                DB::transaction(function () use ($id, $role) {
                    $target = DB::one('SELECT id,role FROM users WHERE id=? FOR UPDATE', [$id]);
                    if (!$target || $target['role'] === 'admin')
                        throw new \DomainException('O administrador único não pode ser alterado por este formulário.');
                    if (DB::one("SELECT r.id FROM routes r JOIN drivers d ON d.id=r.driver_id WHERE d.user_id=? AND r.status<>'concluida'", [$id]))
                        throw new \DomainException('Conclua as rotas abertas antes de alterar o perfil.');
                    DB::run('UPDATE users SET role=? WHERE id=?', [$role, $id]);
                    \audit('change_role', 'user', $id);
                });
                \flash('Perfil atualizado.');
                \redirect('users');
            }
            $name = \text($_POST, 'name', 120);
            $email = \text($_POST, 'email', 190);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new \DomainException('E-mail inválido.');
            $password = \passwordInput($_POST, 72);
            if (strlen($password) < 12 || strlen($password) > 72)
                throw new \DomainException('Use senha com 12 a 72 bytes.');
            if (($_POST['privacy'] ?? '') !== '1')
                throw new \DomainException('Confirme a ciência do aviso de privacidade.');
            $role = \choice($_POST, 'role', ['gestor', 'despachante', 'motorista']);
            DB::transaction(function () use ($name, $email, $password, $role) {
                DB::run('INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES(?,?,?,?,NOW())', [$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]);
                \audit('create', 'user', (int) DB::connection()->lastInsertId());
            });
            \flash('Usuário cadastrado.');
            \redirect('users');
        }
        \view('catalog', ['title' => 'Usuários', 'kind' => 'users', 'rows' => DB::all('SELECT id,name,email,role,active FROM users ORDER BY id DESC LIMIT 100')]);
    }
    public function vehicles(): void
    {
        Auth::require(['admin', 'gestor']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'maintenance') {
                $id = (int) \number($_POST, 'id', 1, PHP_INT_MAX);
                DB::transaction(function () use ($id) {
                    $v = DB::one('SELECT * FROM vehicles WHERE id=? FOR UPDATE', [$id]);
                    if (!$v || $v['status'] === 'em_rota' || DB::one("SELECT id FROM routes WHERE vehicle_id=? AND status<>'concluida'", [$id]))
                        throw new \DomainException('Veículo reservado ou em rota.');
                    DB::run('UPDATE vehicles SET status=?,maintenance_date=? WHERE id=?', [\choice($_POST, 'status', ['disponivel', 'manutencao']), \dateInput($_POST, 'maintenance_date', true), $id]);
                    \audit('maintenance', 'vehicle', $id);
                });
            } else {
                $plate = strtoupper(str_replace('-', '', \text($_POST, 'plate', 8)));
                if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plate))
                    throw new \DomainException('Placa brasileira inválida.');
                DB::transaction(function () use ($plate) {
                    DB::run('INSERT INTO vehicles(plate,type,capacity_kg,capacity_m3,autonomy_km,status,maintenance_date,cost_per_km) VALUES(?,?,?,?,?,?,?,?)', [$plate, \text($_POST, 'type', 60), \number($_POST, 'capacity_kg', 0.01, 100000), \number($_POST, 'capacity_m3', 0.001, 1000), \number($_POST, 'autonomy_km', 1, 3000), \choice($_POST, 'status', ['disponivel', 'manutencao']), \dateInput($_POST, 'maintenance_date', true), \number($_POST, 'cost_per_km', 0, 100)]);
                    \audit('create', 'vehicle', (int) DB::connection()->lastInsertId());
                });
            }
            \flash('Frota atualizada.');
            \redirect('vehicles');
        }
        \view('catalog', ['title' => 'Frota', 'kind' => 'vehicles', 'rows' => DB::all('SELECT * FROM vehicles ORDER BY id DESC LIMIT 100')]);
    }
    public function drivers(): void
    {
        Auth::require(['admin', 'gestor']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cnh = \text($_POST, 'cnh', 11);
            if (!preg_match('/^[0-9]{11}$/', $cnh))
                throw new \DomainException('CNH deve ter 11 dígitos.');
            DB::transaction(function () use ($cnh) {
                $uid = (int) \number($_POST, 'user_id', 1, PHP_INT_MAX);
                $u = DB::one('SELECT id FROM users WHERE id=? AND role=\'motorista\' AND active=1 FOR UPDATE', [$uid]);
                if (!$u)
                    throw new \DomainException('Selecione usuário motorista ativo.');
                $v = (int) \number($_POST, 'vehicle_id', 1, PHP_INT_MAX);
                if (!DB::one('SELECT id FROM vehicles WHERE id=? FOR UPDATE', [$v]))
                    throw new \DomainException('Veículo não encontrado.');
                DB::run('INSERT INTO drivers(user_id,cnh,cnh_category,cnh_expires,phone,vehicle_id) VALUES(?,?,?,?,?,?)', [$uid, $cnh, \choice($_POST, 'cnh_category', ['A', 'B', 'AB', 'C', 'D', 'E', 'AC', 'AD', 'AE']), \dateInput($_POST, 'cnh_expires', true), \text($_POST, 'phone', 24), $v]);
                \audit('create', 'driver', (int) DB::connection()->lastInsertId());
            });
            \flash('Motorista vinculado.');
            \redirect('drivers');
        }
        \view('catalog', [
            'title' => 'Motoristas',
            'kind' => 'drivers',
            'rows' => DB::all('SELECT d.id,u.name,d.cnh_category,d.cnh_expires,d.available,v.plate FROM drivers d JOIN users u ON u.id=d.user_id LEFT JOIN vehicles v ON v.id=d.vehicle_id ORDER BY d.id DESC LIMIT 100'),
            'users' => DB::all("SELECT id,name FROM users WHERE role='motorista' AND active=1 AND id NOT IN (SELECT user_id FROM drivers)"),
            'vehicles' => DB::all('SELECT id,plate FROM vehicles WHERE id NOT IN (SELECT vehicle_id FROM drivers WHERE vehicle_id IS NOT NULL)')
        ]);
    }
}
