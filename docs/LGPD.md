# Tratamento de dados e LGPD — mecanismos e pendências

Esta é uma base técnica, não parecer jurídico nem declaração automática de conformidade.

## Antes de inserir dados reais

Identificar controlador e encarregado/canal do titular; registrar finalidades e bases legais por tratamento; definir prazo de retenção para pedidos, POD, GPS, logs e backups; avaliar operadores externos e transferências; informar titulares. A ciência registrada em `privacy_accepted_at` não substitui essas decisões e não deve ser tratada como consentimento genérico obrigatório.

Dados: usuários (nome/e-mail/hash/perfil); motoristas (CNH/categoria/validade/contato); destinatários (nome/endereço/região/posição); localização em rota e POD. Não coleta biometria, fotos ou assinatura nesta versão. A CNH é validada apenas no formato e deve ser protegida pelo servidor/banco. Não há criptografia de campos na aplicação; use disco/backup cifrados e banco protegido, além de TLS.

Gemini recebe agregados numéricos e nomes de regiões; o Helper também envia a pergunta livre sanitizada, guia e referência/status/janela de pedido autorizado. A aplicação não envia nomes de pessoas, CNH, endereços ou GPS do banco. Sanitização remove padrões reconhecidos de email/CPF/telefone, mas não garante remover todas as informações pessoais do texto: não inclua identificadores nas perguntas. Na faixa gratuita, Google informa que conteúdo pode ser usado para melhorar seus produtos. Avalie os termos do operador e transferências antes de habilitar com dados reais. Sem chave/API, processamento local. O modo local não envia perguntas a provedores. O histórico guarda até oito perguntas/respostas na sessão PHP; é removido ao sair da conta e pode ser apagado pelo botão Limpar. Não é uma tabela permanente do banco; o servidor precisa proteger e expirar seus arquivos de sessão. CDN/mapas recebem IP e dados técnicos do navegador.

## Exercício de direitos

Receba a solicitação em canal oficial, valide identidade e escopo, avalie retenção legal e documente a resposta. A CLI exige acesso administrativo ao servidor, backup prévio e confirmação explícita. Não existe endpoint público de exclusão.

```bash
php bin/anonymize.php delivery 123 --confirm
php bin/anonymize.php user 45 --confirm
```

Entrega encerrada: remove nome/endereço/hash e coordenadas diretas, região vira `anonimizada`, POD perde posição e telemetria de rota associada é removida para reduzir reidentificação. Rotas associadas precisam estar concluídas. Usuário sem rota aberta: remove nome/e-mail/CNH/contato, invalida senha e conta e limpa suas posições; mantém chaves históricas para integridade. Administrador deve ter perfil reatribuído antes da anonimização.

Isso é **anonimização operacional de campos diretos**, não garantia formal de anonimização irreversível de todo o conjunto: referências comerciais, horários e vínculos podem reidentificar pessoas quando combinados com outras fontes. Avalie remoção/retensão adicional e fontes externas. Não existe expurgo automático configurado.

Backups podem conter dados anteriores. Defina descarte/expiração, acesso mínimo e processo para reaplicar solicitações após restauração. Evite logs de corpo HTTP, senhas, tokens ou perguntas. CNH não aparece nas listagens, mas o acesso direto ao banco precisa ser restrito. Documente incidentes e mantenha um procedimento de resposta da equipe.
