<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli')
    exit;
require __DIR__ . '/../config/bootstrap.php';
use Core\DB;
$type = $argv[1] ?? '';
$id = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT);
if (!in_array($type, ['delivery', 'user'], true) || !$id || ($argv[3] ?? '') !== '--confirm')
    exit("Uso: php bin/anonymize.php delivery|user ID --confirm\nFaça backup e valide base legal/retenção antes de executar. Irreversível.\n");
DB::transaction(function () use ($type, $id) {
    if ($type === 'delivery') {
        $d = DB::one('SELECT * FROM deliveries WHERE id=? FOR UPDATE', [$id]);
        if (!$d || !in_array($d['status'], ['entregue', 'falha'], true))
            throw new DomainException('Somente entrega encerrada pode ser anonimizada.');
        if (DB::one("SELECT r.id FROM routes r JOIN route_stops s ON s.route_id=r.id WHERE s.delivery_id=? AND r.status<>'concluida'", [$id]))
            throw new DomainException('Conclua a rota antes de anonimizar.');
        DB::run("UPDATE deliveries SET recipient_name=NULL,address=NULL,lat=0,lng=0,region='anonimizada',confirmation_hash=NULL WHERE id=?", [$id]);
        DB::run('UPDATE delivery_proofs SET lat=0,lng=0 WHERE delivery_id=?', [$id]);
        // Trajetórias podem reidentificar endereços; remove a telemetria de todas as rotas relacionadas.
        DB::run('DELETE p FROM positions p JOIN route_stops s ON s.route_id=p.route_id WHERE s.delivery_id=?', [$id]);
    } else {
        $u = DB::one('SELECT * FROM users WHERE id=? FOR UPDATE', [$id]);
        if (!$u)
            throw new DomainException('Usuário não encontrado.');
        if ($u['role'] === 'admin')
            throw new DomainException('Reatribua o perfil administrador antes de anonimizar.');
        if (DB::one("SELECT r.id FROM routes r JOIN drivers d ON d.id=r.driver_id WHERE d.user_id=? AND r.status<>'concluida'", [$id]))
            throw new DomainException('Usuário possui rota aberta.');
        DB::run('DELETE FROM positions WHERE user_id=?', [$id]);
        DB::run("UPDATE users SET name='Usuário anonimizado',email=?,password_hash=?,active=0 WHERE id=?", ['anon-' . $id . '@example.invalid', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $id]);
        DB::run("UPDATE drivers SET cnh=NULL,phone='removido',available=0,vehicle_id=NULL,cnh_expires='1970-01-01' WHERE user_id=?", [$id]);
    }
    DB::run('INSERT INTO audit_logs(action,entity_type,entity_id) VALUES(?,?,?)', ['anonymize', $type, $id]);
});
echo "Dados diretos anonimizados. Revise backups e demais registros segundo a política de retenção.\n";
