<?php
declare(strict_types=1);
namespace Services;
use Core\{DB,Auth};
/** Assistente local: busca textual TF-IDF, intenções e consultas autorizadas; não é um LLM. */
final class LocalAssistant {
    private const EXAMPLES=[
        'greeting'=>'oi ola bom dia boa tarde boa noite tudo bem obrigado valeu',
        'help'=>'ajuda o que voce faz como usar funcionalidades do sistema cadastrar importar pedido csv',
        'delivery'=>'status situacao consultar pedido entrega referencia rastrear encomenda onde esta meu pedido',
        'delay'=>'quais entregas atrasadas atrasos vencidas janela vencida pedidos fora do prazo',
        'forecast'=>'previsao demanda prever proximos 7 30 dias quantas entregas previstas futuro semana mes seguinte',
        'risk'=>'risco de atraso entregas criticas priorizar prioridades pedidos urgentes',
        'cost'=>'custo custos gastos caro cara regiao mais cara custos por entrega despesas dinheiro',
        'performance'=>'desempenho indicadores resumo operacao painel otd taxa pontualidade hoje ontem concluidas quantidade entregas entregues quantos pedidos',
        'fleet'=>'frota veiculo veiculos disponiveis capacidade van placa motorista vinculado manutencao cnh',
        'routes'=>'rota rotas andamento caminho minhas rotas distancia quilometros km percurso',
        'alerts'=>'alertas notificacoes avisos ocorrencias eventos manutencao vencida parado desvio',
        'pod'=>'pod comprovante comprovar confirmar entrega codigo confirmacao assinatura foto registrar entrega',
        'planning'=>'como planejar gerar otimizar criar rota roteirizacao planejamento capacidade janela autonomia',
        'model'=>'ia inteligencia artificial modelo local como funciona previsao algoritmo api gemini chave offline'
    ];
    public static function normalize(string $value):string {
        $value=mb_strtolower($value,'UTF-8');
        $value=strtr($value,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
        return trim(preg_replace('/[^a-z0-9]+/u',' ',$value));
    }
    private static function tokens(string $value):array {
        $stop=['a','o','as','os','de','do','da','dos','das','e','em','no','na','nos','nas','um','uma','por','para','com','que','qual','quais','como','me','eu','voce','tem','esta','estao'];
        return array_values(array_filter(explode(' ',self::normalize($value)),fn($word)=>strlen($word)>1 && !in_array($word,$stop,true)));
    }
    public static function classify(string $question):array {
        $q=self::normalize($question);
        // Expressões específicas têm precedência sobre palavras genéricas.
        foreach(['planning'=>'/\b(planejar|roteirizacao|otimizar|gerar rota|criar rota)\b/','forecast'=>'/\b(previsao|prever|demanda|previstas|proximos)\b/','delay'=>'/\b(atrasadas|atrasados|vencidas|vencidos|atrasou)\b/','risk'=>'/\b(risco|riscos|criticas|criticos|priorizar|urgentes)\b/','pod'=>'/\b(pod|comprovante|confirmar entrega|comprovar|codigo)\b/','cost'=>'/\b(custo|custos|caro|cara|gasto|gastos)\b/','model'=>'/\b(ia|artificial|gemini|api|modelo local|chave)\b/'] as $intent=>$pattern) if(preg_match($pattern,$q))return ['intent'=>$intent,'confidence'=>1.0];
        $documents=[];$df=[];
        foreach(self::EXAMPLES as $intent=>$text){$documents[$intent]=array_count_values(self::tokens($text));foreach(array_keys($documents[$intent]) as $word)$df[$word]=($df[$word]??0)+1;}
        $query=array_count_values(self::tokens($question));$scores=[];$n=count($documents);
        foreach($documents as $intent=>$terms){$dot=0;$qn=0;$dn=0;
            foreach($query as $word=>$tf){$idf=log(($n+1)/(1+($df[$word]??0)))+1;$weight=(1+log($tf))*$idf;$qn+=$weight*$weight;if(isset($terms[$word]))$dot+=$weight*(1+log($terms[$word]))*$idf;}
            foreach($terms as $word=>$tf){$weight=(1+log($tf))*(log(($n+1)/(1+$df[$word]))+1);$dn+=$weight*$weight;}
            $scores[$intent]=$qn&&$dn?$dot/sqrt($qn*$dn):0;
        }
        arsort($scores);$intent=array_key_first($scores);$score=$scores[$intent]??0;
        return ['intent'=>$score>=.10?$intent:'unknown','confidence'=>round($score,3)];
    }
    public function reply(string $question,array $previous=[]):array {
        $user=Auth::require();$q=self::normalize($question);$match=self::classify($question);$intent=$match['intent'];
        $follow=preg_match('/^(e |e$|tambem |nessa |nesse |a mesma |o mesmo |esse |essa |ele |ela |qual a janela|qual o status)/',$q) || preg_match('/^(hoje|ontem|semana|mes|nos ultimos)/',$q);
        if($follow && isset($previous['intent']) && in_array($intent,['unknown','greeting','performance','delivery'],true))$intent=$previous['intent'];
        if($follow && !empty($previous['reference']) && preg_match('/\b(janela|status|situacao|onde ele|onde ela)\b/',$q))$intent='delivery';
        $from=$follow?($previous['from']??date('Y-m-01')):date('Y-m-01');$to=$follow?($previous['to']??date('Y-m-d')):date('Y-m-d');
        if(preg_match('/\bontem\b/',$q))$from=$to=date('Y-m-d',strtotime('-1 day'));
        elseif(preg_match('/\bhoje\b/',$q))$from=$to=date('Y-m-d');
        elseif(preg_match('/\b(semana|7 dias)\b/',$q)){$from=date('Y-m-d',strtotime('-6 days'));$to=date('Y-m-d');}
        elseif(preg_match('/\b(30 dias|mes)\b/',$q)){$from=preg_match('/30 dias/',$q)?date('Y-m-d',strtotime('-29 days')):date('Y-m-01');$to=date('Y-m-d');}
        if(preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/',$question,$dates) && count($dates[0])>=2)[$from,$to]=Metrics::periods(['from'=>$dates[0][0],'to'=>$dates[0][1]]);
        [$scope,$params]=Metrics::scope();$region=$follow?($previous['region']??null):null;
        $allowedRegions=DB::all("SELECT DISTINCT d.region FROM deliveries d WHERE $scope ORDER BY d.region LIMIT 100",$params);
        foreach($allowedRegions as $row)if(self::normalize($row['region'])!=='' && preg_match('/\b'.preg_quote(self::normalize($row['region']),'/').'\b/',$q)){$region=$row['region'];break;}
        if(preg_match('/\b(todas as regioes|geral|sem filtro)\b/',$q))$region=null;
        $reference=$follow?($previous['reference']??null):null;
        if(preg_match('/\bPED-[A-Z0-9-]+\b/i',$question,$found)){$reference=strtoupper($found[0]);$intent='delivery';}
        $context=['intent'=>$intent,'from'=>$from,'to'=>$to,'region'=>$region,'reference'=>$reference];
        $actions=[];$suggestions=['Como está o desempenho hoje?','Quais entregas estão atrasadas?','Como planejar uma rota?'];
        $page=$user['role']==='motorista'?'driver':'dashboard';
        $label=$user['role']==='motorista'?'Minhas entregas':'Abrir dashboard';
        $period='Período: '.date('d/m/Y',strtotime($from)).' a '.date('d/m/Y',strtotime($to)).($region?' · '.$region:'').'.';
        $filter=$region?' AND d.region=?':'';$scopeParams=[...$params,...($region?[$region]:[])];
        $text='';
        switch($intent){
            case 'greeting':$text='Olá! Sou o SENN AI-X Helper. Consulto os dados da sua operação, explico os indicadores e ajudo no uso do sistema. Estou funcionando localmente, sem chave de API. O que você quer verificar?';break;
            case 'delivery':
                if(!$reference){$text='Me envie a referência completa do pedido, por exemplo PED-SIM-'.date('Ymd').'-1. Consulto status, região e janela somente dentro das suas permissões.';break;}
                $d=DB::one("SELECT d.reference,d.status,d.region,d.window_start,d.window_end,d.delivered_at FROM deliveries d WHERE d.reference=? AND $scope",[$reference,...$params]);
                if(!$d){$text='Pedido não encontrado no seu escopo. Confira a referência completa.';$context['reference']=null;break;}
                $labels=['pendente'=>'Pendente de planejamento','planejada'=>'Incluída em rota planejada','a_caminho'=>'A caminho','entregue'=>'Entregue','falha'=>'Falha registrada'];
                $text=$d['reference']."\nStatus: ".($labels[$d['status']]??$d['status'])."\nRegião: ".$d['region']."\nJanela: ".date('d/m H:i',strtotime($d['window_start'])).' até '.date('d/m H:i',strtotime($d['window_end'])).'.';
                if($d['delivered_at'])$text.="\nConcluída em ".date('d/m/Y H:i',strtotime($d['delivered_at'])).'.';
                elseif(strtotime($d['window_end'])<time() && !in_array($d['status'],['falha','entregue'],true))$text.="\nA janela venceu. Priorize a conferência da rota e o contato operacional.";
                $page=$user['role']==='motorista'?'driver':'tracking';$label='Acompanhar entrega';$suggestions=['Como registrar o comprovante?','Quais entregas estão atrasadas?'];break;
            case 'delay':
                $rows=DB::all("SELECT d.reference,d.window_end,d.region FROM deliveries d WHERE $scope $filter AND d.status IN ('pendente','planejada','a_caminho') AND d.window_end<? ORDER BY d.window_end LIMIT 11",[...$scopeParams,date('Y-m-d H:i:s')]);
                $text=$rows?'Entregas abertas com janela vencida'.($region?' em '.$region:'').":\n":'Não encontrei entregas abertas com janela vencida'.($region?' em '.$region:'').' no seu acesso.';
                foreach(array_slice($rows,0,10) as $row)$text.='• '.$row['reference'].' · '.$row['region'].' · '.date('d/m H:i',strtotime($row['window_end']))."\n";
                if(count($rows)>10)$text.="Há mais resultados; confira a lista de pedidos.\n";
                $text.="\nConsulta da situação atual, independente da data de cadastro. Entrega em falha não é contada como entrega aberta.";$page=$user['role']==='motorista'?'driver':'deliveries';$label='Ver entregas';break;
            case 'forecast':
                if($user['role']==='motorista'){$text='A previsão regional é destinada à gestão. Posso consultar suas entregas, rotas e indicadores.';break;}
                $forecast=(new DemandForecast)->forecast();$horizon=preg_match('/30|mes/',$q)?30:7;$rows=array_values(array_filter($forecast['regions'],fn($r)=>!$region||$r['region']===$region));
                $text=$rows?'Previsão para os próximos '.$horizon." dias, começando amanhã:\n":'Ainda não há histórico suficiente para apresentar essa previsão.';
                foreach($rows as $row)$text.='• '.$row['region'].': aproximadamente '.$row['next'.$horizon]." pedidos.\n";
                if($rows)$text.="\nModelo local de sazonalidade semanal e tendência. MAE do backtest: ".$forecast['mae'].' pedidos/dia. É uma estimativa, não uma garantia. Dados da demonstração são fictícios.';
                $page='intelligence';$label='Ver previsão diária';$suggestions=['E nos próximos 30 dias?','Quais entregas têm maior risco?'];break;
            case 'risk':
                if($user['role']==='motorista'){
                    $rows=DB::all("SELECT d.reference,d.window_end FROM deliveries d WHERE $scope AND d.status IN ('planejada','a_caminho') AND d.window_end<=? ORDER BY d.window_end LIMIT 8",[...$params,date('Y-m-d H:i:s',time()+1800)]);
                    $text=$rows?"Suas entregas abertas com janela vencida ou terminando em até 30 minutos:\n":'Não encontrei entregas suas com janela vencida ou terminando nos próximos 30 minutos.';
                    foreach($rows as $row)$text.='• '.$row['reference'].' · '.date('d/m H:i',strtotime($row['window_end']))."\n";
                    break;
                }
                $report=(new Intelligence)->riskReport();$rows=array_values(array_filter($report['items'],fn($r)=>!$region||$r['region']===$region));usort($rows,fn($a,$b)=>$b['score']<=>$a['score']);
                $text=$rows?"Entregas abertas, da maior para a menor prioridade:\n":'Não há entregas abertas para avaliar neste recorte.';
                foreach(array_slice($rows,0,5) as $row)$text.='• '.$row['reference'].' · escore '.$row['score']."/100\n  ".$row['reason']."\n  Ação: ".$row['action']."\n";
                $text.="\nCombino modelo histórico local e regras de janela/telemetria. O escore não é uma probabilidade calibrada.";$page='intelligence';$label='Ver análise de riscos';break;
            case 'fleet':
                if($user['role']==='motorista'){$rows=DB::all('SELECT v.plate,v.type,v.status FROM drivers dr JOIN vehicles v ON v.id=dr.vehicle_id WHERE dr.user_id=?',[$user['id']]);$text=$rows?'Seu veículo: '.$rows[0]['plate'].' · '.$rows[0]['type'].' · '.$rows[0]['status'].'.':'Seu usuário ainda não está vinculado a um veículo. Peça ao gestor para completar seu cadastro.';break;}
                $rows=DB::all('SELECT status,COUNT(*) n FROM vehicles GROUP BY status');$text=$rows?"Situação atual da frota:\n":'Nenhum veículo cadastrado.';
                foreach($rows as $row)$text.='• '.str_replace('_',' ',$row['status']).': '.$row['n']."\n";
                if(preg_match('/manutencao|cnh/',$q))$text.="\nConsulte Alertas para conferir manutenção/CNH próximas do vencimento. As notificações dependem da execução do monitor.";
                $page=in_array($user['role'],['admin','gestor'],true)?'vehicles':'tracking';$label=$page==='vehicles'?'Abrir frota':'Abrir rastreamento';break;
            case 'routes':
                $where=$user['role']==='motorista'?' AND dr.user_id=?':'';$args=$where?[$user['id']]:[];
                $rows=DB::all("SELECT r.id,r.status,r.estimated_km,r.actual_km,v.plate FROM routes r JOIN drivers dr ON dr.id=r.driver_id JOIN vehicles v ON v.id=r.vehicle_id WHERE r.status<>'concluida' $where ORDER BY r.departure_at LIMIT 8",$args);
                $text=$rows?"Rotas abertas no seu acesso:\n":'Não há rotas abertas no seu acesso.';
                foreach($rows as $row)$text.='• Rota #'.$row['id'].' · '.$row['plate'].' · '.str_replace('_',' ',$row['status']).' · estimativa '.$row['estimated_km'].' km · GPS amostrado '.$row['actual_km']." km.\n";
                $page=$user['role']==='motorista'?'driver':'routes';$label='Abrir rotas';break;
            case 'alerts':
                if($user['role']==='motorista'){$text='A central de alertas é destinada à gestão. Posso conferir suas entregas próximas do fim da janela.';$suggestions=['Quais entregas têm risco de atraso?'];break;}
                $rows=DB::all('SELECT kind,message FROM alerts WHERE resolved_at IS NULL ORDER BY id DESC LIMIT 8');$text=$rows?"Alertas abertos mais recentes:\n":'Não há alertas abertos registrados. O monitor precisa estar em execução para detectar novos eventos.';
                foreach($rows as $row)$text.='• '.$row['kind'].': '.$row['message']."\n";
                $page='alerts';$label='Abrir central de alertas';break;
            case 'planning':$text="Para planejar uma rota:\n1. Cadastre veículo e motorista com vínculo, CNH válida e manutenção em dia.\n2. Cadastre pedidos com coordenadas, peso, volume e janela.\n3. Em Rotas, selecione os pedidos, o veículo e seu motorista; informe uma saída futura.\n4. Confira o trajeto estimado e libere a operação.\nA heurística considera capacidade, autonomia, espera, atendimento e retorno à base; não usa trânsito nem garante o menor caminho global.";
                if($user['role']==='motorista')$text="O planejamento é feito pela gestão/despachante. Na sua área, inicie a rota atribuída, ative o GPS e registre as entregas.";
                $page=$user['role']==='motorista'?'driver':'routes';$label='Abrir planejamento';break;
            case 'pod':$text="O comprovante usa código + localização + horário do servidor.\n1. O motorista inicia sua rota.\n2. Na entrega, informa o código de seis dígitos recebido do destinatário.\n3. Clica em Capturar GPS e confirmar POD e permite a localização no navegador.\n4. O sistema confere o código e registra o comprovante.\nSem permissão de localização, o POD não é enviado. Códigos reais não podem ser consultados pelo chatbot.";$page=$user['role']==='motorista'?'driver':'deliveries';$label=$user['role']==='motorista'?'Minhas entregas':'Abrir pedidos';break;
            case 'model':$text="A IA local funciona no próprio servidor PHP, sem chave de API.\n• Conversa: reconhecimento de intenções por busca TF-IDF, base de orientação e consultas autorizadas ao banco.\n• Demanda: sazonalidade semanal e tendência, com backtest MAE.\n• Risco: regressão logística local e regras operacionais.\n• Desempenho: comparação de custo unitário e OTD por região.\nO assistente local não é um modelo generativo como o Gemini: responde aos assuntos da operação e pede mais detalhes quando não entende. Não executa comandos nem altera registros.";break;
            case 'performance':case 'cost':
                $m=Metrics::dashboard($from,$to);$regions=Metrics::regional($from,$to);
                if($region){$regions=array_values(array_filter($regions,fn($r)=>$r['region']===$region));$row=$regions[0]??null;
                    $text=$row?$period."\nPedidos: ".$row['deliveries']."\nConcluídos: ".$row['done']."\nOTD: ".($row['done']?round(100*$row['on_time']/$row['done'],1).'%':'sem concluídas')."\nCusto por pedido roteirizado: ".($row['routed']?\money((float)$row['estimated_cost']/$row['routed']):'sem dados').'.':$period.' Não há pedidos nesse período e região.';
                }else{$text=$period."\nPedidos: ".$m['total']."\nConcluídos: ".$m['done']."\n".Intelligence::localNarrative($m,$regions);}
                if($intent==='cost' && $region && $regions)$text.="\nOs custos são estimados por rota e rateados por parada, não custos financeiros realizados.";
                if($m['demo_count'])$text.="\nA base deste período inclui dados fictícios de demonstração.";
                $suggestions=['E ontem?','Qual região tem maior custo?','Quais entregas têm maior risco?'];break;
            case 'help':$text="Posso consultar:\n• Status e janela de um pedido pela referência PED-...\n• Entregas atrasadas, riscos e rotas abertas.\n• OTD, custos e desempenho por região e período.\n• Demanda prevista para 7 ou 30 dias (gestão).\n• Frota e alertas, conforme seu perfil.\nTambém explico cadastro, CSV, planejamento e POD. Para importar CSV, abra Pedidos e use o modelo de arquivo, ajustando as datas. Experimente uma das perguntas abaixo.";break;
            default:$text='Não consegui identificar essa consulta com segurança. Posso ajudar com entregas, custos, OTD, previsão, riscos, frota e uso do sistema. Tente “Quais entregas estão atrasadas?” ou informe a referência PED-... completa.';
        }
        $actions[]=['label'=>$label,'url'=>\url($page)];
        return ['text'=>trim($text),'source'=>'local','engine'=>'SENN Local','intent'=>$intent,'confidence'=>$match['confidence'],'context'=>$context,'actions'=>$actions,'suggestions'=>$suggestions];
    }
}
