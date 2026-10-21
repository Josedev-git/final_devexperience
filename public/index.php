<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
use Core\{Auth, DB};
use Services\{Metrics, Intelligence};
$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page))
    abort(404, 'Página não encontrada.');
$method = $_SERVER['REQUEST_METHOD'];
$actions = ['logout', 'position', 'chat', 'analysis', 'resolve', 'chat-clear'];
if (in_array($page, $actions, true) && $method !== 'POST')
    abort(405, 'Use POST.');
if ($method === 'POST')
    checkCsrf();
try {
    switch ($page) {
        case 'login':
            if ($method === 'POST') {
                $email = text($_POST, 'email', 190);
                $password = passwordInput($_POST, 200);
                $role = choice($_POST, 'role', ['admin', 'gestor', 'despachante', 'motorista']);
                if (Auth::login($email, $password, $role))
                    redirect(Auth::landingPage(Auth::require()['role']));
                flash('E-mail, senha ou perfil inválidos, ou acesso temporariamente bloqueado.');
                redirect('login');
            }
            view('auth/login', ['title' => 'Bem-vindo', 'canSetup' => Services\Accounts::isLocal() && !Services\Accounts::hasAdmin()]);
            break;
        case 'register':
            if (Auth::user())
                redirect('dashboard');
            if ($method === 'POST') {
                Services\Accounts::register($_POST);
                flash('Conta criada com sucesso! Entre com seu e-mail e senha.');
                redirect('login');
            }
            view('auth/register', ['title' => 'Criar conta']);
            break;
        case 'setup':
            if (!Services\Accounts::isLocal())
                abort(403, 'Configuração inicial disponível apenas no próprio servidor.');
            if (Services\Accounts::hasAdmin()) {
                flash('O administrador já foi configurado. Faça login.');
                redirect('login');
            }
            if ($method === 'POST') {
                Services\Accounts::createAdmin($_POST);
                flash('Administrador criado! Entre com os dados que você escolheu.');
                redirect('login');
            }
            view('auth/setup', ['title' => 'Configuração inicial']);
            break;
        case 'logout':
            Auth::logout();
            redirect('login');
        case 'dashboard':
            Auth::require();
            [$from, $to] = Metrics::periods($_GET);
            $extra=[]; if(Auth::require()['role']!=='motorista') { $ai=new Intelligence; $extra=['forecast'=>$ai->forecast(),'riskReport'=>$ai->riskReport(),'regional'=>Metrics::regional($from,$to)]; }
            view('dashboard', ['title' => match (Auth::require()['role']) { 'gestor' => 'Painel do Gestor de Frota', 'despachante' => 'Painel do Despachante', 'motorista' => 'Indicadores do Motorista', default => 'Painel do Administrador'}, 'metrics' => Metrics::dashboard($from, $to), 'from' => $from, 'to' => $to]+$extra);
            break;
        case 'demo':
            Auth::require(['admin']);
            if (!Services\Accounts::isLocal()) abort(403, 'Demonstração somente no próprio servidor.');
            if ($method === 'POST') {
                $action=choice($_POST,'action',['seed','tick']);
                if($action==='seed') { Services\DemoSeeder::load(); flash('Dados fictícios preparados. Abra o Dashboard e depois planeje a rota.'); }
                else { $result=Services\DemoSeeder::tick(); flash($result['text']); }
                redirect('demo');
            }
            view('demo',['title'=>'Demonstração local']);
            break;
        case 'users':
            (new Controllers\CatalogController)->users();
            break;
        case 'vehicles':
            (new Controllers\CatalogController)->vehicles();
            break;
        case 'drivers':
            (new Controllers\CatalogController)->drivers();
            break;
        case 'deliveries':
            (new Controllers\DeliveryController)->index();
            break;
        case 'routes':
            (new Controllers\RouteController)->index();
            break;
        case 'driver':
            (new Controllers\DriverController)->index();
            break;
        case 'position':
            (new Controllers\DriverController)->position();
        case 'tracking':
            (new Controllers\TrackingController)->index();
            break;
        case 'tracking-data':
            Auth::require();
            jsonResponse((new Controllers\TrackingController)->data());
        case 'intelligence':
            Auth::require(['admin', 'gestor', 'despachante']);
            $ai = new Intelligence;
            view('intelligence', ['title' => 'Inteligência operacional', 'forecast' => $ai->forecast(), 'risks' => ($report=$ai->riskReport())['items'], 'riskReport'=>$report]);
            break;
        case 'analysis':
            Auth::require(['admin', 'gestor', 'despachante']);
            if (time() < ($_SESSION['ai_next'] ?? 0))
                abort(429, env('AI_MODE','local')==='gemini'?'Aguarde um minuto para solicitar nova análise.':'Aguarde dois segundos para solicitar nova análise.');
            $_SESSION['ai_next'] = time() + (env('AI_MODE','local')==='gemini'?60:2);
            [$from,$to]=Metrics::periods($_POST);
            $metrics = Metrics::dashboard($from,$to);
            jsonResponse((new Intelligence)->narrative($metrics,Metrics::regional($from,$to)));
        case 'chat-history':
            Auth::require();
            jsonResponse(['messages'=>Services\HelperConversation::messages(),'mode'=>env('AI_MODE','local')==='gemini'?'gemini':'local']);
        case 'chat-clear':
            Auth::require();
            Services\HelperConversation::clear();
            jsonResponse(['ok'=>true]);
        case 'chat':
            Auth::require();
            if(time()<($_SESSION['chat_next']??0)) abort(429,'Aguarde um instante antes da próxima pergunta.');
            $_SESSION['chat_next']=time()+(env('AI_MODE','local')==='gemini'?5:1);
            jsonResponse((new Intelligence)->chatReply(text($_POST, 'question', 500)));
        case 'alerts':
            Auth::require(['admin', 'gestor', 'despachante']);
            view('alerts', ['title' => 'Alertas & notificações', 'rows' => DB::all('SELECT * FROM alerts ORDER BY resolved_at IS NULL DESC,id DESC LIMIT 100')]);
            break;
        case 'resolve':
            Auth::require(['admin', 'gestor', 'despachante']);
            $id = (int) number($_POST, 'id', 1, PHP_INT_MAX);
            DB::transaction(function () use ($id) {
                DB::run('UPDATE alerts SET resolved_at=NOW(),resolved_by=? WHERE id=? AND resolved_at IS NULL', [Auth::require()['id'], $id]);
                audit('resolve', 'alert', $id); });
            flash('Alerta resolvido.');
            redirect('alerts');
        case 'export':
            Auth::require(['admin', 'gestor', 'despachante']);
            [$from, $to] = Metrics::periods($_GET);
            $m = Metrics::dashboard($from, $to);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="senn-indicadores.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ([['Período', $from . ' a ' . $to], ['Indicador', 'Valor'], ['Pedidos', $m['total']], ['Entregues', $m['done']], ['OTD (%)', $m['otd'] ?? 'Sem dados'], ['Custo estimado por entrega roteirizada (BRL)', $m['cost'] ?? 'Sem dados'], ['Km GPS amostrados (inclui simulação se informada)', $m['km']], ['Pedidos fictícios de demonstração', $m['demo_count']], ['Km sintéticos incluídos no total', $m['synthetic_km']], ['Frota ocupada (%)', $m['occupancy'] ?? 'Sem dados']] as $row)
                fputcsv($out, $row, ';', '"', '');
            fclose($out);
            exit;
        case 'report':
            Auth::require(['admin', 'gestor', 'despachante']);
            [$from, $to] = Metrics::periods($_GET);
            view('report', ['title' => 'Relatório gerencial', 'metrics' => Metrics::dashboard($from, $to), 'from' => $from, 'to' => $to]);
            break;
        case 'privacy':
            view('privacy', ['title' => 'Privacidade & proteção de dados']);
            break;
        default:
            abort(404, 'Página não encontrada.');
    }
} catch (DomainException $error) {
    if (in_array($page, ['position', 'chat', 'chat-history', 'chat-clear', 'analysis', 'tracking-data'], true))
        jsonResponse(['error' => $error->getMessage()], 422);
    flash($error->getMessage());
    if ($method === 'POST')
        redirect(in_array($page, ['resolve'], true) ? 'alerts' : $page);
    http_response_code(422);
    view('error', ['title' => 'Confira os dados', 'message' => $error->getMessage()]);
} catch (PDOException $error) {
    if (($error->errorInfo[0] ?? '') === '23000') {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            jsonResponse(['error' => 'Registro duplicado ou vínculo inválido.'], 409);
        flash($page === 'register' ? 'Não foi possível cadastrar com estes dados. Se você já possui conta, faça login.' : 'Registro duplicado ou vínculo inválido. Revise os dados.');
        redirect($page);
    }
    throw $error;
}
