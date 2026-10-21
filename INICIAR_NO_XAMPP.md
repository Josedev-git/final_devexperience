# Comece por aqui — XAMPP

1. Extraia o ZIP em uma pasta nova. Não sobrescreva sua única cópia antiga.
2. Se já usa o banco `senn_aix` enviado para revisão, mantenha esse banco: as tabelas e nomes da conexão são os mesmos. **Não reimporte `schema.sql` nem as migrações antigas.** Faça um backup pelo phpMyAdmin antes da atualização. Se tinha senha/porta próprias, copie os valores do seu `.env` antigo para o novo.
3. Se é instalação do zero: ligue MySQL e Apache no XAMPP, abra `http://localhost/phpmyadmin`, clique em Importar e selecione `database/schema.sql`. Ele cria e seleciona `senn_aix`, evitando o erro #1046. Não apaga nem recria um banco existente.
4. O `.env` do pacote está preparado para XAMPP padrão: host `127.0.0.1`, porta `3306`, banco `senn_aix`, usuário `root`, senha vazia. Ajuste se seu MySQL usa outros dados. `APP_SECRET=` pode continuar vazio; a aplicação gera a chave no servidor.
5. Dê dois cliques em **iniciar.cmd**. Se o Windows mostrar uma confirmação para executar o arquivo baixado, confira o conteúdo antes de executar. Mantenha a janela aberta.
6. Abra **http://localhost:8080/index.php?page=login**. Sua conta admin anterior continua válida. Em banco novo, use **Configurar administrador inicial**. Cadastro normal é público com Gestor, Despachante ou Motorista; ninguém cria outro admin.
7. Entre como administrador e abra **Demonstração → Preparar demonstração**. Isso adiciona veículos, contas de simulação, histórico e pedidos de hoje sem trocar suas senhas.
8. Veja o Dashboard, a previsão e os riscos. Siga os passos da tela Demonstração para planejar, iniciar e acompanhar a rota.
9. Para alertas automáticos durante a apresentação, abra `bin/monitorar.cmd` e mantenha a janela aberta. Em uso contínuo, use o Agendador conforme README.
10. Abra **SENN AI-X Helper**: a IA local já funciona sem chave (`AI_MODE=local`, padrão). Para usar Gemini futuramente, configure `AI_MODE=gemini` e `GEMINI_API_KEY=` e reinicie o servidor. O chat local continua disponível nas falhas.

Se preferir abrir pelo CMD: na pasta que contém `iniciar.cmd`, execute:

```cmd
"C:\xampp\php\php.exe" -S localhost:8080 -t public public/router.php
```

O caminho do executável deve ter aspas no começo e no fim. Se XAMPP está em outra unidade, edite o caminho. Se a porta 8080 estiver ocupada, use 8081 e abra essa porta no navegador.

PHP necessário: 8.2 ou superior, com PDO MySQL, cURL e mbstring. No WAMP, ajuste os executáveis nos scripts. Apache serve o phpMyAdmin; a aplicação acima usa o servidor de desenvolvimento PHP. Não exponha esse servidor na internet.
