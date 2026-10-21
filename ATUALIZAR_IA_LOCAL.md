# Atualizar IA local e chatbot

1. Faça uma cópia da pasta atual do projeto.
2. Abra `SENN_AI_X_IA_local.zip` e copie seu conteúdo para dentro de `senn-ai-x`, mesclando as pastas e substituindo os arquivos solicitados. A atualização não inclui `.env`, `storage/app.key` nem SQL de migração.
3. Mantenha seu `.env` e o banco `senn_aix`. Não apague nem reimporte o banco. Sem `AI_MODE`, o padrão já é local; se seu arquivo possui `AI_MODE=gemini`, altere para `AI_MODE=local`.
4. No CMD, pare o servidor com Ctrl+C e ligue novamente:

```cmd
"C:\xampp\php\php.exe" -S localhost:8080 -t public public/router.php
```

5. No navegador, pressione Ctrl+F5, entre e abra SENN AI-X Helper. O painel mostra SENN Local e funciona sem chave.
6. Teste “Como está o desempenho hoje?” e depois “E ontem?”. Experimente “Previsão de demanda para 7 dias”, “E nos próximos 30 dias?” e “Quais entregas estão atrasadas?”.

A conversa mantém até oito perguntas na sessão, com botão Limpar. Enter envia; Shift+Enter quebra a linha. Motoristas consultam somente seu acesso. O assistente é voltado à logística do sistema e não é um LLM de conversa geral. Mapas continuam dependendo de internet; isso não afeta o processamento local do Helper.
