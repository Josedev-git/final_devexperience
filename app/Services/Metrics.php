<?php
declare(strict_types=1);
namespace Services;
use Core\{DB, Auth};
final class Metrics
{
    public static function scope(string $alias = 'd'): array
    {
        $u = Auth::require();
        if ($u['role'] !== 'motorista')
            return ['1=1', []];
        return ["EXISTS(SELECT 1 FROM route_stops ss JOIN routes rr ON rr.id=ss.route_id JOIN drivers dr ON dr.id=rr.driver_id WHERE ss.delivery_id=$alias.id AND dr.user_id=?)", [$u['id']]];
    }
    public static function dashboard(string $from, string $to): array
    {
        [$scope, $params] = self::scope();
        $where = "d.created_at>=? AND d.created_at<? AND $scope";
        $args = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00', ...$params];
        $summary = DB::one("SELECT COUNT(*) total,COALESCE(SUM(d.reference LIKE 'PED-SIM-%'),0) demo_count,COALESCE(SUM(d.status='entregue'),0) done,COALESCE(SUM(d.status='entregue' AND d.delivered_at<=d.window_end),0) on_time FROM deliveries d WHERE $where", $args);
        $byStatus = DB::all("SELECT d.status label,COUNT(*) value FROM deliveries d WHERE $where GROUP BY d.status", $args);
        $byRegion = DB::all("SELECT d.region label,COUNT(*) value FROM deliveries d WHERE $where GROUP BY d.region ORDER BY value DESC LIMIT 20", $args);
        $u = Auth::require();
        $rs = $u['role'] === 'motorista' ? ' AND dr.user_id=?' : '';
        $rargs = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        if ($rs)
            $rargs[] = $u['id'];
        // Custo estimado alocado por parada no mesmo recorte de entregas do denominador.
        $allocation = DB::one("SELECT COALESCE(SUM(r.estimated_cost/(SELECT COUNT(*) FROM route_stops x WHERE x.route_id=r.id)),0) allocated_cost,COUNT(*) allocated_count FROM deliveries d JOIN route_stops s ON s.delivery_id=d.id JOIN routes r ON r.id=s.route_id WHERE $where", $args);
        $travel = DB::one("SELECT COALESCE(SUM(r.actual_km),0) km,COALESCE(SUM(CASE WHEN r.algorithm IN ('demo-history-v1','demo-simulation-v1') THEN r.actual_km ELSE 0 END),0) synthetic_km FROM routes r JOIN drivers dr ON dr.id=r.driver_id WHERE r.departure_at>=? AND r.departure_at<? $rs", $rargs);
        $fleet = DB::one("SELECT COUNT(*) total,COALESCE(SUM(status='em_rota'),0) busy FROM vehicles");
        return [
            'total' => (int) $summary['total'],
            'demo_count'=>(int)$summary['demo_count'],
            'synthetic_km'=>(float)$travel['synthetic_km'],
            'done' => (int) $summary['done'],
            'otd' => $summary['done'] ? round(100 * $summary['on_time'] / $summary['done'], 1) : null,
            'cost' => $allocation['allocated_count'] ? round((float) $allocation['allocated_cost'] / $allocation['allocated_count'], 2) : null,
            'km' => (float) $travel['km'],
            'occupancy' => $u['role'] === 'motorista' ? null : ($fleet['total'] ? round(100 * $fleet['busy'] / $fleet['total'], 1) : 0),
            'statuses' => $byStatus,
            'regions' => $byRegion
        ];
    }

    public static function regional(string $from,string $to):array {
        [$scope,$params]=self::scope();
        return DB::all("SELECT d.region,COUNT(*) deliveries,COALESCE(SUM(d.status='entregue'),0) done,COALESCE(SUM(d.status='falha'),0) failed,COALESCE(SUM(d.status='entregue' AND d.delivered_at<=d.window_end),0) on_time,COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN r.estimated_cost/(SELECT COUNT(*) FROM route_stops x WHERE x.route_id=r.id) ELSE 0 END),0) estimated_cost,COALESCE(SUM(r.id IS NOT NULL),0) routed FROM deliveries d LEFT JOIN route_stops s ON s.delivery_id=d.id LEFT JOIN routes r ON r.id=s.route_id WHERE d.created_at>=? AND d.created_at<? AND $scope GROUP BY d.region ORDER BY estimated_cost DESC LIMIT 30",[$from.' 00:00:00',date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00',...$params]);
    }

    public static function periods(array $query): array
    {
        $from = \dateInput(['date' => $query['from'] ?? date('Y-m-01')], 'date', true);
        $to = \dateInput(['date' => $query['to'] ?? date('Y-m-d')], 'date', true);
        if ($to < $from || strtotime($to) - strtotime($from) > 366 * 86400)
            throw new \DomainException('Selecione até 366 dias.');
        return [$from, $to];
    }
}
