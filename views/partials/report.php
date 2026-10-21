<?php if(($metrics['demo_count']??0)>0): ?><p class="notice">Relatório contém dados sintéticos de demonstração; <?= e($metrics['demo_count']) ?> pedidos fictícios e <?= e($metrics['synthetic_km']) ?> km sintéticos. Não representa operação real.</p><?php endif; ?>
<section class="panel">
    <p class="eyebrow">RELATÓRIO GERENCIAL · <?= e($from . ' a ' . $to) ?></p>
    <h2>SENN AI-X · Indicadores da operação</h2>
    <dl class="report-metrics">
        <?php foreach (['Pedidos' => $metrics['total'], 'Entregues' => $metrics['done'], 'OTD (%)' => $metrics['otd'] ?? 'Sem dados', 'Custo estimado por entrega roteirizada (R$)' => $metrics['cost'] ?? 'Sem dados', 'Km GPS amostrados' => $metrics['km'], 'Ocupação atual da frota (%)' => $metrics['occupancy']] as $label => $value): ?>
            <dt><?= e($label) ?></dt>
            <dd><?= e($value) ?></dd><?php endforeach; ?>
    </dl>
    <p>Pedidos selecionados por data de cadastro. OTD entre entregas concluídas. Km por data de saída de rota. Ocupação
        é o estado atual; custo é uma estimativa alocada por parada.</p><button id="print-report">Imprimir / salvar como
        PDF</button>
</section>