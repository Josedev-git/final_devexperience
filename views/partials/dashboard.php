<?php if(($metrics['demo_count']??0)>0): ?><p class="notice">Demonstração: este período contém <?= e($metrics['demo_count']) ?> pedidos fictícios. Dos quilômetros exibidos, <?= e(number_format($metrics['synthetic_km'],1,',','.')) ?> km são sintéticos. Os indicadores não representam uma operação real.</p><?php endif; ?>
<form class="toolbar" method="get"><input type="hidden" name="page" value="dashboard"><label>De<input type="date"
            name="from" value="<?= e($from) ?>" required></label><label>Até<input type="date" name="to"
            value="<?= e($to) ?>" required></label><button>Aplicar período</button>
    <?php if ($currentUser['role'] !== 'motorista'): ?><a class="button secondary"
            href="<?= e(url('export') . '&from=' . $from . '&to=' . $to) ?>">Exportar CSV / Excel</a><a class="button secondary"
            href="<?= e(url('report') . '&from=' . $from . '&to=' . $to) ?>">Relatório / PDF</a><?php endif; ?></form>
<section class="metric-grid" aria-label="Indicadores">
    <article><span>ENTREGAS NO PRAZO ·
            OTD</span><strong><?= $metrics['otd'] === null ? '—' : e($metrics['otd']) . '%' ?></strong><small>Entre entregas
            concluídas</small></article>
    <article><span>CUSTO /
            ENTREGA</span><strong><?= $metrics['cost'] === null ? '—' : e(money((float) $metrics['cost'])) ?></strong><small>Estimado
            · pedidos roteirizados</small></article>
    <article><span>KM PERCORRIDOS</span><strong><?= e(number_format($metrics['km'], 1, ',', '.')) ?></strong><small>GPS
            amostrado · saídas no período</small></article>
    <article>
        <span><?= $currentUser['role'] === 'motorista' ? 'ENTREGAS CONCLUÍDAS' : 'FROTA OCUPADA' ?></span><strong><?= $currentUser['role'] === 'motorista' ? e($metrics['done']) : e($metrics['occupancy']) . '%' ?></strong><small><?= $currentUser['role'] === 'motorista' ? 'No período selecionado' : 'Posição atual · inclui reservas' ?></small>
    </article>
</section>
<div class="two-col">
    <section class="panel">
        <div class="panel-title">
            <h2>Fluxo de entregas</h2><span class="pill"><?= e($metrics['total']) ?> pedidos</span>
        </div><?php foreach ($metrics['statuses'] as $s): ?>
            <div class="bar-row"><span><?= e($s['label']) ?></span><meter min="0" max="<?= max(1, $metrics['total']) ?>"
                    value="<?= e($s['value']) ?>"><?= e($s['value']) ?></meter><strong><?= e($s['value']) ?></strong></div>
        <?php endforeach;
        if (!$metrics['statuses']): ?>
            <p>Sem entregas neste período.</p><?php endif; ?>
    </section>
    <section class="panel">
        <h2>Demanda por região</h2><?php foreach ($metrics['regions'] as $s): ?>
            <div class="bar-row"><span><?= e($s['label']) ?></span><meter min="0" max="<?= max(1, $metrics['total']) ?>"
                    value="<?= e($s['value']) ?>"><?= e($s['value']) ?></meter><strong><?= e($s['value']) ?></strong></div>
        <?php endforeach;
        if (!$metrics['regions']): ?>
            <p>Cadastre pedidos para acompanhar a distribuição.</p><?php endif; ?>
    </section>
</div>
<section class="panel callout">
    <div>
        <p class="eyebrow">PLANEJAMENTO & ACOMPANHAMENTO</p>
        <h2>A operação continua por aqui.</h2>
        <p>Consulte as janelas, os riscos e a disponibilidade da frota antes da próxima saída.</p>
    </div><a class="button" href="<?= e(url($currentUser['role'] === 'motorista' ? 'driver' : 'intelligence')) ?>">Abrir
        operação →</a>
</section>
<p class="subtle">Pedidos e status: data de cadastro no período. Km: data de saída da rota. GPS sem sinal pode
    subestimar a distância. Veja as definições no manual.</p>
<?php if ($currentUser['role']!=='motorista'): ?>
<section class="panel"><h2>Desempenho regional</h2><div class="table-wrap"><table><thead><tr><th>Região</th><th>Pedidos</th><th>OTD</th><th>Custo / pedido roteirizado</th></tr></thead><tbody>
<?php foreach($regional as $r): ?><tr><td><?= e($r['region']) ?></td><td><?= e($r['deliveries']) ?></td><td><?= $r['done']?e(round(100*$r['on_time']/$r['done'],1)).'%':'—' ?></td><td><?= $r['routed']?e(money((float)$r['estimated_cost']/$r['routed'])):'—' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php $risks=$riskReport['items']; require ROOT.'/views/intelligence.php'; ?>
<?php endif; ?>
