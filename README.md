# SENN AI-X

Plataforma web de gestao de entregas de ultima milha desenvolvida para o SENAI Dev Experience 2026 - Modulo A.

**Tecnologias:** PHP nativo, MySQL/MariaDB, HTML, CSS, JavaScript e Leaflet/OpenStreetMap. Todas as telas sao arquivos `.php`.

## Instalacao e execucao

1. Instale o XAMPP com PHP 8.2 ou superior, PDO MySQL, cURL e mbstring. Ligue o MySQL.
2. Extraia o projeto e abra a pasta `senn-ai-x` no VS Code.
3. Na primeira instalacao, ligue o Apache, acesse `http://localhost/phpmyadmin` e importe `database/schema.sql`. O script cria e seleciona `senn_aix`. Se ja possui um banco funcionando, mantenha-o e faca backup antes de qualquer alteracao.
4. Copie `.env.xampp.example` para `.env` e confira:

```dotenv
APP_ENV=local
APP_BASE_PATH=
APP_SECRET=
SESSION_SECURE=0
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=senn_aix
DB_USER=root
DB_PASS=
AI_MODE=local
AI_ENABLED=1
GEMINI_API_KEY=
```

Se o MySQL tiver senha, informe em `DB_PASS`. A chave da aplicacao e gerada automaticamente quando `APP_SECRET` esta vazio.

5. Abra o terminal **CMD** na raiz do projeto e execute:

```cmd
"C:\xampp\php\php.exe" -S localhost:8080 -t public public/router.php
```

6. Acesse **http://localhost:8080/index.php?page=login**. Mantenha o terminal aberto; `Ctrl + C` encerra o servidor. Tambem pode usar `iniciar.cmd`.

## Contas de teste

Importe `database/contas_teste.sql` pelo phpMyAdmin para criar estas contas:

| Perfil no login | E-mail | Senha inicial |
|---|---|---|
| Gestor de Frota | gestor@example.test | Demo-Senn-2026! |
| Despachante | despachante@example.test | Demo-Senn-2026! |
| Motorista | motorista@example.test | Demo-Senn-2026! |

Selecione o perfil correspondente ao e-mail. O script nao altera contas existentes; nesse caso, use a senha anterior.

**Administrador:** use o e-mail e a senha cadastrados na sua instalacao. Nao existe uma conta `admin@example.test` criada pelo script de testes. Em banco novo, use **Configurar administrador inicial** na tela de login, pelo computador do servidor. O cadastro publico permite Gestor, Despachante e Motorista, sem criar outro administrador.

## Dados para demonstracao

Com um administrador cadastrado, execute na raiz:

```cmd
"C:\xampp\php\php.exe" bin/demo.php
```

O comando adiciona veiculos, motorista, historico ficticio de 56 dias e quatro pedidos do dia. Tambem cria estas contas, caso ainda nao existam:

| Perfil | E-mail | Senha inicial |
|---|---|---|
| Gestor | sim.gestor@example.test | Demo-Senn-2026! |
| Despachante | sim.despachante@example.test | Demo-Senn-2026! |
| Motorista | sim.motorista@example.test | Demo-Senn-2026! |

Planeje os pedidos `PED-SIM-AAAAMMDD-1` a `-4` com o veiculo `SIM1A26` e o motorista da demonstracao. Entre como motorista para iniciar a rota e registrar as entregas. Os codigos de confirmacao sao `112231`, `112232`, `112233` e `112234`, respectivamente. Dados e movimentos simulados sao identificados como ficticios.

## IA utilizada e integracao

O modo padrao e **local**, executado em PHP, sem chave ou API externa:

- **Chatbot:** reconhecimento de intencoes por TF-IDF e similaridade de cosseno, regras e consultas autorizadas ao banco. Nao e um modelo generativo ou LLM.
- **Demanda:** modelo estatistico de sazonalidade semanal e tendencia recente, com previsoes regionais para 7 e 30 dias e avaliacao por MAE.
- **Risco de atraso:** regressao logistica local sobre o historico, combinada com regras de janela e telemetria. Com poucos dados, utiliza estimativa empirica. O escore operacional nao e uma probabilidade calibrada.
- **Desempenho:** comparacao de OTD e custo estimado entre regioes, com recomendacoes locais.

