<?php
declare(strict_types=1);
namespace Services;
use Core\DB;
final class DemoSeeder {
    public static function load():array {
        if(env('APP_ENV')!=='local') throw new \DomainException('Demonstração disponível somente no ambiente local.');
        $admin=DB::one("SELECT id FROM users WHERE role='admin' LIMIT 1");
        if(!$admin) throw new \DomainException('Configure primeiro o administrador.');
        return DB::transaction(function()use($admin){
            $accounts=[];
            foreach(['gestor','despachante','motorista'] as $role){
                $email='sim.'.$role.'@example.test';
                if(!DB::one('SELECT id FROM users WHERE email=?',[$email])) DB::run('INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES(?,?,?,?,?)',['Simulação '.ucfirst($role),$email,password_hash('Demo-Senn-2026!',PASSWORD_DEFAULT),$role,date('Y-m-d H:i:s')]);
                $existing=DB::one('SELECT role FROM users WHERE email=?',[$email]);
                if($existing['role']!==$role)throw new \DomainException('Já existe conta com e-mail da simulação e perfil diferente. Nenhuma conta foi alterada.');
                $accounts[]=$email;
            }
            foreach([['SIM1A26','Van',1000,10,400,180],['SIM2B26','Utilitário',600,6,300,180],['SIM3C26','Van',900,8,350,-1]] as $v){
                if(!DB::one('SELECT id FROM vehicles WHERE plate=?',[$v[0]])) DB::run('INSERT INTO vehicles(plate,type,capacity_kg,capacity_m3,autonomy_km,status,maintenance_date,cost_per_km) VALUES(?,?,?,?,?,?,?,?)',[$v[0],$v[1],$v[2],$v[3],$v[4],$v[5]<0?'manutencao':'disponivel',date('Y-m-d',strtotime($v[5].' days')),2.5]);
            }
            $vehicle=DB::one("SELECT id FROM vehicles WHERE plate='SIM1A26'");
            $user=DB::one("SELECT id FROM users WHERE email='sim.motorista@example.test'");
            if(!DB::one('SELECT id FROM drivers WHERE user_id=?',[$user['id']])) DB::run('INSERT INTO drivers(user_id,cnh,cnh_category,cnh_expires,phone,vehicle_id) VALUES(?,?,?,?,?,?)',[$user['id'],'99900000001','B',date('Y-m-d',strtotime('+365 days')),'00000000000',$vehicle['id']]);
            $driver=DB::one('SELECT id FROM drivers WHERE user_id=?',[$user['id']]);
            // Todos os registros abaixo são sintéticos, identificados por SIM.
            for($day=56;$day>=1;$day--){
                $date=date('Y-m-d',strtotime('-'.$day.' days'));
                $ref='PED-SIM-H-'.$date.'-1';
                if(DB::one('SELECT id FROM deliveries WHERE reference=?',[$ref])) continue;
                $km=18+($day%9)*2;
                DB::run("INSERT INTO routes(vehicle_id,driver_id,created_by,status,departure_at,completed_at,estimated_km,actual_km,estimated_cost,cost_per_km,origin_lat,origin_lng,algorithm) VALUES(?,?,?,'concluida',?,?,?,?,?,2.5,-23.0264,-45.5553,'demo-history-v1')",[$vehicle['id'],$driver['id'],$admin['id'],$date.' 08:00:00',$date.' 17:00:00',$km,$km,$km*2.5]);
                $route=DB::connection()->lastInsertId();
                $count=4+($day%5);
                for($i=1;$i<=$count;$i++){
                    $late=($day+$i)%5===0;$failed=($day+$i)%17===0;
                    $status=$failed?'falha':'entregue';$end=$date.' '.($late?'12':'18').':00:00';$delivered=$failed?null:$date.' 15:00:00';
                    DB::run('INSERT INTO deliveries(reference,recipient_name,address,region,lat,lng,window_start,window_end,weight_kg,volume_m3,priority,status,created_by,delivered_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',['PED-SIM-H-'.$date.'-'.$i,'Pessoa fictícia','Endereço sintético', $i%2?'Centro':'Independência',-23.0264+$i*.001,-45.5553+$i*.001,$date.' 08:00:00',$end,5+$i,0.1,$late?'urgente':'normal',$status,$admin['id'],$delivered,$date.' 07:00:00']);
                    $delivery=DB::connection()->lastInsertId();
                    DB::run('INSERT INTO route_stops(route_id,delivery_id,sequence_no,eta,leg_km) VALUES(?,?,?,?,?)',[$route,$delivery,$i,$date.' 11:00:00',$km/$count]);
                    if(!$failed) DB::run('INSERT INTO delivery_proofs(delivery_id,driver_id,delivered_at,lat,lng) VALUES(?,?,?,?,?)',[$delivery,$driver['id'],$delivered,-23.0264+$i*.001,-45.5553+$i*.001]);
                }
            }
            // Repetir no mesmo dia preserva rotas, senhas e entregas existentes.
            $codes=[];
            for($i=1;$i<=4;$i++){
                $ref='PED-SIM-'.date('Ymd').'-'.$i;$code=(string)(112230+$i);$codes[$ref]=$code;
                if(DB::one('SELECT id FROM deliveries WHERE reference=?',[$ref]))continue;
                DB::run("INSERT INTO deliveries(reference,recipient_name,address,region,lat,lng,window_start,window_end,weight_kg,volume_m3,priority,confirmation_hash,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,'normal',?,?)",[$ref,'Pessoa fictícia','Ponto fictício '. $i,'Centro',-23.0264+$i*.002,-45.5553+$i*.002,date('Y-m-d').' 00:00:00',date('Y-m-d').' 23:59:00',10,.1,password_hash($code,PASSWORD_DEFAULT),$admin['id']]);
            }
            \audit('seed_simulation','demo');
            return ['accounts'=>$accounts,'codes'=>$codes];
        });
    }
    public static function tick():array {
        if(env('APP_ENV')!=='local')throw new \DomainException('Somente ambiente local.');
        return DB::transaction(function(){
            $route=DB::one("SELECT r.*,dr.user_id FROM routes r JOIN drivers dr ON dr.id=r.driver_id JOIN vehicles v ON v.id=r.vehicle_id WHERE r.status='em_andamento' AND r.algorithm IN ('nearest-feasible-v1','demo-simulation-v1') AND v.plate='SIM1A26' ORDER BY r.id DESC LIMIT 1 FOR UPDATE");
            if(!$route)throw new \DomainException('Planeje uma rota com SIM1A26 e inicie usando a conta sim.motorista.');
            $unsafe=DB::one("SELECT d.id FROM route_stops s JOIN deliveries d ON d.id=s.delivery_id WHERE s.route_id=? AND d.reference NOT LIKE 'PED-SIM-%' LIMIT 1",[$route['id']]);
            if($unsafe)throw new \DomainException('A simulação aceita somente pedidos PED-SIM.');
            $stop=DB::one("SELECT d.* FROM route_stops s JOIN deliveries d ON d.id=s.delivery_id WHERE s.route_id=? AND d.status='a_caminho' ORDER BY s.sequence_no LIMIT 1",[$route['id']]);
            if(!$stop)throw new \DomainException('Conclua ou encerre a rota na área do motorista.');
            $last=DB::one('SELECT lat,lng FROM positions WHERE route_id=? ORDER BY id DESC LIMIT 1',[$route['id']])??['lat'=>$route['origin_lat'],'lng'=>$route['origin_lng']];
            $lat=(float)$last['lat'];$lng=(float)$last['lng'];$km=RoutePlanner::distance($lat,$lng,(float)$stop['lat'],(float)$stop['lng']);
            $ratio=$km>0?min(1,.12/$km):1;$newLat=$lat+((float)$stop['lat']-$lat)*$ratio;$newLng=$lng+((float)$stop['lng']-$lng)*$ratio;
            DB::run('INSERT INTO positions(route_id,user_id,lat,lng) VALUES(?,?,?,?)',[$route['id'],$route['user_id'],$newLat,$newLng]);
            DB::run('UPDATE routes SET actual_km=actual_km+?,algorithm=\'demo-simulation-v1\' WHERE id=?',[min(.12,$km),$route['id']]);
            \audit('simulated_position','route',(int)$route['id']);
            return ['text'=>'GPS fictício atualizado. O POD continua sendo registrado pelo motorista com o código do destinatário.'];
        });
    }
}
