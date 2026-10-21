<?php
declare(strict_types=1);
namespace Services;
use Core\DB;
final class Alerts
{
    public function add(string $key, string $kind, string $message, ?int $route = null): void
    {
        DB::transaction(function () use ($key, $kind, $message, $route) {
            DB::run('INSERT IGNORE INTO alerts(dedup_key,kind,message,route_id) VALUES(?,?,?,?)', [$key, $kind, $message, $route]);
            $a = DB::one('SELECT id FROM alerts WHERE dedup_key=?', [$key]);
            DB::run("INSERT IGNORE INTO notification_logs(alert_id,channel,status) VALUES(?,'painel','enviado')", [$a['id']]);
            if (env('ALERT_EMAIL') !== '')
                DB::run("INSERT IGNORE INTO notification_logs(alert_id,channel,status) VALUES(?,'email','pendente')", [$a['id']]);
        });
    }
    public function scan(): int
    {
        foreach (DB::all("SELECT id FROM deliveries WHERE status IN ('pendente','planejada','a_caminho') AND window_end>NOW() AND window_end<=DATE_ADD(NOW(),INTERVAL 30 MINUTE)") as $d)
            $this->add('risk:' . $d['id'], 'risco', 'Entrega #' . $d['id'] . ' ainda aberta e janela termina em até 30 minutos. Priorizar.');
        foreach (DB::all("SELECT id FROM deliveries WHERE status IN ('pendente','planejada','a_caminho') AND window_end<NOW()") as $d)
            $this->add('late:' . $d['id'], 'atraso', 'Entrega #' . $d['id'] . ' com janela vencida.');
        foreach (DB::all('SELECT id FROM vehicles WHERE maintenance_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)') as $v)
            $this->add('maintenance:' . $v['id'] . ':' . date('Y-m'), 'manutencao', 'Veículo #' . $v['id'] . ' com manutenção em até 30 dias.');
        foreach (DB::all('SELECT id FROM drivers WHERE cnh_expires<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)') as $d)
            $this->add('cnh:' . $d['id'] . ':' . date('Y-m'), 'cnh', 'Motorista #' . $d['id'] . ' com CNH em até 30 dias.');
        foreach (DB::all("SELECT * FROM routes WHERE status='em_andamento'") as $r) {
            $points = DB::all('SELECT * FROM positions WHERE route_id=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 500', [$r['id']]);
            $last = $points[0] ?? null;
            if (!$last || strtotime($last['recorded_at']) < time() - 600) {
                $this->add('stale:' . $r['id'] . ':' . date('Y-m-d-H'), 'telemetria', 'Rota #' . $r['id'] . ' sem localização recente.', (int) $r['id']);
                continue;
            }
            if (StopDetector::stopped($points,time()))
                $this->add('stop:' . $r['id'] . ':' . date('Y-m-d-H'), 'parado', 'Rota #' . $r['id'] . ' parada por pelo menos 15 minutos.', (int) $r['id']);
            $stops = DB::all('SELECT d.lat,d.lng FROM route_stops s JOIN deliveries d ON d.id=s.delivery_id WHERE s.route_id=? ORDER BY s.sequence_no', [$r['id']]);
            $path = [['lat' => $r['origin_lat'], 'lng' => $r['origin_lng']], ...$stops, ['lat' => $r['origin_lat'], 'lng' => $r['origin_lng']]];
            $min = INF;
            for ($i = 1; $i < count($path); $i++)
                $min = min($min, self::segmentDistance($last, $path[$i - 1], $path[$i]));
            if ($min > 2)
                $this->add('deviation:' . $r['id'] . ':' . date('Y-m-d-H'), 'desvio', 'Rota #' . $r['id'] . ' a mais de 2 km do corredor aproximado. Confirmar antes de agir.', (int) $r['id']);
        }
        return (int) DB::one('SELECT COUNT(*) n FROM alerts WHERE resolved_at IS NULL')['n'];
    }
    private static function segmentDistance(array $p, array $a, array $b): float
    {
        // Projeção equiretangular local. Adequada apenas à área urbana / last-mile.
        $cos = cos(deg2rad((float) $p['lat']));
        $k = 111.195;
        $ax = ((float) $a['lng'] - (float) $p['lng']) * $cos * $k;
        $ay = ((float) $a['lat'] - (float) $p['lat']) * $k;
        $bx = ((float) $b['lng'] - (float) $p['lng']) * $cos * $k;
        $by = ((float) $b['lat'] - (float) $p['lat']) * $k;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $den = $dx * $dx + $dy * $dy;
        $t = $den > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $den)) : 0;
        return hypot($ax + $t * $dx, $ay + $t * $dy);
    }
    public function sendEmails(): void
    {
        $to = env('ALERT_EMAIL');
        $from = env('MAIL_FROM');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL))
            return;
        foreach (DB::all("SELECT n.id,a.message FROM notification_logs n JOIN alerts a ON a.id=n.alert_id WHERE n.channel='email' AND n.status<>'enviado' AND n.attempts<3 AND a.resolved_at IS NULL LIMIT 50") as $n) {
            // SMTP autenticado opcional ou mail()/MTA; retorno indica aceite, não entrega.
            $ok = (new EmailGateway)->send($to, 'SENN AI-X - alerta operacional', $n['message']);
            DB::run('UPDATE notification_logs SET status=?,attempts=attempts+1,attempted_at=NOW() WHERE id=?', [$ok ? 'enviado' : 'falha', $n['id']]);
        }
    }
}
