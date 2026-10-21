# Rastreabilidade — PDF SENAI Dev Experience 2026

Base: Módulo A, versão final enviada. “Implementado” abaixo descreve o comportamento presente no código; os testes executados estão em VALIDACAO.md. Não é declaração de homologação integral.

| ID | Entrega / código | Limite ou configuração necessária |
|---|---|---|
| RF01 | Accounts.php, Auth.php, CatalogController::users, views/auth: cadastro público ativo, login seguro e quatro perfis | Cadastro escolhe Gestor, Despachante ou Motorista; login verifica o perfil selecionado. Administrador único por pedido do usuário; sem recuperação de senha |
| RF02 | CatalogController::vehicles e catalog.php: placa, tipo, kg, m³, autonomia, estado e manutenção | Criação/lista; alteração apenas status/manutenção; exclusão geral não implementada |
| RF03 | CatalogController::drivers: CNH/categoria/validade, contato, disponibilidade e vínculo | CNH sintática com 11 dígitos; sem consulta oficial. Validação legal de PBT/categoria exige evolução |
| RF04 | DeliveryController: cadastro e CSV, janela, região, coordenadas, peso/volume/prioridade | Importação ≤200 linhas / 1 MB; endereço não é geocodificado automaticamente |
| RF05 | RoutePlanner e RouteController: atribuição, ordem, espera, capacidade, janelas, autonomia e custo | Heurística local por veículo; sem ótimo global, ruas/trânsito, cancelamento ou replanejamento |
| RF06 | TrackingController + tracking.php + app.js: mapa, GPS real e polling com filtros | Browser aberto, permissão, HTTPS/localhost; 10 s; até 500 paradas; sem rastreamento background |
| RF07 | DriverController: código verificado, GPS, timestamp servidor, POD único | Evidência por código, uma das alternativas do PDF; sem foto/assinatura; não há antifraude GPS |
| RF08 | Intelligence::forecast e intelligence.php: 7/30 dias por região, barras e tabela diária | Modelo estatístico local sazonal; sem histórico não prevê; sem intervalos; MAE por backtest temporal de 14 dias |
| RF09 | Intelligence::risks: escore, explicação e ação sugerida | Regressão logística com corte temporal, Brier/acurácia e fallback empírico; escore operacional não calibrado; sem clima/trânsito |
| RF10 | Metrics, dashboard.php, export/report: OTD/custo/km/ocupação/status/região, período, CSV e PDF via impressão | CSV para Excel, sem XLSX nativo; PDF depende de “Salvar como PDF” do navegador |
| RF11 | Alerts, bin/monitor.php: janela a vencer, atraso, parado, desvio aproximado, CNH/manutenção, resolver e logs | Requer agendamento; email por SMTP autenticado opcional/mail(), máximo três tentativas; desvio linear pode gerar falso positivo |
| RF12 | Intelligence::narrative: Gemini generateContent API opcional e narrativa local | Chave/cota/internet necessários só para LLM; recomendações não causais e sem ações automáticas |
| RF13 | LocalAssistant + HelperConversation + Helper: intenções TF-IDF, consultas autorizadas, contexto, histórico temporário e orientações | Modo local padrão sem chave; contexto limitado ao escopo; não é LLM generativo; Gemini opcional com fallback; links de ações autorizadas |

| ID | Mecanismo | Limites |
|---|---|---|
| RNF01 | PDO nativo, validação por tipo, escape contextual, CSRF, RBAC, hashes, cookies, throttling e headers | Sem pentest formal; HTTPS e gestão de segredos dependem do deploy |
| RNF02 | Índices, limites de lote, paginação de pedidos, polling não concorrente | Não há teste de carga nem meta de latência validada; rastreamento/monitor têm consultas por rota; risco consulta última posição em uma única query |
| RNF03 | Layout responsivo, labels, foco visível, navegação por teclado, tabelas, skip link e aria-live | Sem auditoria WCAG independente / teste com leitor de tela |
| RNF04 | Polling 10 s, timestamp, erro explícito e stale >2 min | Baixa latência em demonstração; sem SLA validado; GPS depende de navegador |
| RNF05 | bin/backup.php e instrução de agendamento/restauração | Script PowerShell para tarefas de monitor/backup; requer execução e teste de restauração no Windows; cópia cifrada precisa ser configurada |
| RNF06 | Planejador, inteligência e tabelas locais sem APIs; estado GPS antigo explícito | Exige PHP/banco; não é aplicativo offline; não cria POD sem GPS |
| RNF07 | Core, Controllers, Services, Views e ativos independentes | Monoaplicação de uma transportadora; escalabilidade horizontal exigiria sessão/locks compartilhados |
| RNF08 | Aviso, ciência, minimização, RBAC, agregação para API e CLI de anonimização | Operador precisa definir controlador, base legal, retenção, direitos e backups; não há certificação |
| RNF09 | README, MANUAL, LGPD, ARQUITETURA e VALIDACAO | Contato real de suporte e encarregado devem ser preenchidos pela equipe |

## Itens formais do PDF

Ainda precisam ser executados pela equipe: repositório GitHub online, mínimo três commits reais e regulares, permissão para Cardoso30S / fiorezewesley / Greggori, nome definitivo de equipe e PDF final com link e README incluso. A URL do GitHub não foi fornecida, portanto não foi inventada nem publicada.

## Revisão de escopo solicitada pelo usuário

Todos os módulos de produto correspondem aos requisitos listados acima. Não há pagamentos, planos, redes sociais, módulo financeiro ou aprovação de cadastro. Cadastro na página inicial e administrador único são os dois ajustes explícitos do usuário sobre o RF01. A tela de configuração inicial apenas permite criar essa conta pelo navegador, sem comando obrigatório.

Backup/recuperação, aviso de privacidade/anonimização e documentação permanecem porque são RNF05, RNF08 e RNF09. CSRF, hashing, limites de tentativas e a chave automática são mecanismos do RNF01, não novas funcionalidades de negócio. Scripts de demonstração e testes são auxiliares de instalação/validação e não adicionam módulos ao site.
