<footer class="site-footer"><span>SENN AI-X / GESTÃO DE ENTREGAS</span><span>Projeto SENAI · 2026</span></footer>
</main>
<?php if ($currentUser): ?>
    <details class="helper" id="helper-panel">
        <summary><span class="helper-symbol" aria-hidden="true">?</span> SENN AI-X Helper <span class="helper-toggle" aria-hidden="true">+</span></summary>
        <div class="helper-workspace">
            <header class="helper-heading"><div><strong>Vamos conferir a operação?</strong><span id="helper-mode" class="helper-mode"><?= env('AI_MODE','local')==='gemini'?'Gemini opcional · fallback local':'SENN Local · sem chave de API' ?></span></div><button type="button" id="chat-clear" class="secondary" aria-label="Limpar histórico da conversa">Limpar</button></header>
            <div id="chat-messages" class="chat-messages" role="log" aria-label="Conversa com o assistente" aria-live="polite" aria-relevant="additions"><article class="chat-message assistant"><span class="message-label">SENN LOCAL</span><p>Olá! Posso conferir entregas, indicadores, previsões e riscos, ou ajudar você a usar o sistema. Escolha uma pergunta ou escreva abaixo.</p></article></div>
            <div id="chat-suggestions" class="chat-suggestions" aria-label="Perguntas sugeridas">
                <button type="button" data-question="Como está o desempenho hoje?">Resumo de hoje</button><button type="button" data-question="Quais entregas estão atrasadas?">Entregas atrasadas</button><button type="button" data-question="Como planejar uma rota?">Planejar rota</button>
            </div>
            <form id="chat-form"><label for="question">Pergunte sobre a operação</label><div class="chat-compose"><textarea id="question" name="question" rows="2" maxlength="500" required placeholder="Ex.: qual região tem o maior custo?" autocomplete="off"></textarea><button type="submit" aria-label="Enviar pergunta">Enviar <span aria-hidden="true">↗</span></button></div><div class="chat-compose-meta"><small>Enter envia · Shift + Enter quebra a linha</small><span id="chat-count">0/500</span></div></form>
            <p id="chat-answer" class="chat-feedback" role="status" aria-live="polite"></p>
            <p class="helper-privacy">Histórico temporário nesta sessão (até 8 perguntas). Não inclua dados pessoais. O modo local consulta somente seu acesso e não envia perguntas a APIs; Gemini envia dados ao provedor quando habilitado.</p>
        </div>
    </details><?php endif; ?></body>

</html>