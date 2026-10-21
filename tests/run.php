<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
use Services\RoutePlanner;
$count = 0;
function test(string $name, callable $fn): void
{
    global $count;
    $fn();
    $count++;
    echo "OK $name\n";
}
function check(bool $value, string $message = 'Falha'): void
{
    if (!$value)
        throw new RuntimeException($message);
}
function rejects(callable $fn): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('Esperava rejeição.');
}
putenv('AVERAGE_SPEED_KMH=25');
putenv('ROAD_FACTOR=1.3');
putenv('STOP_SERVICE_MINUTES=5');
$planner = new RoutePlanner;
$t = strtotime('2026-10-22 08:00:00');
$vehicle = ['capacity_kg' => 100, 'capacity_m3' => 2, 'autonomy_km' => 300, 'cost_per_km' => 2];
$job = ['id' => 1, 'weight_kg' => 50, 'volume_m3' => 1, 'lat' => -23.03, 'lng' => -45.56, 'window_start' => '2026-10-22 09:00:00', 'window_end' => '2026-10-22 12:00:00', 'priority' => 'normal'];
test('Haversine zero e simetria', function () {
    check(RoutePlanner::distance(0, 0, 0, 0) === 0.0);
    check(abs(RoutePlanner::distance(0, 0, 0, 1) - 111.195) < 0.1);
    check(abs(RoutePlanner::distance(1, 2, 3, 4) - RoutePlanner::distance(3, 4, 1, 2)) < 0.0001); });
test('Rota inclui espera e custo de ida/volta', function () use ($planner, $vehicle, $job, $t) {
    $p = $planner->plan([$job], $vehicle, -23.0264, -45.5553, $t);
    check($p['stops'][0]['eta'] === '2026-10-22 09:00:00');
    check($p['km'] > 0);
    check(abs($p['cost'] - $p['km'] * 2) < 0.02);
    check(abs($p['km'] - 2 * $p['stops'][0]['leg_km']) < 0.002); });
test('Rejeita excesso de peso', fn() => rejects(fn() => $planner->plan([array_replace($job, ['weight_kg' => 101])], $vehicle, -23, -45, $t)));
test('Rejeita excesso de volume', fn() => rejects(fn() => $planner->plan([array_replace($job, ['volume_m3' => 3])], $vehicle, -23, -45, $t)));
test('Verifica carga total, não só cada pedido', fn() => rejects(fn() => $planner->plan([$job, array_replace($job, ['id' => 2, 'weight_kg' => 60])], $vehicle, -23, -45, $t)));
test('Rejeita janela encerrada', fn() => rejects(fn() => $planner->plan([array_replace($job, ['window_start' => '2026-10-22 06:00:00', 'window_end' => '2026-10-22 07:00:00'])], $vehicle, -23.0264, -45.5553, $t)));
test('Atendimento precisa caber na janela', fn() => rejects(fn() => $planner->plan([array_replace($job, ['lat' => -23, 'lng' => -45, 'window_start' => '2026-10-22 08:00:00', 'window_end' => '2026-10-22 08:04:00'])], $vehicle, -23, -45, $t)));
test('Rejeita autonomia insuficiente', fn() => rejects(fn() => $planner->plan([$job], array_replace($vehicle, ['autonomy_km' => 0.1]), -23.0264, -45.5553, $t)));
test('Seleciona parada próxima primeiro e gera ordem única', function () use ($planner, $vehicle, $job, $t) {
    $close = array_replace($job, ['id' => 2, 'lat' => -23.0264, 'lng' => -45.5553]);
    $p = $planner->plan([$job, $close], $vehicle, -23.0264, -45.5553, $t);
    check($p['stops'][0]['delivery_id'] === 2);
    check(array_column($p['stops'], 'sequence_no') === [1, 2]); });
test('Sem pedido e configuração inválida', function () use ($planner, $vehicle, $job, $t) {
    rejects(fn() => $planner->plan([], $vehicle, -23, -45, $t));
    putenv('AVERAGE_SPEED_KMH=0');
    rejects(fn() => $planner->plan([$job], $vehicle, -23, -45, $t));
    putenv('AVERAGE_SPEED_KMH=25'); });
test('Validação coordenadas, data impossível, enum e XSS', function () {
    check(number(['lat' => '0'], 'lat', -90, 90) === 0.0);
    rejects(fn() => number(['lat' => '91'], 'lat', -90, 90));
    rejects(fn() => dateInput(['date' => '2026-02-30'], 'date', true));
    rejects(fn() => choice(['role' => 'root'], 'role', ['admin', 'motorista']));
    check(e('<script>') === '&lt;script&gt;');
    check(passwordInput(['password' => ' senha com espaços ']) === ' senha com espaços ');
});
echo "$count testes de domínio aprovados.\n";
