# Atualização — IA local e painel de conversa

Validações desta atualização (21/10/2026): **52 arquivos PHP com sintaxe válida**, JavaScript válido e **112 verificações automatizadas**: domínio 11, chave 8, contas 31, inteligência/simulação 34 e assistente local 28. Comandos no README. PHP 8.2 em WebAssembly; serviços com PDO SQLite adaptado em memória, sem tocar na base do usuário.

A nova suíte `tests/local-assistant.php` verifica reconhecimento de oito intenções; modo local mesmo com uma chave preenchida; contexto de período/região/referência; previsão 7/30 dias e riscos; consulta inexistente/injeção; assunto desconhecido; intervalo validado; histórico limitado/limpeza/isolamento por usuário e perfil; autorização de motorista e links permitidos ao despachante. Modelo generativo local não foi instalado: o assistente usa recuperação TF-IDF, intenções e consultas preparadas.

O navegador Chromium renderizou o painel em 1440, 390 e 320 px, sem erros JavaScript ou transbordamento horizontal da página. Foram verificados envio por sugestão/Enter, contexto, histórico após recarregar, limpeza e atalho do módulo de IA. As respostas vieram de fixtures calculadas pelos serviços PHP reais e os endpoints HTTP foram simulados no teste do navegador. Isso **não equivale ao teste HTTP/MySQL completo no XAMPP**. As capturas foram usadas apenas para inspeção interna.

A atualização não modifica schema/tabelas, contas, senhas ou dados existentes. Não houve teste de Gemini/SMTP reais, GPS físico, MySQL nativo, backup/restauração, carga ou auditoria WCAG. Execute o roteiro abaixo no XAMPP. Gemini é opcional; a IA local não usa chamadas a provedores e continua exigindo PHP/banco disponíveis.

---

## Registro e roteiro da revisão anterior

# Validação desta revisão — 21/10/2026

## Evidências executadas

Ambiente: PHP 8.2.33 em WebAssembly, Node para sintaxe JavaScript e PDO SQLite em memória para testes de serviços. Não havia servidor MySQL/MariaDB nativo nem uma instalação XAMPP nesta máquina. A aplicação entregue usa PDO MySQL; o SQLite é exclusivamente um adaptador nos testes.

| Verificação | Resultado |
|---|---|
| Sintaxe de todos os 49 arquivos PHP | Aprovada por `php -l` |
| `public/assets/app.js` | Aprovada por `node --check` |
| `tests/run.php` | 11 testes de domínio aprovados |
| `tests/secret.php` | 8 testes da chave automática aprovados |
| `tests/accounts.php` | 31 verificações de cadastro/login/perfis aprovadas |
| `tests/intelligence.php` | 34 verificações de dados, modelos, fallback, privacidade, escopo, simulação e renderização aprovadas |
| `.env` local / exemplo XAMPP / nome do schema | Coerentes: senn_aix, 127.0.0.1, 3306, root e senha vazia; sem chave real de API |

Total: **84 verificações automatizadas**, além do lint PHP/JavaScript. Os comandos reproduzíveis estão no README. Não execute os testes contra o banco real: as suítes usam memória, sem importar dados na sua base.

Os testes de integração constroem o schema completo adaptado para SQLite, ativam FKs e executam os serviços reais. A adaptação transforma tipos/ENUM/índices e sintaxe NOW/DATE_ADD/DATE_SUB/FOR UPDATE/INSERT IGNORE; portanto não testa semântica de bloqueios, compatibilidade de DDL ou concorrência do MySQL. Não confundir esse resultado com homologação XAMPP.

## Cobertura da revisão

