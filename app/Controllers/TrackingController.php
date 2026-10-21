<?php
declare(strict_types=1);
namespace Controllers;
use Core\{DB, Auth};
final class TrackingController
{
    public function data(): array
    {
        $u = Auth::require();
        $where = ['1=1'];
        $args = [];
        if ($u['role'] === 'motorista') {
            $where[] = 'dr.user_id=?';
            $args[] = $u['id'];
        }
        foreach (['route' => 'r.id', 'driver' => 'dr.id', 'region' => 'd.region'] as $key => $column) {
            $v = $_GET[$key] ?? '';
            if (!is_string($v))
                throw new \DomainException('Filtro inválido.');
            if ($v !== '') {
                if (strlen($v) > 80)
                    throw new \DomainException('Filtro muito longo.');
                $where[] = "$column=?";
                $args[] = $v;
            }
        }
        $rows = DB::all('SELECT r.id route_id,r.status route_status,r.algorithm,v.plate,dr.id driver_id,d.reference,d.status,d.region,d.lat,d.lng,s.sequence_no FROM routes r JOIN vehicles v ON v.id=r.vehicle_id JOIN drivers dr ON dr.id=r.driver_id JOIN route_stops s ON s.route_id=r.id JOIN deliveries d ON d.id=s.delivery_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC,s.sequence_no LIMIT 500', $args);
        $ids = array_values(array_unique(array_column($rows, 'route_id')));
        $positions = [];
        foreach ($ids as $id) {
            $p = DB::one('SELECT p.route_id,p.lat,p.lng,p.recorded_at,r.algorithm FROM positions p JOIN routes r ON r.id=p.route_id WHERE p.route_id=? ORDER BY p.id DESC LIMIT 1', [$id]);
            if ($p) {
                $p['stale'] = strtotime($p['recorded_at']) < time() - 120;
                $positions[] = $p;
            }
        }
        return ['deliveries' => $rows, 'positions' => $positions, 'server_time' => date(DATE_ATOM), 'limit' => 500];
    }
    public function index(): void
    {
        Auth::require();
        \view('tracking', ['title' => 'Centro de rastreamento', 'data' => $this->data()]);
    }
}
