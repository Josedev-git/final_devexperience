# Manual do usuário

## Antes de começar

O responsável prepara banco/ambiente. Na página inicial, use Criar conta para cadastrar nome, e-mail e senha. A conta fica ativa imediatamente na função selecionada (Gestor, Despachante ou Motorista). Acesso via login com e-mail, senha e perfil; o servidor confere os três dados com o banco. A sessão expira após 30 minutos sem atividade. Para sair, use Sair da conta. Tentativas inválidas repetidas bloqueiam temporariamente a conta/IP. Cada usuário deve usar sua própria conta.

## Administrador

Na instalação inicial, abra Configurar administrador inicial no login por localhost e defina a conta única. Se já existe admin, use a conta existente; nenhum novo admin pode ser criado. Usuários comuns se cadastram sozinhos. Em Usuários, o administrador pode atribuir funções Motorista, Gestor ou Despachante às contas e também cadastrar contas operacionais. Não há aprovação obrigatória para entrar. Para alterar senha não existe tela nesta base.

## Gestor de frota

1. Em Frota, informe placa, tipo, capacidade kg/m³, autonomia, custo por km e próxima manutenção. Veículos em manutenção não podem ser roteirizados.
2. Em Motoristas, escolha uma conta do perfil Motorista criada anteriormente; preencha CNH, categoria, validade e contato. Vincule um veículo ainda sem motorista.
3. Antes de planejar, confirme legalmente a categoria da CNH e o peso bruto/tipo do veículo. O protótipo valida vencimento e bloqueia categoria A para carga, mas não consulta órgãos oficiais nem modela todas as regras legais.
4. Em Frota, use Atualizar manutenção / disponibilidade para registrar a nova data após o serviço. Veículos com rota aberta não podem ser alterados por essa ação.

## Despachante

1. Em Pedidos, cadastre uma referência `PED-...`, destinatário/endereço, região, coordenadas, janela, peso, volume e prioridade. Coordenadas devem representar a entrega; o endereço textual sozinho não permite calcular a rota.
2. Guarde o código que aparece uma única vez e repasse ao destinatário. Não entregue a lista completa ao motorista. Perdeu o código? Esta base não tem reemissão: não altere o hash manualmente; implemente fluxo auditado antes de operar com dados reais.
3. CSV: copie database/deliveries-example.csv, mantenha cabeçalho/ordem, vírgula como separador e ponto decimal. Datas `AAAA-MM-DDTHH:MM`. Até 200 pedidos. Uma linha inválida cancela o lote completo, inclusive duplicatas.
4. Em Rotas, escolha veículo disponível, motorista vinculado, saída futura e pedidos pendentes. A geração pode rejeitar carga/janelas/autonomia. O sistema não garante encontrar toda solução viável; ajuste seleção/saída ou distribua em mais rotas.
5. Confira a ordem no rastreamento, filtros de rota e a tabela. As linhas não seguem ruas. Não despache com base em uma estimativa sem revisar o caminho real.

## Motorista

1. Entre com uma conta de Motorista e abra Minhas entregas. Apenas rotas atribuídas aparecem.
2. Inicie a rota. O veículo já estava reservado; iniciar muda os pedidos para `a_caminho`.
3. Toque Ativar localização e permita GPS. Mantenha a página aberta. Em outro dispositivo, use HTTPS. Interrompa a coleta no botão Parar localização ou ao sair da página.
4. Na entrega, peça ao destinatário o código de seis dígitos. Preencha e toque Capturar GPS e confirmar POD. O sistema obtém posição atual, valida código e registra hora do servidor. Não é necessário digitar coordenadas; o formulário não as expõe.
5. Se a entrega não puder ser realizada, use Registrar falha e confirme. Isso encerra a tentativa sem POD; nova tentativa/replanejamento depende de desenvolvimento adicional.
6. Depois de tratar todas as paradas, use Concluir rota. Só então os recursos ficam disponíveis para uma nova atribuição. Não conclua antes de retornar/verificar o encerramento físico da operação.

Sem permissão GPS, o POD não é enviado. A operação básica continua, mas comprovantes exigem geolocalização conforme o desafio. Não invente localização para contornar a exigência.

## Dashboard, IA e Helper

Escolha período e observe os rótulos: OTD entre concluídas, custo estimado, km GPS amostrados, ocupação atual. O custo não é um valor financeiro realizado. CSV abre no Excel/LibreOffice; relatório abre para imprimir e salvar como PDF. Em Inteligência, veja previsão por região, projeção diária, escore de risco e análise do mês. API indisponível resulta em texto local identificado. O Helper responde status `PED-...`, OTD e orientações; não executa mudanças por conversa.

## Alertas e suporte

O operador de infraestrutura agenda o monitor. Atrasos, CNH/manutenção, ausência GPS, paradas e possíveis desvios aparecem em Alertas. Resolver registra reconhecimento; não muda status de pedido ou manutenção. Confirme o fato antes de agir porque GPS e corredor linear têm limitações.

Problemas: 419 → recarregue e entre novamente; GPS bloqueado → confira HTTPS e permissão; mapa offline → use tabela; sem alertas → peça execução do monitor; erro 500 → envie o identificador do incidente ao responsável técnico, sem publicar dados pessoais/senhas. O contato da equipe deve ser preenchido na implantação.


## Demonstração e Gemini nesta revisão

No próprio servidor local, o administrador abre Demonstração e prepara os dados fictícios. Há três veículos, histórico de 56 dias e quatro pedidos do dia. Contas e códigos estão na tela e no README. Planeje com SIM1A26, inicie pela conta sim.motorista e avance a posição fictícia na tela do administrador. Não utilize essa simulação com dados reais. POD continua exigindo código e localização do navegador.

Dashboard também exibe previsão 7/30 dias com MAE, riscos históricos com avaliação temporal e tabela de OTD/custo regional. Gerar análise considera o período selecionado no Dashboard; na página Inteligência, o mês atual. O Helper usa Gemini quando chave/cota estão disponíveis e informa modo local nas falhas. Não inclua nomes, endereços ou documentos em perguntas: com Gemini ativo, a pergunta sanitizada é enviada ao provedor. Leia o aviso de privacidade.


## Assistente local ampliado

O Helper funciona sem chave (`AI_MODE=local`, padrão), usando reconhecimento de intenção, consultas com escopo e base de orientação. Abra o painel para ver o histórico temporário, escrever perguntas e usar sugestões. Enter envia; Shift + Enter quebra a linha. O botão Limpar remove conversa e contexto. O histórico da sessão conserva até oito perguntas e é removido ao sair da conta.

Perguntas complementares como “e ontem?”, “e no Centro?” e “e nos próximos 30 dias?” reaproveitam o contexto. Nova pergunta completa inicia outra consulta. Referências PED-... só retornam pedidos autorizados. Motoristas não recebem previsão/alertas globais. O assistente não modifica a operação pela conversa e informa quando não reconhece um assunto. Não é um modelo generativo de conversa geral.
