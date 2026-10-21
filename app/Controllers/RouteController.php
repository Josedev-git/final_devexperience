<?php
declare(strict_types=1);
namespace Controllers;
use Core\{DB, Auth};
use Services\RoutePlanner;
final class RouteController
{
    public function index(): void
    {
        Auth::require(['admin', 'gestor', 'despachante']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = DB::transaction(function () {
                $vehicleId = (int) \number($_POST, 'vehicle_id', 1, PHP_INT_MAX);
                $driverId = (int) \number($_POST, 'driver_id', 1, PHP_INT_MAX);
                $vehicle = DB::one('SELECT * FROM vehicles WHERE id=? FOR UPDATE', [$vehicleId]);
                $driver = DB::one('SELECT d.*,u.active FROM drivers d JOIN users u ON u.id=d.user_id WHERE d.id=? FOR UPDATE', [$driverId]);
                if (!$vehicle || !$driver || !$driver['active'] || !$driver['available'] || $vehicle['status'] !== 'disponivel' || (int) $driver['vehicle_id'] !== $vehicleId)
                    throw new \DomainException('Veículo/motorista indisponível ou vínculo incompatível.');
                // A compatibilidade legal detalhada de CNH depende do PBT/tipo: rejeita categorias A/AB? AB permite B, logo apenas A é vedada.
                if ($driver['cnh_category'] === 'A')
                    throw new \DomainException('CNH A não autoriza veículo de carga deste protótipo.');
                $departure = \dateInput($_POST, 'departure_at');
                if (strtotime($departure) < time() - 60)
                    throw new \DomainException('Saída não pode estar no passado.');
                if ($driver['cnh_expires'] < substr($departure, 0, 10) || $vehicle['maintenance_date'] <= substr($departure, 0, 10))
                    throw new \DomainException('CNH vencida ou manutenção devida na data da rota.');
                if (DB::one("SELECT id FROM routes WHERE (vehicle_id=? OR driver_id=?) AND status<>'concluida'", [$vehicleId, $driverId]))
                    throw new \DomainException('Veículo ou motorista já reservado em outra rota.');
                $raw = $_POST['delivery_ids'] ?? [];
                if (!is_array($raw) || !$raw || count($raw) > 100)
                    throw new \DomainException('Selecione entre 1 e 100 pedidos.');
                $ids = [];
                foreach ($raw as $value) {
                    if (!ctype_digit((string) $value) || (int) $value < 1)
                        throw new \DomainException('Pedido inválido.');
                    $ids[] = (int) $value;
                }
                $ids = array_values(array_unique($ids));
                sort($ids);
                $jobs = DB::all('SELECT * FROM deliveries WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id FOR UPDATE', $ids);
                if (count($jobs) !== count($ids))
                    throw new \DomainException('Pedido não encontrado.');
                foreach ($jobs as $j)
                    if ($j['status'] !== 'pendente')
                        throw new \DomainException('Há pedido já atribuído. Atualize a seleção.');
                $lat = \number(['lat' => env('DEPOT_LAT', '-23.0264')], 'lat', -90, 90);
                $lng = \number(['lng' => env('DEPOT_LNG', '-45.5553')], 'lng', -180, 180);
                $plan = (new RoutePlanner)->plan($jobs, $vehicle, $lat, $lng, strtotime($departure));
                DB::run('INSERT INTO routes(vehicle_id,driver_id,created_by,departure_at,estimated_km,estimated_cost,cost_per_km,origin_lat,origin_lng) VALUES(?,?,?,?,?,?,?,?,?)', [$vehicleId, $driverId, Auth::require()['id'], $departure, $plan['km'], $plan['cost'], $vehicle['cost_per_km'], $lat, $lng]);
                $id = (int) DB::connection()->lastInsertId();
                foreach ($plan['stops'] as $s) {
                    DB::run('INSERT INTO route_stops(route_id,delivery_id,sequence_no,eta,leg_km) VALUES(?,?,?,?,?)', [$id, $s['delivery_id'], $s['sequence_no'], $s['eta'], $s['leg_km']]);
                    DB::run("UPDATE deliveries SET status='planejada' WHERE id=?", [$s['delivery_id']]);
                }
                DB::run("UPDATE vehicles SET status='em_rota' WHERE id=?", [$vehicleId]); // reservado desde o planejamento
                DB::run('UPDATE drivers SET available=0 WHERE id=?', [$driverId]);
                \audit('plan', 'route', $id);
                return $id;
            });
            \flash('Rota #' . $id . ' gerada e liberada para o motorista.');
            \redirect('routes');
        }
        \view('routes', [
            'title' => 'Planejamento de rotas',
            'vehicles' => DB::all("SELECT * FROM vehicles WHERE status='disponivel'"),
            'drivers' => DB::all('SELECT d.id,d.vehicle_id,u.name FROM drivers d JOIN users u ON u.id=d.user_id WHERE d.available=1 AND u.active=1'),
            'jobs' => DB::all("SELECT * FROM deliveries WHERE status='pendente' ORDER BY window_end LIMIT 100"),
            'routes' => DB::all('SELECT r.id,r.status,r.departure_at,r.estimated_km,r.actual_km,r.estimated_cost,v.plate,u.name FROM routes r JOIN vehicles v ON v.id=r.vehicle_id JOIN drivers d ON d.id=r.driver_id JOIN users u ON u.id=d.user_id ORDER BY r.id DESC LIMIT 100')
        ]);
    }
}
