<section class="panel ai-local-intro"><div><p class="eyebrow">INTELIGÊNCIA OPERACIONAL / MODELOS LOCAIS</p><h2>Os dados da operação, com contexto.</h2><p>Previsão por sazonalidade e tendência, riscos por regressão logística e um assistente que consulta o seu acesso. O modo local funciona sem chave de API.</p></div><div class="ai-shortcuts"><button type="button" class="secondary" data-helper-question="Previsão de demanda para 30 dias">Consultar demanda</button><button type="button" class="secondary" data-helper-question="Quais entregas têm maior risco?">Priorizar entregas</button><button type="button" data-helper-question="Qual região tem maior custo?">Analisar custos ↗</button></div></section>
<section class="panel">
    <div class="panel-title">
        <h2>Previsão de demanda</h2><span class="pill">Modelo sazonal local · 56 dias</span>
    </div>
    <p><?= e($forecast['warning']) ?> MAE: <?= e($forecast['mae'] ?? 'sem histórico') ?> pedidos/dia.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Região</th>
                    <th>Próximos 7 dias</th>
                    <th>Próximos 30 dias</th>
                    <th>Volume relativo · 30 dias</th>
                </tr>
            </thead>
            <tbody>
                <?php $maximum = max([1, ...array_column($forecast['regions'], 'next30')]);
                foreach ($forecast['regions'] as $f): ?>
                    <tr>
                        <td><?= e($f['region']) ?></td>
                        <td><?= e($f['next7']) ?></td>
                        <td><?= e($f['next30']) ?></td>
                        <td><meter min="0" max="<?= e($maximum) ?>"
                                value="<?= e($f['next30']) ?>"><?= e($f['next30']) ?></meter></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <details>
        <summary>Projeção diária · tabela acessível</summary><?php foreach ($forecast['regions'] as $f): ?>
            <h3><?= e($f['region']) ?></h3>
            <div class="daily-grid"><?php foreach ($f['daily'] as $day): ?>
                    <div><small><?= e($day['day']) ?></small><strong><?= e($day['value']) ?></strong></div><?php endforeach; ?>
            </div><?php endforeach; ?>
    </details>
</section>
<section class="panel">
    <h2>Análise inteligente de desempenho</h2>
    <p>Análise local pronta para uso, sem chave: compara custos e OTD por região e recomenda ações com indicadores agregados <?= isset($from)?'do período selecionado':'do mês atual' ?>. Gemini é uma opção de configuração. Recomendações precisam de
        revisão humana.</p><button type="button" id="analysis-button">Gerar análise →</button>
    <p id="analysis-answer" aria-live="polite" role="status"></p>
</section>
<section class="panel">
    <h2>Riscos de atraso / falha</h2>
    <p>Regressão logística treinada no histórico e ajustes operacionais para priorização (0–100). O escore final não é uma probabilidade calibrada. Até 500 entregas por vencimento.</p><p>Amostras: <?= e($riskReport['validation']['samples']) ?> · Brier no teste temporal (menor é melhor): <?= e($riskReport['validation']['brier'] ?? 'histórico insuficiente') ?>. Dados sintéticos servem apenas para demonstrar o fluxo.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Escore</th>
                    <th>Motivo</th>
                    <th>Ação sugerida</th>
                </tr>
            </thead>
            <tbody><?php foreach ($risks as $r): ?>
                    <tr>
                        <td><?= e($r['reference']) ?></td>
                        <td><span class="pill <?= $r['score'] >= 60 ? 'warning' : '' ?>"><?= e($r['score']) ?></span></td>
                        <td><?= e($r['reason']) ?></td>
                        <td><?= e($r['action']) ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>