<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../config/bootstrap.php';
use Core\DB;
$required=['users'=>['id','name','email','password_hash','role','admin_singleton','active','privacy_accepted_at','created_at'],'vehicles'=>['id','plate','type','capacity_kg','autonomy_km','status','maintenance_date','cost_per_km'],'drivers'=>['id','user_id','cnh','cnh_category','cnh_expires','phone','available','vehicle_id'],'deliveries'=>['id','reference','recipient_name','address','region','lat','lng','window_start','window_end','weight_kg','priority','status','confirmation_hash','created_by','delivered_at','created_at'],'routes'=>['id','vehicle_id','driver_id','created_by','status','departure_at','completed_at','estimated_km','actual_km','estimated_cost','cost_per_km','origin_lat','origin_lng','algorithm'],'route_stops'=>['id','route_id','delivery_id','sequence_no','eta','leg_km'],'positions'=>['id','route_id','user_id','lat','lng','recorded_at'],'delivery_proofs'=>['id','delivery_id','driver_id','delivered_at','lat','lng','evidence_type'],'alerts'=>['id','dedup_key','kind','message','route_id','resolved_at','resolved_by','created_at'],'notification_logs'=>['id','alert_id','channel','status','attempts','attempted_at'],'audit_logs'=>['id','user_id','action','entity_type','entity_id','created_at'],'login_attempts'=>['key_hash','failures','blocked_until','updated_at']];
$missing=0;
foreach($required as $table=>$columns){
    $present=array_column(DB::all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',[env('DB_NAME'),$table]),'COLUMN_NAME');
    $absent=array_diff($columns,$present);
    if($absent){$missing++;echo 'INCOMPATÍVEL '.$table.': faltam '.implode(', ',$absent).PHP_EOL;}
    else echo 'OK '.$table.PHP_EOL;
}
if($missing)exit(1);
if(!in_array('--adicionar-checks',$argv,true)) {echo "Estrutura compatível. Nenhum dado foi alterado.\n";exit;}
$checks=[
'vehicles'=>['ck_senn_vehicle_values'=>'capacity_kg>0 AND capacity_m3>0 AND autonomy_km>0 AND cost_per_km>=0'],
'deliveries'=>['ck_senn_delivery_values'=>'window_end>window_start AND weight_kg>0 AND volume_m3>0','ck_senn_delivery_geo'=>'lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180'],
'positions'=>['ck_senn_position_geo'=>'lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180'],
'delivery_proofs'=>['ck_senn_proof_geo'=>'lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180']
];
$normalize=fn(string $v)=>strtolower(preg_replace('/[\s`()]/','',$v));
foreach($checks as $table=>$rules){
    $present=DB::all("SELECT cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME=cc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=? AND tc.TABLE_NAME=? AND tc.CONSTRAINT_TYPE='CHECK'",[env('DB_NAME'),$table]);
    $clauses=array_map(fn($r)=>$normalize($r['CHECK_CLAUSE']),$present);
    foreach($rules as $name=>$clause){
        if(in_array($normalize($clause),$clauses,true)){echo 'Já protegido: '.$name.PHP_EOL;continue;}
        // Identificadores/expressões vêm exclusivamente das constantes acima.
        if(DB::one("SELECT COUNT(*) n FROM `$table` WHERE NOT ($clause)")['n']>0)throw new DomainException('Há registros inválidos em '.$table.'. Corrija-os antes de adicionar o CHECK. Nenhum registro foi excluído.');
        DB::run("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($clause)");
        echo 'CHECK acrescentado: '.$name.PHP_EOL;
    }
}
echo "Conferência concluída; nenhuma linha foi apagada.\n";
