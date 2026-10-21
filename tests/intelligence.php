<?php
declare(strict_types=1);
putenv('APP_ENV=local');putenv('APP_SECRET='.str_repeat('f',64));putenv('AI_ENABLED=1');putenv('GEMINI_API_KEY=');
require __DIR__.'/../config/bootstrap.php';
// Driver de teste apenas: o aplicativo em produção continua usando PDO MySQL.
class SimulationTestPDO extends PDO {
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        $query=preg_replace_callback('/DATE_(ADD|SUB)\((NOW\(\)|CURDATE\(\)),INTERVAL (\d+) (MINUTE|DAY)\)/',fn($m)=>($m[2]==='CURDATE()'?'date':'datetime')."('now','".($m[1]==='ADD'?'+':'-').$m[3].' '.strtolower($m[4])."')",$query);
        $query=str_replace(['INSERT IGNORE INTO',' FOR UPDATE','NOW()','CURDATE()'],['INSERT OR IGNORE INTO','','CURRENT_TIMESTAMP',"date('now')"],$query);
        return parent::prepare($query,$options);
    }
}
use Core\{DB,Auth};use Services\{DemoSeeder,Metrics,Intelligence,StopDetector,GeminiGateway,RiskModel,DemandForecast,Alerts};
set_exception_handler(function(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().' line '.$e->getLine().PHP_EOL);exit(1);});
$pdo=new SimulationTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$sql=file_get_contents(ROOT.'/database/schema.sql');
$sql=preg_replace('/^--.*$/m','',$sql);
$sql=preg_replace('/CREATE DATABASE.*?;|USE `senn_aix`;|SET NAMES utf8mb4;/s','',$sql);
$sql=str_replace('BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
$sql=str_replace('BIGINT UNSIGNED','INTEGER',$sql);
$sql=preg_replace("/ENUM\('[^\n]+?\)/",'TEXT',$sql);
$sql=str_replace('UNIQUE KEY uq_users_single_admin(admin_singleton),','',$sql);
$sql=str_replace("THEN 1 ELSE NULL END) STORED,","THEN 1 ELSE NULL END) STORED UNIQUE,",$sql);
$sql=preg_replace('/UNIQUE KEY \w+\(/','UNIQUE(',$sql);
$sql=preg_replace('/,?\s*INDEX \w+\([^)]*\)/','',$sql);
$sql=str_replace('ENGINE=InnoDB','',$sql) ;  
$pdo->exec($sql); 
(new ReflectionProperty(DB::class,'pdo'))->setValue(null,$pdo);
$_SESSION=[];$_SERVER['REMOTE_ADDR']='127.0.0.1';
$count=0;
function verify(bool $ok,string $name):void {global $count;if(!$ok)throw new RuntimeException($name);$count++;echo "OK $name\n";}
DB::run("INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES('Admin','admin@example.test',?,'admin',?)",[password_hash('Preservada-2026!',PASSWORD_DEFAULT),date('Y-m-d H:i:s')]);
$admin=DB::one("SELECT * FROM users WHERE role='admin'");$_SESSION['uid']=$admin['id'];$_SESSION['last_activity']=time();
// Auth usa os mesmos campos de sessão do código real.
$_SESSION['user_id']=$admin['id'];
$data=DemoSeeder::load();
verify(count($data['codes'])===4,'Cria quatro pedidos atuais com códigos');
verify((int)DB::one('SELECT COUNT(*) n FROM vehicles')['n']===3,'Cria três veículos');
verify((int)DB::one('SELECT COUNT(*) n FROM deliveries')['n']>300,'Histórico sintético acima de 300 pedidos');
$before=DB::one('SELECT COUNT(*) n FROM deliveries')['n'];DemoSeeder::load();
verify(DB::one('SELECT COUNT(*) n FROM deliveries')['n']===$before,'Carga repetida não duplica pedidos');
verify(DB::one("SELECT * FROM users WHERE role='admin'")===$admin,'Preserva administrador e hash da senha');
$forecast=(new Intelligence)->forecast();verify(count($forecast['regions'])===2,'Previsão de duas regiões');verify($forecast['mae']!==null,'Backtest de demanda calcula MAE');
verify(count($forecast['regions'][0]['daily'])===30,'Projeção diária cobre 30 dias');
$r=(new Intelligence)->riskReport();verify(count($r['items'])===4,'Risco contém quatro pedidos abertos');verify($r['validation']['samples']>300 && $r['validation']['brier']!==null,'Modelo de risco avaliado com corte temporal');
$m=Metrics::dashboard(date('Y-m-d',strtotime('-60 days')),date('Y-m-d'));
verify($m['demo_count']>300 && $m['synthetic_km']===$m['km'],'Indicadores identificam dados e quilômetros sintéticos');
verify($m['otd']>0 && $m['otd']<100 && $m['cost']>0 && $m['km']>0,'Indicadores usam histórico, custos e distância');
$regions=Metrics::regional(date('Y-m-d',strtotime('-60 days')),date('Y-m-d'));verify(count($regions)===2,'Consulta regional agrega dados');
$reply=(new Intelligence)->chatReply('Qual é o desempenho do mês?');verify($reply['source']==='local' && $reply['text']!=='' && count($reply['actions'])===1,'Chat responde sem API e sugere navegação');
verify((new Intelligence)->narrative($m,$regions)['source']==='local','Análise funciona sem chave');
$now=time();$points=[];for($i=0;$i<=20;$i++)$points[]=['lat'=>$i<=16?-23.02:-23.04,'lng'=>-45.55,'recorded_at'=>date('Y-m-d H:i:s',$now-$i*60)];
verify(StopDetector::stopped($points,$now),'Detecta parada atual mesmo havendo movimento anterior');
verify(!StopDetector::stopped($points,$now+600),'GPS antigo não comprova parada');
verify(!StopDetector::stopped([$points[0],$points[16]],$now),'Lacuna de GPS não comprova parada');
$gateway=new GeminiGateway(fn()=>[200,json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'Resposta de teste']]]]]])]);
verify($gateway->reply('sistema','pergunta')['reason']==='missing_key','Sem chave usa fallback');
putenv('GEMINI_API_KEY=chave-ficticia-do-teste');
verify($gateway->reply('sistema','pergunta')['text']==='Resposta de teste','Parser Gemini recebe resposta via transporte simulado');
verify((new GeminiGateway(fn()=>[429,'{}']))->reply('x','y')['reason']==='quota','Quota Gemini aciona fallback');
verify((new GeminiGateway(fn()=>[500,'{}']))->reply('x','y')['reason']==='unavailable','Falha Gemini aciona fallback');
verify((new GeminiGateway(fn()=>[200,'invalid']))->reply('x','y')['reason']==='request_failed','JSON inválido aciona fallback');
$captured=null;
$inspector=new GeminiGateway(function($url,$body,$headers)use(&$captured){$captured=['url'=>$url,'body'=>$body,'headers'=>$headers];return [200,json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'Explicação via transporte de teste']]]]]])];});
(new Intelligence($inspector))->chatReply('Qual o OTD? email alice@example.test CPF 123.456.789-09 telefone (12) 99999-0000');
$payload=json_encode($captured['body']);
verify(!str_contains($payload,'alice@example.test') && !str_contains($payload,'123.456.789-09') && !str_contains($payload,'99999-0000'),'Remove identificadores reconhecidos da pergunta antes do provedor');
verify(!str_contains($payload,'Pessoa fict') && !str_contains($payload,'Endere') && !str_contains($payload,'99900000001'),'Contexto enviado omite destinatário, endereço e CNH do banco');
verify(str_starts_with($captured['url'],'https://generativelanguage.googleapis.com/v1beta/models/') && in_array('x-goog-api-key: chave-ficticia-do-teste',$captured['headers'],true),'Gateway usa endpoint oficial e chave no header do servidor');
putenv('GEMINI_API_KEY=');
// Caminho de movimento: somente veículo e pedidos da demonstração.
$vehicle=DB::one("SELECT * FROM vehicles WHERE plate='SIM1A26'");$driver=DB::one('SELECT * FROM drivers WHERE vehicle_id=?',[$vehicle['id']]);
DB::run("INSERT INTO routes(vehicle_id,driver_id,created_by,status,departure_at,estimated_km,estimated_cost,cost_per_km,origin_lat,origin_lng) VALUES(?,?,?,'em_andamento',?,2,5,2.5,-23.0264,-45.5553)",[$vehicle['id'],$driver['id'],$admin['id'],date('Y-m-d H:i:s')]);$route=DB::connection()->lastInsertId();
$delivery=DB::one("SELECT * FROM deliveries WHERE status='pendente' LIMIT 1");
DB::run('INSERT INTO route_stops(route_id,delivery_id,sequence_no,eta,leg_km) VALUES(?,?,1,?,1)',[$route,$delivery['id'],date('Y-m-d H:i:s')]);DB::run("UPDATE deliveries SET status='a_caminho' WHERE id=?",[$delivery['id']]);
DemoSeeder::tick();verify((float)DB::one('SELECT actual_km FROM routes WHERE id=?',[$route])['actual_km']<=.120001,'Simulador limita passo e atualiza distância');
DemoSeeder::tick();
verify(DB::one('SELECT COUNT(*) n FROM positions')['n']==2,'Simulador permite passos sucessivos e registra GPS');
verify(DB::one('SELECT status FROM deliveries WHERE id=?',[$delivery['id']])['status']==='a_caminho','Simulador não falsifica POD');
DB::run("UPDATE deliveries SET reference='PED-REAL-TESTE' WHERE id=?",[$delivery['id']]);$blocked=false;try{DemoSeeder::tick();}catch(DomainException){$blocked=true;}verify($blocked,'Simulador recusa pedido fora da demonstração');
(new Alerts)->scan();verify((int)DB::one("SELECT COUNT(*) n FROM alerts WHERE kind='manutencao'")['n']>=1,'Monitor detecta manutenção no histórico demonstrativo');
// Motorista só vê o próprio escopo; outro motorista não herda histórico.
DB::run("INSERT INTO users(name,email,password_hash,role,privacy_accepted_at) VALUES('Outro','outro@example.test','hash','motorista',?)",[date('Y-m-d H:i:s')]);
$_SESSION['uid']=DB::connection()->lastInsertId();$_SESSION['user_id']=$_SESSION['uid'];
verify(Metrics::dashboard(date('Y-m-d',strtotime('-60 days')),date('Y-m-d'))['total']===0,'Outro motorista não acessa entregas alheias');
$_SESSION['user_id']=$admin['id'];
set_error_handler(function(int $code,string $message,string $file,int $line){throw new ErrorException($message,0,$code,$file,$line);});
ob_start();view('dashboard',['title'=>'Teste','metrics'=>$m,'from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'forecast'=>$forecast,'riskReport'=>$r,'regional'=>$regions]);$html=ob_get_clean();
verify(str_contains($html,'Desempenho regional') && str_contains($html,'SENN AI-X Helper'),'Dashboard completo renderiza com inteligência e Helper');
ob_start();view('intelligence',['title'=>'Teste vazio','forecast'=>['regions'=>[],'mae'=>null,'warning'=>'Sem dados'],'risks'=>[],'riskReport'=>['validation'=>['samples'=>0,'brier'=>null]]]);$empty=ob_get_clean();
verify(str_contains($empty,'Sem dados'),'Inteligência sem histórico renderiza sem erro');
restore_error_handler();
echo "$count verificações de inteligência e simulação aprovadas.\n";
