<?php
declare(strict_types=1);
namespace Controllers;
use Core\{DB, Auth};
use Services\RoutePlanner;
final class DriverController
{
    private function lockedRoute(int $id, int $driver): array
    {
        $r = DB::one('SELECT * FROM routes WHERE id=? AND driver_id=? FOR UPDATE', [$id, $driver]);
        if (!$r)
            throw new \DomainException('Rota não encontrada no seu acesso.');
        return $r;
    }
    public function index(): void
    {
        $user = Auth::require(['motorista']);
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !DB::one('SELECT id FROM drivers WHERE user_id=?', [$user['id']])) {
            \view('driver', ['title' => 'Minhas entregas', 'routes' => []]);
            return;
        }
        $driver = Auth::driverId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = \choice($_POST, 'action', ['start', 'pod', 'fail', 'finish']);
            $route = (int) \number($_POST, 'route_id', 1, PHP_INT_MAX);
            // Serializa mutações de motorista e aplica limite ao código POD.
            if ($action === 'pod' && time() < ($_SESSION['pod_blocked_until'] ?? 0))
                throw new \DomainException('Aguarde 60 segundos antes de tentar outro código.');
            try {
                DB::transaction(function () use ($driver, $route, $action) {
                    $r = $this->lockedRoute($route, $driver);
                    if ($action === 'start') {
                        if ($r['status'] !== 'planejada')
                            throw new \DomainException('Rota não está planejada.');
                        $valid = DB::one('SELECT d.cnh_expires,u.active,v.maintenance_date FROM drivers d JOIN users u ON u.id=d.user_id JOIN vehicles v ON v.id=d.vehicle_id WHERE d.id=?', [$driver]);
                        if (!$valid || !$valid['active'] || $valid['cnh_expires'] < date('Y-m-d') || $valid['maintenance_date'] <= date('Y-m-d'))
                            throw new \DomainException('CNH ou manutenção impede início.');
                        DB::run("UPDATE routes SET status='em_andamento' WHERE id=?", [$route]);
                        DB::run("UPDATE deliveries d JOIN route_stops s ON s.delivery_id=d.id SET d.status='a_caminho' WHERE s.route_id=? AND d.status='planejada'", [$route]);
                    } elseif ($action === 'finish') {
                        if ($r['status'] !== 'em_andamento')
                            throw new \DomainException('Inicie a rota antes de concluir.');
                        $open = DB::one("SELECT COUNT(*) n FROM route_stops s JOIN deliveries d ON d.id=s.delivery_id WHERE s.route_id=? AND d.status NOT IN ('entregue','falha')", [$route]);
                        if ($open['n'])
                            throw new \DomainException('Finalize as entregas antes de concluir a rota.');
                        DB::run("UPDATE routes SET status='concluida',completed_at=NOW() WHERE id=?", [$route]);
                        DB::run("UPDATE vehicles SET status='disponivel' WHERE id=?", [$r['vehicle_id']]);
                        DB::run('UPDATE drivers SET available=1 WHERE id=?', [$driver]);
                    } else {
                        if ($r['status'] !== 'em_andamento')
                            throw new \DomainException('Inicie a rota primeiro.');
                        $did = (int) \number($_POST, 'delivery_id', 1, PHP_INT_MAX);
                        $d = DB::one('SELECT d.* FROM deliveries d JOIN route_stops s ON s.delivery_id=d.id WHERE d.id=? AND s.route_id=? FOR UPDATE', [$did, $route]);
                        if (!$d || $d['status'] !== 'a_caminho')
                            throw new \DomainException('Entrega indisponível para esta ação.');
                        if ($action === 'fail') {
                            DB::run("UPDATE deliveries SET status='falha' WHERE id=?", [$did]);
                            \audit('delivery_failed', 'delivery', $did);
                        } else {
                            $code = \text($_POST, 'confirmation_code', 6);
                            if (!preg_match('/^[0-9]{6}$/', $code) || !$d['confirmation_hash'] || !password_verify($code, $d['confirmation_hash']))
                                throw new \DomainException('Código de confirmação inválido.');
                            $lat = \number($_POST, 'lat', -90, 90);
                            $lng = \number($_POST, 'lng', -180, 180);
                            DB::run('INSERT INTO delivery_proofs(delivery_id,driver_id,lat,lng) VALUES(?,?,?,?)', [$did, $driver, $lat, $lng]);
                            DB::run("UPDATE deliveries SET status='entregue',delivered_at=NOW(),confirmation_hash=NULL WHERE id=?", [$did]);
                            \audit('pod', 'delivery', $did);
                        }
                    }
                    \audit($action, 'route', $route);
                });
            } catch (\DomainException $e) {
                if ($action === 'pod' && $e->getMessage() === 'Código de confirmação inválido.') {
                    $_SESSION['pod_attempts'] = ($_SESSION['pod_attempts'] ?? 0) + 1;
                    if ($_SESSION['pod_attempts'] >= 5) {
                        $_SESSION['pod_blocked_until'] = time() + 60;
                        $_SESSION['pod_attempts'] = 0;
                    }
                }
                throw $e;
            }
            \flash('Operação registrada.');
            \redirect('driver');
        }
        $routes = DB::all("SELECT r.*,v.plate FROM routes r JOIN vehicles v ON v.id=r.vehicle_id WHERE r.driver_id=? AND r.status<>'concluida' ORDER BY r.departure_at", [$driver]);
        foreach ($routes as &$r)
            $r['stops'] = DB::all('SELECT d.id,d.reference,d.address,d.window_start,d.window_end,d.status,s.sequence_no,s.eta FROM route_stops s JOIN deliveries d ON d.id=s.delivery_id WHERE s.route_id=? ORDER BY s.sequence_no', [$r['id']]);
        unset($r);
        \view('driver', ['title' => 'Minhas entregas', 'routes' => $routes]);
    }
    public function position(): never
    {
        $driver = Auth::driverId();
        $route = (int) \number($_POST, 'route_id', 1, PHP_INT_MAX);
        $lat = \number($_POST, 'lat', -90, 90);
        $lng = \number($_POST, 'lng', -180, 180);
        DB::transaction(function () use ($driver, $route, $lat, $lng) {
            $r = $this->lockedRoute($route, $driver);
            if ($r['status'] !== 'em_andamento')
                throw new \DomainException('A rota não está em andamento.');
            $last = DB::one('SELECT * FROM positions WHERE route_id=? ORDER BY id DESC LIMIT 1', [$route]);
            $km = 0;
            if ($last) {
                $seconds = time() - strtotime($last['recorded_at']);
                if ($seconds < 5)
                    throw new \DomainException('Aguarde 5 segundos entre posições.');
                $km = RoutePlanner::distance((float) $last['lat'], (float) $last['lng'], $lat, $lng);
                if ($km / max(1, $seconds) * 3600 > 160)
                    throw new \DomainException('Salto GPS incompatível; posição não registrada.');
            }
            DB::run('INSERT INTO positions(route_id,user_id,lat,lng) VALUES(?,?,?,?)', [$route, Auth::require()['id'], $lat, $lng]);
            DB::run('UPDATE routes SET actual_km=actual_km+? WHERE id=?', [$km, $route]);
        });
        \jsonResponse(['ok' => true, 'server_time' => date(DATE_ATOM)]);
    }
}