- Preservação do administrador e da senha; recusa de segundo admin no serviço e por chave única no banco de teste.
- Cadastro e login nos quatro perfis; papel real do banco, sessão e direcionamento. Cadastro público jamais cria admin.
- Carga de demonstração com três veículos, motorista, mais de 300 entregas, rotas, comprovantes e quatro pedidos atuais. Repetir não duplica nem altera a conta admin.
- Indicadores com OTD parcial, custos e distância; identificação de pedidos/quilômetros sintéticos; agregação por região.
- Previsão 7/30 dias e MAE temporal; risco histórico com divisão temporal e Brier.
- Chat/narrativa sem chave; transporte Gemini **simulado** para resposta válida, cota 429, erro 500 e JSON inválido. Não houve chamada externa real.
- Inspeção do payload Gemini simulado: endpoint oficial/header de autenticação, remoção de email/CPF/telefone reconhecidos e ausência de destinatário/endereço/CNH do banco.
- Motorista alheio não recebe entregas de outro usuário nos indicadores.
- Parada atual detectada mesmo com movimento anterior; GPS antigo/lacunas não comprovam parada.
- Passos sucessivos do simulador; limite de deslocamento; recusa de pedidos fora do prefixo demonstrativo; nenhum POD automático.
- Monitor gera alerta de manutenção. Templates completos do dashboard e da inteligência sem histórico renderizam sem warnings.

## Ainda precisam ser executados no XAMPP

Não foram realizados: importação MySQL/MariaDB real, fluxo HTTP completo com cookies/CSRF, concorrência/transações MySQL, envio SMTP real, Gemini real, GPS físico, inspeção visual desktop/celular, Agendador do Windows, backup/restauração, teste de carga, pentest ou auditoria WCAG. SMTP e tarefas do Windows foram implementados e documentados, mas dependem dessas verificações no ambiente da equipe.

A documentação não afirma que os dados sintéticos validam eficácia preditiva. MAE, Brier e acurácia calculados no exemplo apenas demonstram a avaliação do modelo.

## Roteiro de aceite antes da apresentação

1. Faça backup da sua base. Instalação nova: importe schema; base enviada existente: preserve e execute `php bin/conferir-banco.php`. Opcionalmente, após backup, use `--adicionar-checks` para reforçar o dump sem CHECKs. Verifique a mensagem e se houve alteração parcial de DDL caso algum dado seja inválido.
2. Inicie por `iniciar.cmd`; entre com admin existente ou configure o único admin em banco novo.
3. Cadastre uma conta por perfil operacional, entre e saia; confira hash e perfil em users. Login com perfil diferente deve falhar. POST forjado role=admin no cadastro deve falhar. Endpoint de gestor chamado por motorista deve negar acesso.
4. Prepare a demonstração duas vezes. Confirme três veículos e ausência de duplicação dos pedidos do mesmo dia. Confira que sua conta anterior continua com a mesma senha.
5. Veja dashboard, análise regional, previsão diária e validação temporal. Mude o período e gere análise.
6. Despachante: cadastre pedido e importe CSV com datas ajustadas. Duplicata/linha inválida deve cancelar todo o lote. Guarde o código do destinatário.
7. Planeje os quatro pedidos de hoje em SIM1A26 com saída futura. Teste também sobrecarga/janela inviável/manutenção: devem ser recusadas.
8. Entre como sim.motorista em janela privada e inicie a rota. Admin avança GPS fictício; Rastreamento deve atualizar em até o próximo polling. Filtre rota/motorista/região. Não ative GPS real simultaneamente no teste de movimento sintético.
9. Para POD, conceda localização no navegador (ou localização de teste claramente identificada); código errado deve falhar, código certo deve gerar um único comprovante com timestamp. Outro motorista não pode confirmar. Trate todas as entregas e conclua; veículo/motorista são liberados.
10. Abra monitorar.cmd: confira manutenção/atrasos/telemetria e logs. Configure um SMTP autorizado e faça teste real de email, checando aceite e recebimento. Resolva um alerta.
11. Configure sua chave Gemini e consulte o Helper; confirme fonte Gemini. Desative AI_ENABLED e MAPS_ENABLED, reinicie e repita consultas/tabelas/planejamento. Com API ausente, a fonte deve ser local.
12. Exporte CSV e relatório via Imprimir/Salvar como PDF. Confira aviso de dados sintéticos. Teste largura de celular, teclado, labels e foco.
13. Execute backup, restaure em banco vazio separado e confira tabelas/contagens/login/POD. Configure tarefas do Windows e confirme histórico. Não altere DB_NAME da base principal antes de validar a recuperação.
14. Complete GitHub, commits reais, permissões dos avaliadores e PDF final conforme ENTREGA.md.