A integracao ocorre nos servicos `LocalAssistant.php`, `DemandForecast.php`, `RiskModel.php` e `Intelligence.php`. O chatbot respeita o perfil do usuario e guarda ate oito perguntas na sessao.

**Endpoints internos:**

| Metodo | Endpoint | Uso |
|---|---|---|
| POST | /index.php?page=chat | Enviar pergunta no campo `question` |
| GET | /index.php?page=chat-history | Consultar historico da sessao |
| POST | /index.php?page=chat-clear | Limpar conversa |
| POST | /index.php?page=analysis | Solicitar analise de desempenho |

Esses endpoints exigem autenticacao; os POST usam protecao CSRF. Exemplo de pergunta: `Quais entregas estao atrasadas?` ou `Previsao de demanda para 7 dias`.

### Gemini opcional

`GeminiGateway.php` integra a API por cURL, com HTTPS e chave no cabecalho `x-goog-api-key`. Para ativar, configure `AI_MODE=gemini`, `GEMINI_API_KEY` e `GEMINI_MODEL` com um modelo disponivel na sua conta. O gateway possui `gemini-3.5-flash-lite` como valor padrao no codigo; a integracao externa nao foi validada com uma chave real nesta entrega.

Endpoint externo:

```text
POST https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent
```

O corpo usa `systemInstruction`, `contents` e temperatura `0.2`. Os prompts estao em `Intelligence.php` e orientam:

- **Chat:** responder em portugues usando somente a resposta autorizada pelo sistema local, sem inventar dados ou executar acoes, em ate 180 palavras.
- **Analise:** produzir diagnostico e tres acoes usando somente o JSON de indicadores, comparar custos e OTD e declarar insuficiencia de dados.

Em falha, indisponibilidade ou ausencia de chave, o sistema retorna respostas locais. As previsoes e os riscos continuam sendo calculados localmente.

## Abordagem de roteirizacao

`RoutePlanner.php` aplica uma heuristica gulosa de **vizinho viavel mais proximo**. Calcula distancias pela formula de Haversine com fator de ajuste viario e considera peso, volume, janelas de entrega, tempo de atendimento e autonomia, incluindo retorno a base. Tambem verifica disponibilidade do motorista e do veiculo.

Os valores padrao sao 25 km/h, fator viario 1.3 e 5 minutos por parada, configuraveis no ambiente. O custo e estimado pela distancia e pelo custo por km do veiculo. O Leaflet exibe os pontos e o trajeto aproximado; nao ha consulta de transito ou garantia de rota globalmente otima.

## Funcionalidades e seguranca

Cadastro e login por perfil; frota e motoristas; pedidos e CSV; rotas e rastreamento; comprovacao por codigo, GPS e horario; dashboard; alertas; chatbot; exportacao CSV e relatorio para salvar em PDF.

O projeto utiliza PDO com prepared statements, escape de saida, sessoes e CSRF. Nao publique `.env`, chaves, dados pessoais ou backups. Sem mapa externo, a tabela mantem a consulta basica. E-mail depende de configuracao SMTP/MTA.

## Testes e documentacao

```cmd
"C:\xampp\php\php.exe" tests/run.php
"C:\xampp\php\php.exe" tests/secret.php
"C:\xampp\php\php.exe" tests/accounts.php
"C:\xampp\php\php.exe" tests/intelligence.php
"C:\xampp\php\php.exe" tests/local-assistant.php
```

As tres ultimas suites exigem PDO SQLite. Valide tambem os fluxos no XAMPP; os testes locais nao substituem testes reais de GPS, SMTP, Gemini e restauracao de backup.

Mais detalhes em [Manual](docs/MANUAL.md), [Arquitetura](docs/ARQUITETURA.md), [Requisitos](docs/REQUISITOS.md) e [Entrega](docs/ENTREGA.md).
