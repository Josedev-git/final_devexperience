<?php
declare(strict_types=1);
namespace Services;
/** Heurística gulosa: parada viável mais próxima, desempate por prioridade.
 * Distância Haversine * fator viário. Sem trânsito/ruas; não garante ótimo global.
 */
final class RoutePlanner
{
    public static function distance(float $a, float $b, float $c, float $d): float
    {
        $x = sin(deg2rad($c - $a) / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin(deg2rad($d - $b) / 2) ** 2;
        return 6371 * 2 * asin(sqrt(min(1, max(0, $x))));
    }
    public function plan(array $jobs, array $vehicle, float $lat, float $lng, int $departure): array
    {
        if (!$jobs)
            throw new \DomainException('Selecione entregas pendentes.');
        if (array_sum(array_column($jobs, 'weight_kg')) > (float) $vehicle['capacity_kg'] || array_sum(array_column($jobs, 'volume_m3')) > (float) $vehicle['capacity_m3'])
            throw new \DomainException('Capacidade de peso ou volume excedida.');
        $speed = (float) env('AVERAGE_SPEED_KMH', '25');
        $factor = (float) env('ROAD_FACTOR', '1.3');
        $service = (int) env('STOP_SERVICE_MINUTES', '5') * 60;
        if ($speed <= 0 || $factor < 1 || $service < 0)
            throw new \DomainException('Configuração de roteirização inválida.');
        $depotLat = $lat;
        $depotLng = $lng;
        $clock = $departure;
        $km = 0;
        $stops = [];
        $priority = ['urgente' => 0, 'alta' => 1, 'normal' => 2];
        while ($jobs) {
            $candidates = [];
            foreach ($jobs as $i => $j) {
                $leg = self::distance($lat, $lng, (float) $j['lat'], (float) $j['lng']) * $factor;
                $arrival = max($clock + (int) ceil($leg / $speed * 3600), strtotime($j['window_start']));
                if ($arrival + $service <= strtotime($j['window_end']))
                    $candidates[] = ['index' => $i, 'leg' => $leg, 'eta' => $arrival, 'priority' => $priority[$j['priority']]];
            }
            if (!$candidates)
                throw new \DomainException('A heurística não encontrou sequência viável nas janelas. Ajuste saída/seleção; pode existir outra solução.');
            usort($candidates, fn($a, $b) => ($a['leg'] <=> $b['leg']) ?: ($a['priority'] <=> $b['priority']));
            $pick = $candidates[0];
            $j = $jobs[$pick['index']];
            unset($jobs[$pick['index']]);
            $stops[] = ['delivery_id' => $j['id'], 'eta' => date('Y-m-d H:i:s', $pick['eta']), 'leg_km' => round($pick['leg'], 3), 'sequence_no' => count($stops) + 1];
            $clock = $pick['eta'] + $service;
            $km += $pick['leg'];
            $lat = (float) $j['lat'];
            $lng = (float) $j['lng'];
        }
        $km += self::distance($lat, $lng, $depotLat, $depotLng) * $factor; // retorno à base
        if ($km > (float) $vehicle['autonomy_km'])
            throw new \DomainException('Rota excede a autonomia do veículo (inclui retorno).');
        return ['stops' => $stops, 'km' => round($km, 3), 'cost' => round($km * (float) $vehicle['cost_per_km'], 2)];
    }
}
