<section class="panel"><h2>Demonstração local — dados fictícios</h2>
<p>Adiciona três veículos, um motorista vinculado, 56 dias de histórico sintético e quatro pedidos para hoje. Preserva suas contas e senhas. Não representa uma operação real.</p>
<form method="post"><?= csrf() ?><input type="hidden" name="action" value="seed"><button>Preparar demonstração</button></form>
<p>Contas criadas: sim.gestor@example.test, sim.despachante@example.test e sim.motorista@example.test. Senha inicial: <code>Demo-Senn-2026!</code>. Uma conta já existente mantém sua senha.</p>
<ol><li>Carregue os dados e abra o Dashboard.</li><li>Em Rotas, escolha SIM1A26, o motorista de simulação e os quatro pedidos PED-SIM de hoje. Origem: -23.0264, -45.5553.</li><li>Em outra janela privada, entre como motorista e inicie a rota.</li><li>Nesta página, avance o GPS fictício; acompanhe o mapa em Rastreamento.</li><li>Na área do motorista, registre o POD com GPS real do navegador e o código abaixo. Para uma apresentação remota dos pontos fictícios, utilize a simulação GPS das ferramentas do navegador, claramente identificada como teste.</li></ol>
<p>Pedidos de hoje: final -1 → 112231; -2 → 112232; -3 → 112233; -4 → 112234. O simulador não confirma entregas nem falsifica o POD.</p>
<form method="post"><?= csrf() ?><input type="hidden" name="action" value="tick"><button>Avançar GPS fictício em até 120 metros</button></form>
<p>Use apenas em localhost. A simulação recusa rotas com pedidos que não sejam PED-SIM.</p></section>
